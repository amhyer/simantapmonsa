<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\User;
use App\Services\PembelajaranMendalam\GeminiPembelajaranService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class AtpController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly GeminiPembelajaranService $ai,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $mapelList = $this->ctx->daftarMapel();

        $mapelAktif = trim((string) $request->query('mapel', $mapelList->first()?->nama ?? 'Matematika'));
        $faseAktif = strtoupper(trim((string) $request->query('fase', 'A')));
        if (! array_key_exists($faseAktif, CapaianPembelajaran::FASE_LABELS)) {
            $faseAktif = 'A';
        }

        $cpAcuan = CapaianPembelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $mapelAktif)
            ->where('fase', $faseAktif)
            ->orderBy('urutan')
            ->get();

        $tpList = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $mapelAktif)
            ->where('fase', $faseAktif)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        $kelasFase = TujuanPembelajaranPm::kelasUntukFase($faseAktif);
        $ringkasan = [];
        foreach ($kelasFase as $k) {
            foreach ([1, 2] as $sem) {
                $filtered = $tpList->where('kelas_target', $k)->where('semester_target', $sem);
                $ringkasan[] = [
                    'kelas' => $k,
                    'semester' => $sem,
                    'jumlah_tp' => $filtered->count(),
                    'total_jp' => (int) $filtered->sum('alokasi_jp'),
                ];
            }
        }

        return view('guru.pm.atp.index', [
            'mapelList' => $mapelList,
            'mapelAktif' => $mapelAktif,
            'faseAktif' => $faseAktif,
            'faseLabels' => CapaianPembelajaran::FASE_LABELS,
            'kelasFase' => $kelasFase,
            'cpAcuan' => $cpAcuan,
            'tpList' => $tpList,
            'ringkasan' => $ringkasan,
            'aiQuota' => $this->ai->quotaStatus($tenantId),
        ]);
    }

    public function simpanAlur(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $items = $request->input('items', []);

        if (! is_array($items)) {
            return back()->with('error', 'Data ATP tidak valid.');
        }

        $updated = 0;
        foreach ($items as $tpId => $row) {
            if (! is_array($row)) {
                continue;
            }
            $tp = TujuanPembelajaranPm::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('id', (int) $tpId)
                ->first();

            if (! $tp) {
                continue;
            }

            $tp->update([
                'urutan' => max(1, (int) ($row['urutan'] ?? $tp->urutan)),
                'kelas_target' => ! empty($row['kelas_target']) ? max(1, min(6, (int) $row['kelas_target'])) : null,
                'semester_target' => in_array((int) ($row['semester_target'] ?? 0), [1, 2], true) ? (int) $row['semester_target'] : null,
                'materi_pokok' => trim((string) ($row['materi_pokok'] ?? '')) ?: null,
                'alokasi_jp' => max(0, min(100, (int) ($row['alokasi_jp'] ?? 0))),
            ]);
            $updated++;
        }

        return back()->with('success', "Alur Tujuan Pembelajaran ({$updated} TP) berhasil disimpan.");
    }

    public function aiGenerate(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'fase' => ['required', 'in:A,B,C,D,E,F'],
        ]);

        $tpList = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $data['nama_mapel'])
            ->where('fase', $data['fase'])
            ->orderBy('urutan')
            ->get();

        if ($tpList->isEmpty()) {
            return back()->with('error', 'Belum ada Tujuan Pembelajaran pada mapel & fase ini. Buat TP terlebih dahulu.');
        }

        $payload = $tpList->map(fn (TujuanPembelajaranPm $tp) => [
            'id' => (int) $tp->id,
            'kode_tp' => (string) ($tp->kode_tp ?: 'TP.'.$tp->urutan),
            'deskripsi' => (string) $tp->deskripsi,
        ])->values()->all();

        try {
            $hasil = $this->ai->susunAtp($data['nama_mapel'], $data['fase'], $payload, $tenantId, $guru->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $byId = $tpList->keyBy('id');
        $applied = 0;
        foreach ($hasil as $row) {
            /** @var TujuanPembelajaranPm|null $tp */
            $tp = $byId->get($row['id']);
            if (! $tp) {
                continue;
            }
            $tp->update([
                'urutan' => $row['urutan'],
                'kelas_target' => $row['kelas_target'],
                'semester_target' => $row['semester_target'],
                'materi_pokok' => $row['materi_pokok'] ?: $tp->materi_pokok,
                'alokasi_jp' => $row['alokasi_jp'],
            ]);
            $applied++;
        }

        return back()->with('success', "Alur ATP untuk {$applied} TP berhasil disusun otomatis oleh AI.");
    }

    public function cetakPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $mapel = trim((string) $request->query('mapel', ''));
        $fase = strtoupper(trim((string) $request->query('fase', 'A')));
        $kelasFilter = $request->query('kelas') ? (int) $request->query('kelas') : null;

        $tpList = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->where('fase', $fase)
            ->when($kelasFilter !== null, fn ($q) => $q->where('kelas_target', $kelasFilter))
            ->orderBy('nama_mapel')
            ->orderBy('kelas_target')
            ->orderBy('semester_target')
            ->orderBy('urutan')
            ->get();

        $pdf = Pdf::loadView('guru.pm.pdf.atp', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'tpList' => $tpList,
            'mapel' => $mapel ?: 'Semua Mata Pelajaran',
            'fase' => $fase,
            'kelasFilter' => $kelasFilter,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('alur-tujuan-pembelajaran.pdf');
    }
}
