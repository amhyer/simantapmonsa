<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\KktpInterval;
use App\Models\PembelajaranMendalam\KktpItem;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\PengaturanGuru;
use App\Models\User;
use App\Services\PembelajaranMendalam\GuruScopeService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KktpController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly GuruScopeService $scope,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $mapelList = $this->scope->filterMapelCollection($this->ctx->daftarMapel(), $guru, $tenantId);

        $ctx = $this->ctx->getContext($request, $guru);
        $tingkat = $ctx['tingkat'];
        $this->scope->authorizeTingkat($guru, $tingkat, $tenantId);
        $fase = $ctx['fase'];
        $mapelAktif = trim((string) $request->query('mapel', $mapelList->first()?->nama ?? 'Matematika'));
        $this->scope->authorizeMapel($guru, $mapelAktif, $tenantId);

        $interval = KktpInterval::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $mapelAktif)
            ->first();

        if (! $interval) {
            $interval = new KktpInterval([
                'nama_mapel' => $mapelAktif,
                'tingkat' => $tingkat,
                'nilai_kktp' => 75,
                'batas_bawah' => 60,
                'batas_atas' => 88,
            ]);
        }

        $tpList = TujuanPembelajaranPm::query()
            ->with('kktpItem')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $mapelAktif)
            ->where('fase', $fase)
            ->where(function ($q) use ($tingkat): void {
                $q->whereNull('kelas_target')->orWhere('kelas_target', $tingkat);
            })
            ->orderBy('urutan')
            ->get();

        return view('guru.pm.kktp.index', [
            'tingkat' => $tingkat,
            'fase' => $fase,
            'mapelList' => $mapelList,
            'mapelAktif' => $mapelAktif,
            'interval' => $interval,
            'rentangPredikat' => $interval->rentangPredikat(),
            'tpList' => $tpList,
            'pendekatanLabels' => KktpItem::PENDEKATAN_LABELS,
        ]);
    }

    public function simpanInterval(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $this->scope->authorizeNotLockedBySupervisi($guru, $tenantId);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:12'],
            'batas_bawah' => ['required', 'integer', 'min:1', 'max:90'],
            'nilai_kktp' => ['required', 'integer', 'gt:batas_bawah', 'max:95'],
            'batas_atas' => ['required', 'integer', 'gt:nilai_kktp', 'max:99'],
        ]);

        $this->scope->authorizeTingkat($guru, (int) $data['tingkat'], $tenantId);
        $this->scope->authorizeMapel($guru, $data['nama_mapel'], $tenantId);

        $mapelId = $this->ctx->daftarMapel()->firstWhere('nama', $data['nama_mapel'])?->id;

        KktpInterval::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'nama_mapel' => $data['nama_mapel'],
                'tingkat' => (int) $data['tingkat'],
            ],
            [
                'mata_pelajaran_id' => $mapelId,
                'batas_bawah' => (int) $data['batas_bawah'],
                'nilai_kktp' => (int) $data['nilai_kktp'],
                'batas_atas' => (int) $data['batas_atas'],
                'updated_by' => $guru->id,
            ]
        );

        // Sinkronkan nilai KKTP ke KKM PengaturanGuru agar e-Rapor & analisis nilai selaras
        $pengaturan = PengaturanGuru::firstOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'kkm' => (int) $data['nilai_kktp'],
                'semester' => 'Ganjil',
                'tahun_pelajaran' => $this->ctx->tahunAjaranAktif(),
            ]
        );
        if ((int) $pengaturan->kkm !== (int) $data['nilai_kktp']) {
            $pengaturan->update(['kkm' => (int) $data['nilai_kktp'], 'pembaruan' => now()]);
        }

        return redirect()
            ->route('guru.pm.kktp.index', ['tingkat' => $data['tingkat'], 'mapel' => $data['nama_mapel']])
            ->with('success', 'Interval KKTP berhasil disimpan dan disinkronkan ke KKM e-Rapor.');
    }

    public function simpanItem(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $this->scope->authorizeNotLockedBySupervisi($guru, $tenantId);
        $items = $request->input('items', []);

        if (! is_array($items)) {
            return back()->with('error', 'Data KKTP per TP tidak valid.');
        }

        $saved = 0;
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

            $this->scope->authorizeFase($guru, $tp->fase, $tenantId);
            $this->scope->authorizeMapel($guru, $tp->nama_mapel, $tenantId);

            $pendekatan = in_array((string) ($row['pendekatan'] ?? ''), array_keys(KktpItem::PENDEKATAN_LABELS), true)
                ? (string) $row['pendekatan']
                : 'deskripsi';

            KktpItem::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'tp_id' => $tp->id,
                ],
                [
                    'guru_id' => $guru->id,
                    'pendekatan' => $pendekatan,
                    'kriteria' => trim((string) ($row['kriteria'] ?? '')) ?: null,
                    'rubrik' => trim((string) ($row['rubrik'] ?? '')) ?: null,
                ]
            );
            $saved++;
        }

        return back()->with('success', "KKTP untuk {$saved} Tujuan Pembelajaran berhasil disimpan.");
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tingkat = max(1, min(12, (int) $request->query('tingkat', 1)));
        $this->scope->authorizeTingkat($guru, $tingkat, $tenantId);
        $fase = TujuanPembelajaranPm::faseUntukKelas($tingkat);
        $mapel = trim((string) $request->query('mapel', ''));
        if ($mapel !== '') {
            $this->scope->authorizeMapel($guru, $mapel, $tenantId);
        }

        $tpList = TujuanPembelajaranPm::query()
            ->with('kktpItem')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->where('fase', $fase)
            ->orderBy('nama_mapel')
            ->orderBy('urutan')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('KKTP');
        $sheet->fromArray(['No', 'Mata Pelajaran', 'Kode TP', 'Tujuan Pembelajaran', 'Pendekatan', 'Kriteria Ketercapaian', 'Rubrik'], null, 'A1');

        $r = 2;
        foreach ($tpList as $idx => $tp) {
            $sheet->fromArray([
                $idx + 1,
                $tp->nama_mapel,
                $tp->kode_tp,
                $tp->deskripsi,
                KktpItem::PENDEKATAN_LABELS[$tp->kktpItem?->pendekatan ?? 'deskripsi'] ?? 'Deskripsi Kriteria',
                $tp->kktpItem?->kriteria ?? '',
                $tp->kktpItem?->rubrik ?? '',
            ], null, "A{$r}");
            $r++;
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "kktp-kelas-{$tingkat}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function cetakPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tingkat = max(1, min(12, (int) $request->query('tingkat', 1)));
        $this->scope->authorizeTingkat($guru, $tingkat, $tenantId);
        $fase = TujuanPembelajaranPm::faseUntukKelas($tingkat);
        $mapel = trim((string) $request->query('mapel', ''));
        if ($mapel !== '') {
            $this->scope->authorizeMapel($guru, $mapel, $tenantId);
        }

        $interval = KktpInterval::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tingkat', $tingkat)
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->first() ?? new KktpInterval(['nilai_kktp' => 75, 'batas_bawah' => 60, 'batas_atas' => 88]);

        $tpList = TujuanPembelajaranPm::query()
            ->with('kktpItem')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->where('fase', $fase)
            ->orderBy('nama_mapel')
            ->orderBy('urutan')
            ->get();

        $pdf = Pdf::loadView('guru.pm.pdf.kktp', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'tingkat' => $tingkat,
            'fase' => $fase,
            'mapel' => $mapel ?: 'Semua Mata Pelajaran',
            'rentangPredikat' => $interval->rentangPredikat(),
            'tpList' => $tpList,
            'pendekatanLabels' => KktpItem::PENDEKATAN_LABELS,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("kktp-kelas-{$tingkat}.pdf");
    }
}
