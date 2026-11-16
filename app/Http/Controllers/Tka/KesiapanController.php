<?php

namespace App\Http\Controllers\Tka;

use App\Http\Controllers\Controller;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Tka\Kesiapan\Audit;
use App\Models\Tka\Kesiapan\Pemetaan;
use App\Models\Tka\Kesiapan\TindakLanjut;
use App\Services\Tka\Kesiapan\KesiapanAkses;
use App\Services\Tka\Kesiapan\KesiapanFilter;
use App\Services\Tka\Kesiapan\KesiapanPengaturanService;
use App\Services\Tka\Kesiapan\KesiapanPeriode;
use App\Services\Tka\Kesiapan\KesiapanService;
use App\Services\Tka\Kesiapan\RekomendasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pemantauan Kesiapan Siswa Menghadapi TKA — dasbor guru/kepsek/admin + profil siswa (guru).
 *
 * Otorisasi:
 *  - Route dibatasi middleware role di routes/web.php;
 *  - Query siswa & rombel SELALU disaring di server via KesiapanAkses (anti-IDOR, anti cross-tenant);
 *  - Hanya guru pemilik yang dapat melihat profil individual & menyunting tindak lanjut;
 *  - Kepsek & Admin hanya melihat dan mengekspor data agregat (dengan perlindungan kelompok kecil).
 */
class KesiapanController extends Controller
{
    public function __construct()
    {
        abort_unless(config('tka_kesiapan.aktif', true), 404);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless(KesiapanAkses::bolehAgregat($user), 403);

        $filter = KesiapanFilter::dariRequest($request);
        if ($filter->rombelId !== null) {
            abort_unless(KesiapanAkses::rombelDiizinkan($user, $filter->rombelId), 403);
        }

        $siswaQ = KesiapanAkses::siswaQuery($user)
            ->with('rombel:id,nama_rombel,tingkat')
            ->orderBy('kelas')
            ->orderBy('nama_peserta_didik');
        if ($filter->rombelId !== null) {
            $siswaQ->where('siswa.rombel_id', $filter->rombelId);
        }
        $siswaList = $siswaQ->get();

        $bolehProfil = KesiapanAkses::bolehProfilIndividu($user);
        $data = (new KesiapanService($user->tenant_id))->kelas($siswaList, $filter, ! $bolehProfil);

        return view('tka.kesiapan.index', [
            'peran' => $user->peran,
            'filter' => $filter,
            'data' => $data,
            'opsi' => $this->opsiFilter($user),
            'indikator' => (array) config('tka_kesiapan.indikator'),
            'bolehProfil' => $bolehProfil,
            'disclaimer' => config('tka_kesiapan.disclaimer'),
            'acuan' => config('tka_kesiapan.acuan_resmi'),
        ]);
    }

    public function siswa(Request $request, string $uuid)
    {
        $user = Auth::user();
        abort_unless(KesiapanAkses::bolehProfilIndividu($user), 403);
        abort_unless(Str::isUuid($uuid), 404);

        $siswa = KesiapanAkses::siswaUuid($user, $uuid);
        abort_if($siswa === null, 404);
        $this->authorize('viewProfil', [TindakLanjut::class, $siswa]);

        $filter = KesiapanFilter::dariRequest($request);
        $profil = (new KesiapanService($user->tenant_id))->profil($siswa, $filter);

        [$tahun, $semester] = $this->periodeRekomendasi($filter);
        $tindakLanjut = TindakLanjut::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_ajaran', $tahun)
            ->where('semester', $semester)
            ->with(['revisi.user:id,nama_lengkap'])
            ->get()
            ->keyBy('indikator_kode');

        return view('tka.kesiapan.siswa', [
            'filter' => $filter,
            'profil' => $profil,
            'siswa' => $siswa,
            'tindakLanjut' => $tindakLanjut,
            'periode' => ['tahun_ajaran' => $tahun, 'semester' => $semester],
            'opsi' => $this->opsiFilter($user),
            'indikator' => (array) config('tka_kesiapan.indikator'),
            'statusTindakLanjut' => TindakLanjut::STATUS,
            'disclaimer' => config('tka_kesiapan.disclaimer'),
            'acuan' => config('tka_kesiapan.acuan_resmi'),
        ]);
    }

    public function susun(Request $request, string $uuid, RekomendasiService $rekomendasi)
    {
        $user = Auth::user();
        abort_unless(KesiapanAkses::bolehProfilIndividu($user), 403);
        abort_unless(Str::isUuid($uuid), 404);

        $siswa = KesiapanAkses::siswaUuid($user, $uuid);
        abort_if($siswa === null, 404);

        $filter = KesiapanFilter::dariRequest($request);
        $profil = (new KesiapanService($user->tenant_id))->profil($siswa, $filter);
        [$tahun, $semester] = $this->periodeRekomendasi($filter);
        $baru = $rekomendasi->susun($siswa, $profil, $user, $tahun, $semester);

        return back()->with('success', $baru > 0
            ? "{$baru} saran tindak lanjut disusun dari bukti yang tersedia. Mohon tinjau dan sesuaikan."
            : 'Tidak ada saran baru: belum ada indikator dengan bukti cukup yang memerlukan dukungan/perhatian tren, atau saran sudah tersusun.');
    }

    public function perbarui(Request $request, string $tindakLanjut, RekomendasiService $rekomendasi)
    {
        $user = Auth::user();
        abort_unless(KesiapanAkses::bolehProfilIndividu($user), 403);
        abort_unless(Str::isUuid($tindakLanjut), 404);

        $tl = TindakLanjut::query()->where('uuid', $tindakLanjut)->firstOrFail();
        abort_if(KesiapanAkses::siswaQuery($user)->where('siswa.id', $tl->siswa_id)->doesntExist(), 404);
        $this->authorize('updateTindakLanjut', $tl);

        $valid = $request->validate([
            'status' => 'required|in:'.implode(',', TindakLanjut::STATUS),
            'rekomendasi' => 'nullable|string|max:2000',
            'catatan_guru' => 'nullable|string|max:1000',
            'alasan' => 'nullable|string|max:500',
        ]);

        $rekomendasi->perbarui($tl, $user, $valid);

        return back()->with('success', 'Tindak lanjut disimpan dan dicatat dalam riwayat revisi.');
    }

    public function ekspor(Request $request): StreamedResponse
    {
        $user = Auth::user();
        abort_unless(KesiapanAkses::bolehAgregat($user), 403);

        $filter = KesiapanFilter::dariRequest($request);
        if ($filter->rombelId !== null) {
            abort_unless(KesiapanAkses::rombelDiizinkan($user, $filter->rombelId), 403);
        }

        $siswaQ = KesiapanAkses::siswaQuery($user)->with('rombel:id,nama_rombel,tingkat')->orderBy('kelas')->orderBy('nama_peserta_didik');
        if ($filter->rombelId !== null) {
            $siswaQ->where('siswa.rombel_id', $filter->rombelId);
        }

        $bolehProfil = KesiapanAkses::bolehProfilIndividu($user);
        $data = (new KesiapanService($user->tenant_id))->kelas($siswaQ->get(), $filter, ! $bolehProfil);
        $indikator = (array) config('tka_kesiapan.indikator');

        if (! $bolehProfil) {
            return $this->eksporAgregat($data, $indikator);
        }

        return response()->streamDownload(function () use ($data, $indikator) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Catatan', (string) config('tka_kesiapan.disclaimer')]);
            fputcsv($out, array_merge(['Nama', 'Kelas', 'Status data'], collect($indikator)->flatMap(fn ($d) => [
                $d['nama'].' — status bukti',
                $d['nama'].' — kecenderungan (estimasi internal)',
                $d['nama'].' — keyakinan',
                $d['nama'].' — tren tervalidasi',
            ])->all()));
            foreach ($data['baris'] as $p) {
                $row = [self::aman($p['siswa']->nama_peserta_didik), self::aman((string) $p['siswa']->kelas), $p['status_data']];
                foreach (array_keys($indikator) as $k) {
                    $i = $p['indikator'][$k];
                    $row[] = $i['bukti_status'] === 'cukup' ? 'cukup' : 'Data belum cukup dinilai';
                    $row[] = $i['kecenderungan'] ?? '';
                    $row[] = $i['keyakinan'] ?? '';
                    $row[] = $i['tren']['arah'];
                }
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'kesiapan-tka-kelas-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Ekspor agregat aman (tanpa data per individu) untuk Kepsek & Admin. */
    private function eksporAgregat(array $data, array $indikator): StreamedResponse
    {
        return response()->streamDownload(function () use ($data, $indikator) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Catatan', (string) config('tka_kesiapan.disclaimer')]);

            if (! empty($data['agregat_terlindungi'])) {
                fputcsv($out, ['Privasi', 'Rincian agregat disembunyikan karena jumlah siswa pada filter aktif kurang dari '.$data['min_kelompok'].' siswa.']);
                fclose($out);

                return;
            }

            fputcsv($out, array_merge(
                ['Kelas / Rombel', 'Jumlah Siswa', 'Data Memadai', 'Data Belum Cukup'],
                collect($indikator)->flatMap(fn ($d) => [
                    $d['nama'].' — perlu dukungan',
                    $d['nama'].' — berkembang',
                    $d['nama'].' — cukup mantap',
                    $d['nama'].' — belum cukup data',
                ])->all()
            ));

            foreach ($data['agregat_rombel'] as $r) {
                if (! empty($r['privasi_terlindungi'])) {
                    fputcsv($out, [
                        self::aman($r['kelas']),
                        $r['jumlah_siswa'],
                        'Disembunyikan (kelompok < '.$data['min_kelompok'].' siswa)',
                        'Disembunyikan',
                    ]);

                    continue;
                }
                $row = [self::aman($r['kelas']), $r['jumlah_siswa'], $r['memadai'], $r['belum_cukup']];
                foreach (array_keys($indikator) as $k) {
                    $c = $r['per_indikator'][$k] ?? null;
                    $row[] = $c ? $c['perlu_dukungan'] : '-';
                    $row[] = $c ? $c['berkembang'] : '-';
                    $row[] = $c ? $c['cukup_mantap'] : '-';
                    $row[] = $c ? $c['belum_cukup'] : '-';
                }
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'kesiapan-tka-agregat-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Ringkasan ramah untuk siswa & orang tua (tanpa perbandingan teman sekelas atau peringkat). */
    public function mandiri(Request $request)
    {
        $user = Auth::user();
        abort_unless(in_array($user->peran, ['siswa', 'ortu'], true), 403);

        $anakList = KesiapanAkses::siswaQuery($user)->orderBy('nama_peserta_didik')->get();
        $uuid = trim((string) $request->query('siswa_uuid', ''));
        $terpilih = $uuid !== '' ? $anakList->firstWhere('uuid', $uuid) : $anakList->first();
        if ($uuid !== '' && $terpilih === null) {
            abort(403);
        }

        $filter = KesiapanFilter::dariRequest($request);
        $profil = $terpilih ? (new KesiapanService($user->tenant_id))->profil($terpilih, $filter) : null;
        $tindakLanjut = $terpilih
            ? TindakLanjut::query()
                ->where('siswa_id', $terpilih->id)
                ->whereIn('status', ['disarankan', 'ditinjau', 'berjalan', 'selesai'])
                ->get()
                ->keyBy('indikator_kode')
            : collect();

        return view('tka.kesiapan.mandiri', [
            'peran' => $user->peran,
            'anakList' => $anakList,
            'siswa' => $terpilih,
            'profil' => $profil,
            'tindakLanjut' => $tindakLanjut,
            'indikator' => (array) config('tka_kesiapan.indikator'),
            'disclaimer' => config('tka_kesiapan.disclaimer'),
            'acuan' => config('tka_kesiapan.acuan_resmi'),
        ]);
    }

    // ── pengaturan admin ─────────────────────────────────────────────────

    public function pengaturan()
    {
        $user = Auth::user();
        abort_unless($user->peran === 'admin', 403);

        return view('tka.kesiapan.pengaturan', [
            'aturan' => KesiapanService::aturanTenant($user->tenant_id),
            'batas' => KesiapanPengaturanService::BATAS,
            'pemetaan' => Pemetaan::query()->withoutGlobalScopes()
                ->when($user->tenant_id !== null, fn ($q) => $q->where('tenant_id', $user->tenant_id), fn ($q) => $q->whereNull('tenant_id'))
                ->orderBy('label_sumber')->get(),
            'audit' => Audit::query()->withoutGlobalScopes()
                ->when($user->tenant_id !== null, fn ($q) => $q->where('tenant_id', $user->tenant_id), fn ($q) => $q->whereNull('tenant_id'))
                ->with('user:id,nama_lengkap')->latest('id')->limit(25)->get(),
            'indikator' => (array) config('tka_kesiapan.indikator'),
            'disclaimer' => config('tka_kesiapan.disclaimer'),
            'acuan' => config('tka_kesiapan.acuan_resmi'),
        ]);
    }

    public function simpanAturan(Request $request, KesiapanPengaturanService $svc)
    {
        $user = Auth::user();
        abort_unless($user->peran === 'admin', 403);

        $request->validate(['alasan' => 'nullable|string|max:500']);

        try {
            $svc->simpanAturan(
                $user,
                $request->only(array_keys(KesiapanPengaturanService::BATAS)),
                $request->input('alasan')
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['aturan' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Aturan pemantauan kesiapan TKA disimpan dan dicatat ke log audit.');
    }

    public function simpanPemetaan(Request $request, KesiapanPengaturanService $svc)
    {
        $user = Auth::user();
        abort_unless($user->peran === 'admin', 403);

        $valid = $request->validate([
            'label_sumber' => 'required|string|max:120',
            'indikator_kode' => 'required|string|max:40',
            'alasan' => 'nullable|string|max:500',
        ]);

        try {
            $svc->simpanPemetaan($user, $valid['label_sumber'], $valid['indikator_kode'], $valid['alasan'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['pemetaan' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Pemetaan mata pelajaran/label sumber berhasil disimpan.');
    }

    public function hapusPemetaan(Request $request, int $id, KesiapanPengaturanService $svc)
    {
        $user = Auth::user();
        abort_unless($user->peran === 'admin', 403);

        $valid = $request->validate(['alasan' => 'nullable|string|max:500']);
        $svc->hapusPemetaan($user, $id, $valid['alasan'] ?? null);

        return back()->with('success', 'Pemetaan berhasil dihapus.');
    }

    // ── util ─────────────────────────────────────────────────────────────

    /** Cegah CSV injection. */
    private static function aman(string $v): string
    {
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }

    /** @return array{0: string, 1: string} */
    private function periodeRekomendasi(KesiapanFilter $f): array
    {
        $sekarang = KesiapanPeriode::dariTanggal(now());

        return [$f->tahunAjaran ?? $sekarang['tahun_ajaran'], $f->semester ?? $sekarang['semester']];
    }

    /** @return array<string, mixed> */
    private function opsiFilter($user): array
    {
        return [
            'tahun_ajaran' => Semester::query()->orderByDesc('tahun_ajaran')->pluck('tahun_ajaran')->unique()->values()->all(),
            'rombel' => Rombel::query()
                ->when($user->peran === 'guru', fn ($q) => $q->where('guru_id', $user->id))
                ->when($user->tenant_id !== null, fn ($q) => $q->where('tenant_id', $user->tenant_id))
                ->orderBy('nama_rombel')->get(['id', 'nama_rombel', 'tingkat']),
            'sumber' => (array) config('tka_kesiapan.sumber'),
        ];
    }
}
