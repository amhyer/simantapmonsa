<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\PembelajaranMendalam\VisitasiPengawas;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use App\Services\PembelajaranMendalam\PengawasAnalyticsService;
use App\Services\PembelajaranMendalam\SupervisiPmService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly SupervisiPmService $supervisi,
        private readonly PengawasAnalyticsService $analytics,
    ) {}

    /**
     * Dashboard Eksekutif Radar Mutu Satuan Pendidikan untuk Pengawas Sekolah.
     */
    public function index(Request $request): View
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true)
            ? (int) $request->query('semester', 1)
            : $this->ctx->semesterAktif();

        $radar = $this->analytics->radarMutu($tenantId, $tahunAjaran, $semester);

        return view('pengawas.dashboard', [
            'tahunAjaran' => $tahunAjaran,
            'semester' => $semester,
            'sekolah' => $this->ctx->sekolah($tenantId),
            'sekolahBinaanList' => $this->sekolahBinaanList($pengawas),
            'activeTenantId' => $tenantId,
            'kpi' => $radar['kpi'],
            'guruRows' => $radar['guru_rows'],
            'visitasiTerbaru' => $radar['visitasi_terbaru'],
        ]);
    }

    /**
     * Ganti konteks Sekolah Binaan aktif bagi Pengawas yang membina lebih dari 1 sekolah.
     */
    public function gantiSekolahBinaan(Request $request): RedirectResponse
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
        ]);

        $allowedIds = $this->allowedTenantIds($pengawas);
        $targetId = (int) $data['tenant_id'];
        if (! in_array($targetId, $allowedIds, true)) {
            abort(403, 'Sekolah tersebut tidak termasuk dalam daftar sekolah binaan Anda.');
        }

        session(['pengawas_active_tenant_id' => $targetId]);

        return back()->with('success', 'Konteks sekolah binaan berhasil dialihkan.');
    }

    /**
     * Daftar Supervisi Berjenjang (Guru -> Kepala Sekolah -> Pengawas).
     */
    public function supervisiIndex(Request $request): View
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true)
            ? (int) $request->query('semester', 1)
            : $this->ctx->semesterAktif();
        $cari = trim((string) $request->query('q', ''));
        $statusKepsek = trim((string) $request->query('status_kepsek', ''));
        $statusPengawas = trim((string) $request->query('status_pengawas', ''));

        $data = $this->supervisi->ringkasanSemuaGuru($tenantId, $tahunAjaran, $semester, $cari, $statusKepsek);
        $rows = $data['rows'];

        if ($statusPengawas !== '' && array_key_exists($statusPengawas, SupervisiPm::PENGAWAS_LABELS)) {
            $rows = $rows->filter(function (array $r) use ($statusPengawas): bool {
                /** @var SupervisiPm|null $sup */
                $sup = $r['supervisi'] ?? null;
                $st = $sup?->status_pengawas ?: SupervisiPm::PENGAWAS_MENUNGGU;

                return $st === $statusPengawas;
            })->values();
        }

        return view('pengawas.supervisi.index', [
            'tahunAjaran' => $tahunAjaran,
            'semester' => $semester,
            'cari' => $cari,
            'statusKepsek' => $statusKepsek,
            'statusPengawas' => $statusPengawas,
            'rows' => $rows,
            'kpi' => $data['kpi'],
            'statusKepsekLabels' => SupervisiPm::STATUS_LABELS,
            'statusPengawasLabels' => SupervisiPm::PENGAWAS_LABELS,
        ]);
    }

    /**
     * Inspeksi Read-Only 9 Dokumen Pembelajaran Mendalam Guru & Form Verifikasi Berjenjang Pengawas.
     */
    public function supervisiShow(Request $request, User $guru): View
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        $this->authorizeGuruInBinaan($guru, $tenantId);

        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true)
            ? (int) $request->query('semester', 1)
            : $this->ctx->semesterAktif();

        $detail = $this->supervisi->detailGuru($tenantId, $guru, $tahunAjaran, $semester);

        return view('pengawas.supervisi.show', array_merge($detail, [
            'guru' => $guru,
            'tahunAjaran' => $tahunAjaran,
            'semester' => $semester,
            'statusPengawasLabels' => SupervisiPm::PENGAWAS_LABELS,
        ]));
    }

    /**
     * Simpan Verifikasi Berjenjang, Catatan Pembinaan & Rekomendasi Tindak Lanjut (RTL) oleh Pengawas.
     */
    public function simpanVerifikasi(Request $request, User $guru): RedirectResponse
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        $this->authorizeGuruInBinaan($guru, $tenantId);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'in:1,2'],
            'status_pengawas' => ['required', 'in:menunggu,terverifikasi,pembinaan'],
            'catatan_pengawas' => ['nullable', 'string', 'max:2000'],
            'rekomendasi_rtl' => ['nullable', 'string', 'max:2000'],
        ]);

        $semester = (int) $data['semester'];
        $detail = $this->supervisi->detailGuru($tenantId, $guru, $data['tahun_ajaran'], $semester);

        $existing = SupervisiPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('guru_id', $guru->id)
            ->where('tahun_ajaran', $data['tahun_ajaran'])
            ->where('semester', $semester)
            ->first();

        SupervisiPm::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'tahun_ajaran' => $data['tahun_ajaran'],
                'semester' => $semester,
            ],
            [
                'status' => $existing?->status ?? SupervisiPm::STATUS_MENUNGGU,
                'skor_kelengkapan' => $detail['persen'],
                'pengawas_id' => $pengawas->id,
                'status_pengawas' => $data['status_pengawas'],
                'catatan_pengawas' => trim((string) ($data['catatan_pengawas'] ?? '')) ?: null,
                'rekomendasi_rtl' => trim((string) ($data['rekomendasi_rtl'] ?? '')) ?: null,
                'diverifikasi_pengawas_at' => in_array($data['status_pengawas'], [SupervisiPm::PENGAWAS_TERVERIFIKASI, SupervisiPm::PENGAWAS_PEMBINAAN], true)
                    ? now()
                    : null,
            ]
        );

        $label = SupervisiPm::PENGAWAS_LABELS[$data['status_pengawas']] ?? $data['status_pengawas'];

        return redirect()
            ->route('pengawas.supervisi.show', [
                'guru' => $guru->uuid,
                'tahun_ajaran' => $data['tahun_ajaran'],
                'semester' => $semester,
            ])
            ->with('success', "Verifikasi Pengawas untuk {$guru->nama_lengkap} berhasil disimpan ({$label}).");
    }

    /**
     * Modul E-Visitasi & Instrumen Observasi Kelas Pembelajaran Mendalam (Rubrik Mindful, Meaningful, Joyful).
     */
    public function visitasiIndex(Request $request): View
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true)
            ? (int) $request->query('semester', 1)
            : $this->ctx->semesterAktif();

        $guruList = User::query()
            ->where('peran', 'guru')
            ->where('aktif', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_lengkap')
            ->get(['id', 'uuid', 'nama_lengkap', 'kelas_mata_pelajaran']);

        $visitasiList = VisitasiPengawas::query()
            ->with(['guru:id,nama_lengkap,kelas_mata_pelajaran', 'pengawas:id,nama_lengkap'])
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->orderByDesc('tanggal_visitasi')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('pengawas.visitasi.index', [
            'tahunAjaran' => $tahunAjaran,
            'semester' => $semester,
            'guruList' => $guruList,
            'visitasiList' => $visitasiList,
            'jenisLabels' => VisitasiPengawas::JENIS_PENDAMPINGAN,
            'skalaRubrik' => VisitasiPengawas::SKALA_RUBRIK,
        ]);
    }

    public function visitasiStore(Request $request): RedirectResponse
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'in:1,2'],
            'tanggal_visitasi' => ['required', 'date'],
            'jenis_pendampingan' => ['required', 'in:supervisi_akademik,supervisi_manajerial,pendampingan_kurikulum,pemantauan_tka_literasi'],
            'guru_id' => ['nullable', 'integer'],
            'skor_berkesadaran' => ['required', 'integer', 'min:1', 'max:4'],
            'skor_bermakna' => ['required', 'integer', 'min:1', 'max:4'],
            'skor_menggembirakan' => ['required', 'integer', 'min:1', 'max:4'],
            'skor_memahami' => ['required', 'integer', 'min:1', 'max:4'],
            'skor_mengaplikasi' => ['required', 'integer', 'min:1', 'max:4'],
            'skor_merefleksi' => ['required', 'integer', 'min:1', 'max:4'],
            'temuan' => ['required', 'string', 'max:3000'],
            'rekomendasi' => ['required', 'string', 'max:3000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:3000'],
        ]);

        $guruId = null;
        if (! empty($data['guru_id'])) {
            $guru = User::query()
                ->where('id', (int) $data['guru_id'])
                ->where('peran', 'guru')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->first();
            if ($guru) {
                $guruId = $guru->id;
            }
        }

        VisitasiPengawas::create([
            'tenant_id' => $tenantId,
            'pengawas_id' => $pengawas->id,
            'guru_id' => $guruId,
            'tahun_ajaran' => $data['tahun_ajaran'],
            'semester' => (int) $data['semester'],
            'tanggal_visitasi' => $data['tanggal_visitasi'],
            'jenis_pendampingan' => $data['jenis_pendampingan'],
            'skor_berkesadaran' => (int) $data['skor_berkesadaran'],
            'skor_bermakna' => (int) $data['skor_bermakna'],
            'skor_menggembirakan' => (int) $data['skor_menggembirakan'],
            'skor_memahami' => (int) $data['skor_memahami'],
            'skor_mengaplikasi' => (int) $data['skor_mengaplikasi'],
            'skor_merefleksi' => (int) $data['skor_merefleksi'],
            'temuan' => trim($data['temuan']),
            'rekomendasi' => trim($data['rekomendasi']),
            'tindak_lanjut' => ! empty($data['tindak_lanjut']) ? trim($data['tindak_lanjut']) : null,
        ]);

        return back()->with('success', 'Berita Acara Visitasi & Instrumen Observasi Kelas berhasil disimpan.');
    }

    public function visitasiDestroy(VisitasiPengawas $visitasi): RedirectResponse
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        if (($tenantId !== null && (int) $visitasi->tenant_id !== $tenantId) || (int) $visitasi->pengawas_id !== (int) $pengawas->id) {
            abort(403, 'Anda hanya dapat menghapus catatan visitasi milik Anda sendiri.');
        }

        $visitasi->delete();

        return back()->with('success', 'Catatan visitasi berhasil dihapus.');
    }

    public function visitasiPdf(VisitasiPengawas $visitasi): Response
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        if ($tenantId !== null && (int) $visitasi->tenant_id !== $tenantId) {
            abort(403);
        }

        $visitasi->load(['guru', 'pengawas']);

        $pdf = Pdf::loadView('pengawas.pdf.visitasi', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'visitasi' => $visitasi,
            'pengawas' => $pengawas,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('berita-acara-visitasi-'.$visitasi->tanggal_visitasi->format('Y-m-d').'.pdf');
    }

    /**
     * Audit Keselarasan Kurikulum — KKTP — e-Rapor & Peta Hasil Belajar Kelas 1–6.
     */
    public function auditIndex(Request $request): View
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true)
            ? (int) $request->query('semester', 1)
            : $this->ctx->semesterAktif();

        $audit = $this->analytics->auditKeselarasan($tenantId, $tahunAjaran, $semester);

        return view('pengawas.audit.index', [
            'tahunAjaran' => $tahunAjaran,
            'semester' => $semester,
            'guruAudit' => $audit['guru_audit'],
            'petaKelas' => $audit['peta_kelas'],
            'ringkasan' => $audit['ringkasan'],
        ]);
    }

    /**
     * Pengaturan Mandiri Tanda Tangan Digital & Identitas Pengawas Sekolah.
     */
    public function tandaTanganIndex(): View
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        $sekolah = $this->ctx->sekolah($tenantId);
        $pengSekolah = is_array($sekolah?->pengaturan) ? $sekolah->pengaturan : [];

        return view('pengawas.ttd', [
            'sekolah' => $sekolah,
            'pengSekolah' => $pengSekolah,
            'pengawas' => $pengawas,
        ]);
    }

    public function tandaTanganStore(Request $request): RedirectResponse
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);

        $data = $request->validate([
            'nama_pengawas' => ['required', 'string', 'max:150'],
            'nip_pengawas' => ['nullable', 'string', 'max:40'],
            'ttd_pengawas' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'ttd_pengawas_canvas' => ['nullable', 'string'],
        ]);

        $namaSekolahDefault = ($tenantId !== null ? Tenant::query()->whereKey($tenantId)->value('nama_sekolah') : null)
            ?: (SekolahSettings::withoutGlobalScopes()
                ->whereNotNull('nama_sekolah')
                ->whereNotIn('nama_sekolah', ['SDN SIMANTAP', 'Sekolah Dasar'])
                ->value('nama_sekolah') ?: config('app.name', 'SIMANTAP'));

        $sekolah = SekolahSettings::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['guru_id' => $pengawas->id, 'nama_sekolah' => $namaSekolahDefault]
        );
        if (in_array($sekolah->nama_sekolah, ['SDN SIMANTAP', 'Sekolah Dasar'], true)) {
            $sekolah->nama_sekolah = $namaSekolahDefault;
        }

        $cfgSekolah = is_array($sekolah->pengaturan) ? $sekolah->pengaturan : [];
        $cfgSekolah['nama_pengawas'] = $data['nama_pengawas'];
        $cfgSekolah['nip_pengawas'] = $data['nip_pengawas'] ?? '';

        if ($request->hasFile('ttd_pengawas')) {
            $this->deleteIdentitasFile($cfgSekolah['ttd_pengawas'] ?? null);
            $cfgSekolah['ttd_pengawas'] = $request->file('ttd_pengawas')->store('private/identitas/ttd_pengawas', 'local');
        } elseif (! empty($data['ttd_pengawas_canvas']) && str_starts_with($data['ttd_pengawas_canvas'], 'data:image/png;base64,')) {
            $raw = base64_decode(substr($data['ttd_pengawas_canvas'], strlen('data:image/png;base64,')));
            if ($raw !== false && strlen($raw) > 100 && strlen($raw) <= 1048576) {
                $this->deleteIdentitasFile($cfgSekolah['ttd_pengawas'] ?? null);
                $filename = 'private/identitas/ttd_pengawas/pengawas_'.$pengawas->id.'_'.time().'.png';
                Storage::disk('local')->put($filename, $raw);
                $cfgSekolah['ttd_pengawas'] = $filename;
            }
        }

        $sekolah->update([
            'nama_sekolah' => $sekolah->nama_sekolah,
            'pengaturan' => $cfgSekolah,
        ]);

        return back()->with('success', 'Identitas & Tanda Tangan Digital Pengawas Sekolah berhasil disimpan.');
    }

    public function tandaTanganHapus(): RedirectResponse
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        $sekolah = $this->ctx->sekolah($tenantId);

        if ($sekolah && is_array($sekolah->pengaturan)) {
            $cfg = $sekolah->pengaturan;
            $this->deleteIdentitasFile($cfg['ttd_pengawas'] ?? null);
            unset($cfg['ttd_pengawas']);
            $sekolah->update(['pengaturan' => $cfg]);
        }

        return back()->with('success', 'Tanda tangan digital Pengawas berhasil dihapus.');
    }

    /**
     * Generator 1-Klik Laporan Kepengawasan Semester PDF (siap diserahkan ke Dinas Pendidikan / Korwas).
     */
    public function cetakLaporanSemesterPdf(Request $request): Response
    {
        /** @var User $pengawas */
        $pengawas = Auth::user();
        $tenantId = $this->activeTenantId($pengawas);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true)
            ? (int) $request->query('semester', 1)
            : $this->ctx->semesterAktif();

        $radar = $this->analytics->radarMutu($tenantId, $tahunAjaran, $semester);
        $audit = $this->analytics->auditKeselarasan($tenantId, $tahunAjaran, $semester);
        $visitasiAll = VisitasiPengawas::query()
            ->with('guru:id,nama_lengkap')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->orderBy('tanggal_visitasi')
            ->get();

        $pdf = Pdf::loadView('pengawas.pdf.laporan-semester', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'pengawas' => $pengawas,
            'tahunAjaran' => $tahunAjaran,
            'semester' => $semester,
            'kpi' => $radar['kpi'],
            'guruRows' => $radar['guru_rows'],
            'audit' => $audit,
            'visitasiList' => $visitasiAll,
        ])->setPaper('a4', 'landscape');

        $safeTahun = str_replace(['/', '\\', ' '], '-', $tahunAjaran);

        return $pdf->stream("laporan-kepengawasan-{$safeTahun}-sem-{$semester}.pdf");
    }

    private function activeTenantId(User $pengawas): ?int
    {
        $baseTenantId = $pengawas->tenant_id !== null ? (int) $pengawas->tenant_id : $this->ctx->tenantId($pengawas);
        $allowed = $this->allowedTenantIds($pengawas);
        $sessionTenant = session('pengawas_active_tenant_id');

        if ($sessionTenant !== null && in_array((int) $sessionTenant, $allowed, true)) {
            $activeId = (int) $sessionTenant;
            \App\Modules\Tenant\Contexts\TenantContext::set($activeId);

            return $activeId;
        }

        if ($baseTenantId !== null) {
            \App\Modules\Tenant\Contexts\TenantContext::set($baseTenantId);
        }

        return $baseTenantId;
    }

    /**
     * @return array<int, int>
     */
    private function allowedTenantIds(User $pengawas): array
    {
        $ids = [];
        if ($pengawas->tenant_id !== null) {
            $ids[] = (int) $pengawas->tenant_id;
        }
        if (is_array($pengawas->terhubung_dengan)) {
            foreach ($pengawas->terhubung_dengan as $id) {
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function sekolahBinaanList(User $pengawas)
    {
        $ids = $this->allowedTenantIds($pengawas);
        if (empty($ids)) {
            return collect();
        }

        return Tenant::query()->whereIn('id', $ids)->orderBy('nama_sekolah')->get(['id', 'nama_sekolah', 'npsn']);
    }

    private function authorizeGuruInBinaan(User $guru, ?int $tenantId): void
    {
        if ($guru->peran !== 'guru' || ($tenantId !== null && (int) $guru->tenant_id !== $tenantId)) {
            abort(403, 'Guru tidak berada pada sekolah binaan aktif.');
        }
    }

    private function deleteIdentitasFile(?string $path): void
    {
        if (! $path) {
            return;
        }
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
