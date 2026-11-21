<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\BukuPaketSibi;
use App\Models\PembelajaranMendalam\ProtaItem;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\User;
use App\Services\PembelajaranMendalam\GeminiPembelajaranService;
use App\Services\PembelajaranMendalam\KalenderPendidikanService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class ProtaController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly KalenderPendidikanService $kalender,
        private readonly GeminiPembelajaranService $ai,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $mapelList = $this->ctx->daftarMapel();
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());

        $tingkat = max(1, min(6, (int) $request->query('tingkat', 1)));
        $fase = TujuanPembelajaranPm::faseUntukKelas($tingkat);
        $mapelAktif = trim((string) $request->query('mapel', $mapelList->first()?->nama ?? 'Matematika'));

        $defaultJp = $this->kalender->jpPerMingguMapel($tenantId, $tahunAjaran, $mapelAktif, $tingkat);
        $jpPerMinggu = max(1, min(20, (int) $request->query('jp', $defaultJp)));

        $sem1 = $this->kalender->ringkasanSemester($tenantId, $tahunAjaran, 1, $jpPerMinggu);
        $sem2 = $this->kalender->ringkasanSemester($tenantId, $tahunAjaran, 2, $jpPerMinggu);

        $protaRows = ProtaItem::query()
            ->with('tp:id,kode_tp,deskripsi')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $mapelAktif)
            ->orderBy('semester')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        $tpOptions = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $mapelAktif)
            ->where('fase', $fase)
            ->orderBy('urutan')
            ->get(['id', 'kode_tp', 'deskripsi', 'kelas_target', 'semester_target', 'materi_pokok', 'alokasi_jp']);

        return view('guru.pm.prota.index', [
            'tahunAjaran' => $tahunAjaran,
            'tingkat' => $tingkat,
            'fase' => $fase,
            'mapelList' => $mapelList,
            'mapelAktif' => $mapelAktif,
            'jpPerMinggu' => $jpPerMinggu,
            'sem1' => $sem1,
            'sem2' => $sem2,
            'protaRows' => $protaRows,
            'tpOptions' => $tpOptions,
            'totalJpSem1' => (int) $protaRows->where('semester', 1)->sum('alokasi_jp'),
            'totalJpSem2' => (int) $protaRows->where('semester', 2)->sum('alokasi_jp'),
            'aiQuota' => $this->ai->quotaStatus($tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'nama_mapel' => ['required', 'string', 'max:120'],
            'rows' => ['nullable', 'array'],
        ]);

        ProtaItem::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $data['tahun_ajaran'])
            ->where('tingkat', (int) $data['tingkat'])
            ->where('nama_mapel', $data['nama_mapel'])
            ->delete();

        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        $urutan = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $materi = trim((string) ($row['materi'] ?? ''));
            if ($materi === '') {
                continue;
            }
            $urutan++;
            ProtaItem::create([
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'tahun_ajaran' => $data['tahun_ajaran'],
                'tingkat' => (int) $data['tingkat'],
                'nama_mapel' => $data['nama_mapel'],
                'semester' => in_array((int) ($row['semester'] ?? 1), [1, 2], true) ? (int) $row['semester'] : 1,
                'tp_id' => ! empty($row['tp_id']) ? (int) $row['tp_id'] : null,
                'urutan' => $urutan,
                'materi' => $materi,
                'alokasi_jp' => max(0, min(200, (int) ($row['alokasi_jp'] ?? 0))),
                'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
            ]);
        }

        return redirect()
            ->route('guru.pm.prota.index', [
                'tahun_ajaran' => $data['tahun_ajaran'],
                'tingkat' => $data['tingkat'],
                'mapel' => $data['nama_mapel'],
            ])
            ->with('success', "Program Tahunan ({$urutan} baris) berhasil disimpan.");
    }

    public function ambilDariAtp(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'nama_mapel' => ['required', 'string', 'max:120'],
        ]);

        $tingkat = (int) $data['tingkat'];
        $fase = TujuanPembelajaranPm::faseUntukKelas($tingkat);

        $atpRows = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $data['nama_mapel'])
            ->where('fase', $fase)
            ->where(function ($q) use ($tingkat): void {
                $q->where('kelas_target', $tingkat)->orWhereNull('kelas_target');
            })
            ->orderBy('semester_target')
            ->orderBy('urutan')
            ->get();

        if ($atpRows->isEmpty()) {
            return back()->with('error', 'Belum ada TP/ATP untuk mapel dan kelas ini. Silakan lengkapi menu ATP terlebih dahulu.');
        }

        ProtaItem::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $data['tahun_ajaran'])
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $data['nama_mapel'])
            ->delete();

        $urutan = 0;
        foreach ($atpRows as $tp) {
            $urutan++;
            ProtaItem::create([
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'tahun_ajaran' => $data['tahun_ajaran'],
                'tingkat' => $tingkat,
                'nama_mapel' => $data['nama_mapel'],
                'semester' => in_array((int) $tp->semester_target, [1, 2], true) ? (int) $tp->semester_target : 1,
                'tp_id' => $tp->id,
                'urutan' => $urutan,
                'materi' => $tp->materi_pokok ?: mb_substr($tp->deskripsi, 0, 120),
                'alokasi_jp' => $tp->alokasi_jp > 0 ? $tp->alokasi_jp : 6,
                'keterangan' => $tp->kode_tp ? "Dari ATP ({$tp->kode_tp})" : 'Dari ATP',
            ]);
        }

        return redirect()
            ->route('guru.pm.prota.index', [
                'tahun_ajaran' => $data['tahun_ajaran'],
                'tingkat' => $tingkat,
                'mapel' => $data['nama_mapel'],
            ])
            ->with('success', "{$urutan} baris Program Tahunan berhasil diambil otomatis dari ATP.");
    }

    public function aiGenerate(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'nama_mapel' => ['required', 'string', 'max:120'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $tingkat = (int) $data['tingkat'];
        $fase = TujuanPembelajaranPm::faseUntukKelas($tingkat);
        $jpPerMinggu = $this->kalender->jpPerMingguMapel($tenantId, $data['tahun_ajaran'], $data['nama_mapel'], $tingkat);
        $sem1 = $this->kalender->ringkasanSemester($tenantId, $data['tahun_ajaran'], 1, $jpPerMinggu);
        $sem2 = $this->kalender->ringkasanSemester($tenantId, $data['tahun_ajaran'], 2, $jpPerMinggu);

        $tpList = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $data['nama_mapel'])
            ->where('fase', $fase)
            ->orderBy('urutan')
            ->get()
            ->map(fn (TujuanPembelajaranPm $tp) => [
                'id' => (int) $tp->id,
                'kode_tp' => (string) ($tp->kode_tp ?: 'TP.'.$tp->urutan),
                'deskripsi' => (string) $tp->deskripsi,
            ])->values()->all();

        $buku = BukuPaketSibi::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $data['nama_mapel'])
            ->where('tingkat', $tingkat)
            ->first();

        try {
            $items = $this->ai->susunProta(
                $data['nama_mapel'],
                $tingkat,
                $sem1['jp_tersedia'],
                $sem2['jp_tersedia'],
                $tpList,
                $buku?->daftar_isi,
                $data['catatan'] ?? null,
                $tenantId,
                $guru->id
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ProtaItem::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $data['tahun_ajaran'])
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $data['nama_mapel'])
            ->delete();

        $urutan = 0;
        foreach ($items as $item) {
            $urutan++;
            ProtaItem::create([
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'tahun_ajaran' => $data['tahun_ajaran'],
                'tingkat' => $tingkat,
                'nama_mapel' => $data['nama_mapel'],
                'semester' => $item['semester'],
                'tp_id' => $item['tp_id'],
                'urutan' => $urutan,
                'materi' => $item['materi'],
                'alokasi_jp' => $item['alokasi_jp'],
                'keterangan' => $item['keterangan'] ?: 'Disusun AI',
            ]);
        }

        return redirect()
            ->route('guru.pm.prota.index', [
                'tahun_ajaran' => $data['tahun_ajaran'],
                'tingkat' => $tingkat,
                'mapel' => $data['nama_mapel'],
            ])
            ->with('success', "{$urutan} baris Program Tahunan berhasil disusun oleh AI.");
    }

    public function cetakPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $tingkat = max(1, min(6, (int) $request->query('tingkat', 1)));
        $mapel = trim((string) $request->query('mapel', ''));

        $rows = ProtaItem::query()
            ->with('tp:id,kode_tp,deskripsi')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->orderBy('nama_mapel')
            ->orderBy('semester')
            ->orderBy('urutan')
            ->get();

        $pdf = Pdf::loadView('guru.pm.pdf.prota', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'tahunAjaran' => $tahunAjaran,
            'tingkat' => $tingkat,
            'mapel' => $mapel ?: 'Semua Mata Pelajaran',
            'rows' => $rows,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("prota-kelas-{$tingkat}.pdf");
    }
}
