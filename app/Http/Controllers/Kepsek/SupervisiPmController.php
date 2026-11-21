<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\User;
use App\Services\AktivitasService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use App\Services\PembelajaranMendalam\SupervisiPmService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupervisiPmController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly SupervisiPmService $service,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $kepsek */
        $kepsek = Auth::user();
        $tenantId = $this->ctx->tenantId($kepsek);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true) ? (int) $request->query('semester', 1) : 1;
        $cari = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));

        $result = $this->service->ringkasanSemuaGuru($tenantId, $tahunAjaran, $semester, $cari, $statusFilter);

        return view('kepsek.supervisi-pm.index', [
            'tahunAjaran' => $tahunAjaran,
            'semester' => $semester,
            'cari' => $cari,
            'statusFilter' => $statusFilter,
            'statusLabels' => SupervisiPm::STATUS_LABELS,
            'rows' => $result['rows'],
            'kpi' => $result['kpi'],
        ]);
    }

    public function show(Request $request, User $guru): View
    {
        /** @var User $kepsek */
        $kepsek = Auth::user();
        $tenantId = $this->ctx->tenantId($kepsek);
        $this->authorizeGuruSameTenant($tenantId, $guru);

        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true) ? (int) $request->query('semester', 1) : 1;

        $detail = $this->service->detailGuru($tenantId, $guru, $tahunAjaran, $semester);

        return view('kepsek.supervisi-pm.show', array_merge($detail, [
            'guru' => $guru,
            'tahunAjaran' => $tahunAjaran,
            'semester' => $semester,
            'statusLabels' => SupervisiPm::STATUS_LABELS,
        ]));
    }

    public function simpanKeputusan(Request $request, User $guru): RedirectResponse
    {
        /** @var User $kepsek */
        $kepsek = Auth::user();
        $tenantId = $this->ctx->tenantId($kepsek);
        $this->authorizeGuruSameTenant($tenantId, $guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'in:1,2'],
            'status' => ['required', 'in:menunggu,direview,disetujui,revisi'],
            'catatan_kepsek' => ['nullable', 'string', 'max:2000'],
        ]);

        $semester = (int) $data['semester'];
        $detail = $this->service->detailGuru($tenantId, $guru, $data['tahun_ajaran'], $semester);

        $supervisi = SupervisiPm::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'tahun_ajaran' => $data['tahun_ajaran'],
                'semester' => $semester,
            ],
            [
                'kepsek_id' => $kepsek->id,
                'status' => $data['status'],
                'skor_kelengkapan' => $detail['persen'],
                'catatan_kepsek' => ! empty($data['catatan_kepsek']) ? trim((string) $data['catatan_kepsek']) : null,
                'disetujui_at' => $data['status'] === SupervisiPm::STATUS_DISETUJUI ? now() : null,
            ]
        );

        AktivitasService::tambah(
            'Supervisi Pembelajaran Mendalam: '.$supervisi->statusLabel(),
            $guru->nama_lengkap." ({$data['tahun_ajaran']} S{$semester} · {$detail['persen']}%)",
            'pm_supervisi',
            $supervisi->id
        );

        return redirect()
            ->route('kepsek.supervisi-pm.show', [
                'guru' => $guru,
                'tahun_ajaran' => $data['tahun_ajaran'],
                'semester' => $semester,
            ])
            ->with('success', "Keputusan supervisi untuk {$guru->nama_lengkap} ({$supervisi->statusLabel()}) berhasil disimpan.");
    }

    public function cetakRekapPdf(Request $request): Response
    {
        /** @var User $kepsek */
        $kepsek = Auth::user();
        $tenantId = $this->ctx->tenantId($kepsek);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true) ? (int) $request->query('semester', 1) : 1;

        $result = $this->service->ringkasanSemuaGuru($tenantId, $tahunAjaran, $semester);

        $pdf = Pdf::loadView('kepsek.supervisi-pm.pdf-rekap', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'kepsek' => $kepsek,
            'tahunAjaran' => $tahunAjaran,
            'semester' => $semester,
            'rows' => $result['rows'],
            'kpi' => $result['kpi'],
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("rekap-supervisi-pm-sem-{$semester}.pdf");
    }

    private function authorizeGuruSameTenant(?int $tenantId, User $guru): void
    {
        if ($guru->peran !== 'guru') {
            abort(404);
        }

        $guruTenant = $guru->tenant_id !== null ? (int) $guru->tenant_id : null;
        if ($tenantId !== null && $guruTenant !== $tenantId) {
            abort(403, 'Anda tidak memiliki akses supervisi untuk guru dari sekolah lain.');
        }
    }
}
