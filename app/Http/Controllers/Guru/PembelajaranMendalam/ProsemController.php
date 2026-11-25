<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\KalenderPendidikan;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\ProtaItem;
use App\Models\User;
use App\Services\PembelajaranMendalam\GuruScopeService;
use App\Services\PembelajaranMendalam\KalenderPendidikanService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProsemController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly KalenderPendidikanService $kalender,
        private readonly GuruScopeService $scope,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $mapelList = $this->scope->filterMapelCollection($this->ctx->daftarMapel(), $guru, $tenantId);
        $ctx = $this->ctx->getContext($request, $guru);
        $tahunAjaran = $ctx['tahun_ajaran'];

        $tingkat = $ctx['tingkat'];
        $this->scope->authorizeTingkat($guru, $tingkat, $tenantId);
        $semester = $ctx['semester'];
        $mapelAktif = trim((string) $request->query('mapel', $mapelList->first()?->nama ?? 'Matematika'));
        $this->scope->authorizeMapel($guru, $mapelAktif, $tenantId);

        $defaultJp = $this->kalender->jpPerMingguMapel($tenantId, $tahunAjaran, $mapelAktif, $tingkat);
        $jpPerMinggu = max(1, min(20, (int) $request->query('jp', $defaultJp)));

        $ringkasan = $this->kalender->ringkasanSemester($tenantId, $tahunAjaran, $semester, $jpPerMinggu);

        $prosemRows = ProsemItem::query()
            ->with('tp:id,kode_tp,deskripsi')
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $mapelAktif)
            ->where('semester', $semester)
            ->orderBy('bulan')
            ->orderBy('minggu_ke')
            ->orderBy('id')
            ->get();

        $jumlahProtaSem = ProtaItem::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $mapelAktif)
            ->where('semester', $semester)
            ->count();

        // Kelompokkan untuk tampilan matriks per materi/TP
        $matriksRows = $prosemRows
            ->groupBy(fn (ProsemItem $item) => ($item->bab ?: '-').'|'.$item->materi)
            ->map(function ($group) {
                /** @var ProsemItem $first */
                $first = $group->first();
                $sel = [];
                foreach ($group as $g) {
                    $key = $g->bulan.'_'.$g->minggu_ke;
                    $sel[$key] = ($sel[$key] ?? 0) + (int) $g->alokasi_jp;
                }

                return [
                    'bab' => $first->bab ?: '-',
                    'kode_tp' => $first->tp?->kode_tp ?: '-',
                    'materi' => $first->materi,
                    'total_jp' => (int) $group->sum('alokasi_jp'),
                    'sel' => $sel,
                ];
            })
            ->values();

        return view('guru.pm.prosem.index', [
            'tahunAjaran' => $tahunAjaran,
            'tingkat' => $tingkat,
            'semester' => $semester,
            'mapelList' => $mapelList,
            'mapelAktif' => $mapelAktif,
            'jpPerMinggu' => $jpPerMinggu,
            'ringkasan' => $ringkasan,
            'prosemRows' => $prosemRows,
            'matriksRows' => $matriksRows,
            'totalJpProsem' => (int) $prosemRows->sum('alokasi_jp'),
            'jumlahProtaSem' => $jumlahProtaSem,
            'bulanNama' => KalenderPendidikan::NAMA_BULAN,
        ]);
    }

    public function susunOtomatis(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:12'],
            'nama_mapel' => ['required', 'string', 'max:120'],
            'semester' => ['required', 'in:1,2'],
            'jp_per_minggu' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $this->scope->authorizeNotLockedBySupervisi($guru, $tenantId, $data['tahun_ajaran'], (int) $data['semester']);
        $this->scope->authorizeTingkat($guru, (int) $data['tingkat'], $tenantId);
        $this->scope->authorizeMapel($guru, $data['nama_mapel'], $tenantId);

        $created = $this->kalender->susunProsemOtomatis(
            $tenantId,
            $guru->id,
            $data['tahun_ajaran'],
            (int) $data['tingkat'],
            $data['nama_mapel'],
            (int) $data['semester'],
            (int) $data['jp_per_minggu']
        );

        if ($created === 0) {
            return back()->with('error', 'Tidak dapat menyusun Prosem otomatis. Pastikan Program Tahunan (Prota) untuk semester ini sudah diisi.');
        }

        return redirect()
            ->route('guru.pm.prosem.index', [
                'tahun_ajaran' => $data['tahun_ajaran'],
                'tingkat' => $data['tingkat'],
                'semester' => $data['semester'],
                'mapel' => $data['nama_mapel'],
                'jp' => $data['jp_per_minggu'],
            ])
            ->with('success', "Program Semester berhasil disusun otomatis ({$created} slot minggu efektif) dari Program Tahunan.");
    }

    public function storeBaris(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:12'],
            'nama_mapel' => ['required', 'string', 'max:120'],
            'semester' => ['required', 'in:1,2'],
            'bab' => ['nullable', 'string', 'max:120'],
            'materi' => ['required', 'string', 'max:255'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'minggu_ke' => ['required', 'integer', 'min:1', 'max:5'],
            'alokasi_jp' => ['required', 'integer', 'min:1', 'max:40'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $this->scope->authorizeNotLockedBySupervisi($guru, $tenantId, $data['tahun_ajaran'], (int) $data['semester']);
        $this->scope->authorizeTingkat($guru, (int) $data['tingkat'], $tenantId);
        $this->scope->authorizeMapel($guru, $data['nama_mapel'], $tenantId);

        ProsemItem::create([
            'tenant_id' => $tenantId,
            'guru_id' => $guru->id,
            'tahun_ajaran' => $data['tahun_ajaran'],
            'tingkat' => (int) $data['tingkat'],
            'nama_mapel' => $data['nama_mapel'],
            'semester' => (int) $data['semester'],
            'bab' => ! empty($data['bab']) ? $data['bab'] : null,
            'materi' => $data['materi'],
            'bulan' => (int) $data['bulan'],
            'minggu_ke' => (int) $data['minggu_ke'],
            'alokasi_jp' => (int) $data['alokasi_jp'],
            'keterangan' => ! empty($data['keterangan']) ? $data['keterangan'] : null,
        ]);

        return back()->with('success', 'Baris Program Semester berhasil ditambahkan.');
    }

    public function destroyBaris(ProsemItem $prosem): RedirectResponse
    {
        Gate::authorize('delete', $prosem);
        $prosem->delete();

        return back()->with('success', 'Baris Program Semester berhasil dihapus.');
    }

    public function cetakPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $tingkat = max(1, min(12, (int) $request->query('tingkat', 1)));
        $this->scope->authorizeTingkat($guru, $tingkat, $tenantId);
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true) ? (int) $request->query('semester', 1) : 1;
        $mapel = trim((string) $request->query('mapel', ''));
        if ($mapel !== '') {
            $this->scope->authorizeMapel($guru, $mapel, $tenantId);
        }

        $ringkasan = $this->kalender->ringkasanSemester($tenantId, $tahunAjaran, $semester, 4);

        $rows = ProsemItem::query()
            ->with('tp:id,kode_tp')
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->where('semester', $semester)
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->orderBy('nama_mapel')
            ->orderBy('bulan')
            ->orderBy('minggu_ke')
            ->get();

        $matriksRows = $rows
            ->groupBy(fn (ProsemItem $item) => $item->nama_mapel.'|'.($item->bab ?: '-').'|'.$item->materi)
            ->map(function ($group) {
                /** @var ProsemItem $first */
                $first = $group->first();
                $sel = [];
                foreach ($group as $g) {
                    $key = $g->bulan.'_'.$g->minggu_ke;
                    $sel[$key] = ($sel[$key] ?? 0) + (int) $g->alokasi_jp;
                }

                return [
                    'mapel' => $first->nama_mapel,
                    'bab' => $first->bab ?: '-',
                    'kode_tp' => $first->tp?->kode_tp ?: '-',
                    'materi' => $first->materi,
                    'total_jp' => (int) $group->sum('alokasi_jp'),
                    'sel' => $sel,
                ];
            })
            ->values();

        $pdf = Pdf::loadView('guru.pm.pdf.prosem', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'tahunAjaran' => $tahunAjaran,
            'tingkat' => $tingkat,
            'semester' => $semester,
            'mapel' => $mapel ?: 'Semua Mata Pelajaran',
            'ringkasan' => $ringkasan,
            'matriksRows' => $matriksRows,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("prosem-kelas-{$tingkat}-sem-{$semester}.pdf");
    }
}
