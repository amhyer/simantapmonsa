<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\AlokasiWaktu;
use App\Models\User;
use App\Services\PembelajaranMendalam\GuruScopeService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use App\Support\SchoolLevel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AlokasiWaktuController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly GuruScopeService $scope,
    ) {}

    public function simpanKonteks(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'in:1,2'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $this->scope->authorizeTingkat($guru, (int) $data['tingkat'], $tenantId);
        $this->ctx->setContext($data['tahun_ajaran'], (int) $data['semester'], (int) $data['tingkat']);

        return back()->with('success', "Konteks aktif diubah ke Kelas {$data['tingkat']} · {$data['tahun_ajaran']} Semester {$data['semester']}.");
    }

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = $this->ctx->getContext($request)['tahun_ajaran'];

        $mapelList = $this->ctx->daftarMapel();
        $records = AlokasiWaktu::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->get();

        // Gabungkan mapel yang sudah ada di DB agar tidak hilang
        $namaMapelSet = $mapelList->pluck('nama')->merge($records->pluck('nama_mapel'))->unique()->values();

        $tingkatList = SchoolLevel::tingkatForJenjang(SchoolLevel::current($tenantId));

        $matrix = [];
        foreach ($namaMapelSet as $namaMapel) {
            foreach ($tingkatList as $t) {
                $matrix[$namaMapel][$t] = ['intra' => 0, 'ko' => 0];
            }
        }
        foreach ($records as $row) {
            if (in_array((int) $row->tingkat, $tingkatList, true)) {
                $matrix[$row->nama_mapel][(int) $row->tingkat] = [
                    'intra' => (int) $row->jp_intra,
                    'ko' => (int) $row->jp_kokurikuler,
                ];
            }
        }

        $ringkasanTingkat = [];
        foreach ($tingkatList as $t) {
            $intra = 0;
            $ko = 0;
            foreach ($matrix as $perTingkat) {
                $intra += $perTingkat[$t]['intra'] ?? 0;
                $ko += $perTingkat[$t]['ko'] ?? 0;
            }
            $minggu = AlokasiWaktu::mingguPerTahun($t);
            $ringkasanTingkat[$t] = [
                'intra' => $intra,
                'ko' => $ko,
                'total_minggu' => $intra + $ko,
                'total_tahun' => ($intra + $ko) * $minggu,
                'minggu_efektif' => $minggu,
            ];
        }

        $canEdit = $guru->peran === 'admin';

        return view('guru.pm.alokasi.index', [
            'tahunAjaran' => $tahunAjaran,
            'tingkatList' => $tingkatList,
            'namaMapelList' => $namaMapelSet,
            'matrix' => $matrix,
            'ringkasanTingkat' => $ringkasanTingkat,
            'canEdit' => $canEdit,
            'routePrefix' => $canEdit ? 'admin.pm' : 'guru.pm',
            'layout' => $canEdit ? 'layouts.admin-with-tabs' : 'layouts.app',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        if ($guru->peran !== 'admin') {
            abort(403, 'Hanya Admin/Operator sekolah yang dapat mengubah master Alokasi Waktu.');
        }
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = trim((string) $request->input('tahun_ajaran', $this->ctx->tahunAjaranAktif()));
        $alokasi = $request->input('a', []);

        if (! is_array($alokasi)) {
            return back()->with('error', 'Format data alokasi waktu tidak valid.');
        }

        $mapelByName = $this->ctx->daftarMapel()->keyBy('nama');
        $tingkatList = SchoolLevel::tingkatForJenjang(SchoolLevel::current($tenantId));

        foreach ($alokasi as $namaMapel => $perTingkat) {
            $namaMapel = trim((string) $namaMapel);
            if ($namaMapel === '' || ! is_array($perTingkat)) {
                continue;
            }
            $mapelId = $mapelByName->get($namaMapel)?->id;

            foreach ($tingkatList as $t) {
                $intra = max(0, min(30, (int) ($perTingkat[$t]['intra'] ?? 0)));
                $ko = max(0, min(20, (int) ($perTingkat[$t]['ko'] ?? 0)));

                AlokasiWaktu::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'nama_mapel' => $namaMapel,
                        'tingkat' => $t,
                        'tahun_ajaran' => $tahunAjaran,
                    ],
                    [
                        'mata_pelajaran_id' => $mapelId,
                        'jp_intra' => $intra,
                        'jp_kokurikuler' => $ko,
                        'updated_by' => $guru->id,
                    ]
                );
            }
        }

        return redirect()
            ->route('admin.pm.alokasi.index', ['tahun_ajaran' => $tahunAjaran])
            ->with('success', 'Alokasi waktu struktur kurikulum berhasil disimpan.');
    }

    public function isiStandar(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        if ($guru->peran !== 'admin') {
            abort(403, 'Hanya Admin/Operator sekolah yang dapat mengubah master Alokasi Waktu.');
        }
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = trim((string) $request->input('tahun_ajaran', $this->ctx->tahunAjaranAktif()));
        $daftarMapel = $this->ctx->daftarMapel();
        $jenjang = SchoolLevel::current($tenantId);

        foreach (AlokasiWaktu::standarUntukJenjang($jenjang) as $namaStandar => $perTingkat) {
            // Cocokkan dengan nama mapel aktual sekolah (misal: "Matematika" -> "Matematika (Umum)")
            $matched = $daftarMapel->first(function ($m) use ($namaStandar): bool {
                $a = mb_strtolower($m->nama);
                $b = mb_strtolower($namaStandar);

                return $a === $b || str_contains($a, $b) || str_contains($b, $a);
            });

            $namaTarget = $matched ? $matched->nama : $namaStandar;
            $mapelId = $matched?->id;

            foreach ($perTingkat as $tingkat => [$intra, $ko]) {
                AlokasiWaktu::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'nama_mapel' => $namaTarget,
                        'tingkat' => $tingkat,
                        'tahun_ajaran' => $tahunAjaran,
                    ],
                    [
                        'mata_pelajaran_id' => $mapelId,
                        'jp_intra' => $intra,
                        'jp_kokurikuler' => $ko,
                        'updated_by' => $guru->id,
                    ]
                );
            }
        }

        return redirect()
            ->route('admin.pm.alokasi.index', ['tahun_ajaran' => $tahunAjaran])
            ->with('success', 'Alokasi waktu berhasil diisi sesuai standar Permendikdasmen No. 13 Tahun 2025.');
    }

    public function cetakPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $tingkatFilter = $request->query('tingkat') ? (int) $request->query('tingkat') : null;

        $records = AlokasiWaktu::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->when($tingkatFilter !== null, fn ($q) => $q->where('tingkat', $tingkatFilter))
            ->orderBy('nama_mapel')
            ->orderBy('tingkat')
            ->get();

        $tingkatList = $tingkatFilter
            ? [$tingkatFilter]
            : SchoolLevel::tingkatForJenjang(SchoolLevel::current($tenantId));
        $namaMapelList = $records->pluck('nama_mapel')->unique()->values();
        $matrix = [];
        foreach ($records as $row) {
            $matrix[$row->nama_mapel][$row->tingkat] = [
                'intra' => (int) $row->jp_intra,
                'ko' => (int) $row->jp_kokurikuler,
            ];
        }

        $pdf = Pdf::loadView('guru.pm.pdf.alokasi', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'tahunAjaran' => $tahunAjaran,
            'tingkatList' => $tingkatList,
            'namaMapelList' => $namaMapelList,
            'matrix' => $matrix,
        ])->setPaper('a4', $tingkatFilter ? 'portrait' : 'landscape');

        $suffix = $tingkatFilter ? "kelas-{$tingkatFilter}" : 'semua-kelas';

        return $pdf->stream("alokasi-waktu-{$suffix}.pdf");
    }
}
