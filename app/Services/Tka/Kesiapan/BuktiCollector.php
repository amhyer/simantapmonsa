<?php

namespace App\Services\Tka\Kesiapan;

use App\Models\CbtResult;
use App\Models\Exam\Attempt;
use App\Models\KenaikanKelas;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tka\AdaptiveSimulation;
use App\Models\Tka\StudentAssessment;
use App\Models\Tryout\Result as TryoutResult;
use App\Modules\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengumpul bukti kesiapan — HANYA MEMBACA (read-only) dari tabel sumber.
 *
 * Satu pemanggilan = query terkelompok (O(1) per sumber) untuk SELURUH daftar siswa (tanpa N+1).
 * Menangani:
 *  - Isolasi tenant eksplisit (termasuk mode single-school saat ada tenant di DB);
 *  - Deduplikasi lintas sumber (kuis->nilai, tka_erapor_sync_logs, attempt ulang, legacy vs exam_*);
 *  - Penolakan ujian belum final / dibatalkan / draft / skala tidak valid / nilai kosong;
 *  - Resolusi jenjang historis HANYA dari bukti periode tervalidasi (TANPA tebak kurangi tahun).
 */
class BuktiCollector
{
    private PemetaanResolver $resolver;

    private bool $hasTenants;

    public function __construct(private readonly ?int $tenantId = null, ?PemetaanResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new PemetaanResolver($tenantId);
        $this->hasTenants = Schema::hasTable('tenants') && Tenant::hasAnyTenant();
    }

    /**
     * Antarmuka ringkas untuk pemanggil yang hanya membutuhkan koleksi bukti.
     *
     * @param  Collection<int, Siswa>  $siswaList
     * @return Collection<int, array<string, mixed>>
     */
    public function kumpulkan(Collection $siswaList): Collection
    {
        return $this->collectBukti($siswaList)['bukti'];
    }

    /**
     * @param  Collection<int, Siswa>  $siswaList
     * @return array{
     *   bukti: Collection<int, array<string, mixed>>,
     *   diagnostik: array<int, array<string, int>>,
     *   histori_kondisi: array<int, array<string, mixed>>
     * }
     */
    public function collectBukti(Collection $siswaList): array
    {
        $siswaList = $siswaList->keyBy('id');
        $ids = $siswaList->keys()->all();

        $diag = [];
        $kondisi = [];
        foreach ($ids as $id) {
            $diag[$id] = [
                'duplikat_kuis_nilai' => 0,
                'duplikat_tka_sync' => 0,
                'duplikat_attempt_ulang' => 0,
                'ujian_belum_final' => 0,
                'ujian_dibatalkan_atau_draft' => 0,
                'skala_atau_nilai_kosong' => 0,
                'kalender_tidak_valid' => 0,
            ];
            $kondisi[$id] = [
                'tinggal_kelas' => [],
                'akselerasi' => [],
                'pindah_rombel' => [],
                'kalender_tidak_konsisten' => [],
                'status_siswa' => $siswaList[$id]->status_siswa,
                'catatan_mutasi' => null,
            ];
        }

        if ($ids === []) {
            return ['bukti' => collect(), 'diagnostik' => $diag, 'histori_kondisi' => $kondisi];
        }

        // Muat jejak sinkronisasi TKA -> e-Rapor/Nilai agar tidak terhitung ganda.
        [$syncNilaiIds, $syncEraporRows] = $this->muatSyncLogs($ids);

        // Muat relasi semester & rombel siswa sekali saja (tanpa N+1) untuk validasi periode aktif.
        $siswaSemIds = $siswaList->pluck('semester_id')->filter()->unique()->all();
        $siswaRombelIds = $siswaList->pluck('rombel_id')->filter()->unique()->all();
        $semestersMap = $siswaSemIds === [] ? collect() : Semester::query()->whereIn('id', $siswaSemIds)->get()->keyBy('id');
        $rombelsMap = $siswaRombelIds === [] ? collect() : DB::table('rombel')->whereIn('id', $siswaRombelIds)->get(['id', 'nama_rombel', 'tingkat', 'semester_id'])->keyBy('id');

        [$ujianBukti, $seenLegacy] = $this->ujianEngine($ids, $siswaList, $diag);

        $mentah = collect()
            ->merge($this->nilaiHarian($ids, $siswaList, $syncNilaiIds, $diag))
            ->merge($this->kuis($ids, $siswaList, $diag))
            ->merge($this->erapor($ids, $siswaList, $syncEraporRows, $diag, $kondisi))
            ->merge($ujianBukti)
            ->merge($this->cbtLegacy($ids, $siswaList, $seenLegacy, $diag))
            ->merge($this->tryoutLegacy($ids, $siswaList, $seenLegacy, $diag))
            ->merge($this->asesmenTka($ids, $siswaList))
            ->merge($this->simulasiTka($ids, $siswaList))
            ->unique('id')
            ->values();

        // Tahap 4: Validasi jenjang historis tanpa menebak dari kelas saat ini dikurangi tahun.
        [$bukti, $kondisi] = $this->validasiJenjangHistoris($mentah, $siswaList, $semestersMap, $rombelsMap, $kondisi);

        return ['bukti' => $bukti, 'diagnostik' => $diag, 'histori_kondisi' => $kondisi];
    }

    private function scopeTenant($query, string $kolom = 'tenant_id')
    {
        if ($this->tenantId !== null) {
            return $query->where($kolom, $this->tenantId);
        }

        return $this->hasTenants ? $query->whereNull($kolom) : $query;
    }

    /** @return array{0: array<int, true>, 1: Collection<int, Collection<int, object>>} */
    private function muatSyncLogs(array $ids): array
    {
        if (! Schema::hasTable('tka_erapor_sync_logs')) {
            return [[], collect()];
        }

        $rows = $this->scopeTenant(DB::table('tka_erapor_sync_logs'))
            ->whereIn('siswa_id', $ids)
            ->get(['nilai_id', 'nilai_erapor_id', 'skor_tka_konversi']);

        $nilaiIds = [];
        foreach ($rows->pluck('nilai_id')->filter() as $nid) {
            $nilaiIds[(int) $nid] = true;
        }
        $eraporRows = $rows->whereNotNull('nilai_erapor_id')->groupBy('nilai_erapor_id');

        return [$nilaiIds, $eraporRows];
    }

    // ── sumber ─────────────────────────────────────────────────────────────

    private function nilaiHarian(array $ids, Collection $siswa, array $syncNilaiIds, array &$diag): Collection
    {
        $rows = $this->scopeTenant(Nilai::query()->withoutGlobalScopes()->whereNull('deleted_at'))
            ->whereIn('siswa_id', $ids)
            ->get(['id', 'siswa_id', 'kuis_id', 'tanggal', 'jenis', 'judul_penilaian', 'mata_pelajaran', 'nilai', 'rekaman']);

        return $rows->map(function ($r) use ($siswa, $syncNilaiIds, &$diag) {
            $sid = (int) $r->siswa_id;
            if ($r->kuis_id !== null) {
                $diag[$sid]['duplikat_kuis_nilai']++;

                return null;
            }
            $rekaman = is_array($r->rekaman) ? $r->rekaman : [];
            if (isset($syncNilaiIds[(int) $r->id]) || ($rekaman['sumber'] ?? null) === 'tka_kognitif') {
                $diag[$sid]['duplikat_tka_sync']++;

                return null;
            }
            if ($r->nilai === null || ! is_numeric($r->nilai) || (float) $r->nilai < 0 || (float) $r->nilai > 100) {
                $diag[$sid]['skala_atau_nilai_kosong']++;

                return null;
            }

            return $this->baris(
                $siswa[$sid], 'nilai_harian', 'nilai:'.$r->id, $r->jenis.': '.$r->judul_penilaian,
                Carbon::parse($r->tanggal), null, $r->mata_pelajaran, $r->mata_pelajaran,
                (float) $r->nilai, 100.0, (string) $r->nilai.' / 100', null
            );
        })->filter()->values();
    }

    private function kuis(array $ids, Collection $siswa, array &$diag): Collection
    {
        $rows = $this->scopeTenant(
            DB::table('hasil_kuis as h')->join('kuis as k', 'k.id', '=', 'h.kuis_id')->whereNull('k.deleted_at'),
            'h.tenant_id'
        )->whereIn('h.siswa_id', $ids)
            ->get(['h.id', 'h.siswa_id', 'h.waktu', 'h.benar', 'h.total', 'k.judul', 'k.mata_pelajaran', 'k.kelas']);

        return $rows->map(function ($r) use ($siswa, &$diag) {
            $sid = (int) $r->siswa_id;
            if ((int) $r->total <= 0 || (int) $r->benar < 0 || (int) $r->benar > (int) $r->total) {
                $diag[$sid]['skala_atau_nilai_kosong']++;

                return null;
            }

            return $this->baris(
                $siswa[$sid], 'kuis', 'kuis:'.$r->id, 'Kuis: '.$r->judul,
                Carbon::parse($r->waktu), $r->kelas, $r->mata_pelajaran, $r->mata_pelajaran,
                ($r->benar / $r->total) * 100.0, 100.0, $r->benar.' / '.$r->total.' soal', null
            );
        })->filter()->values();
    }

    private function erapor(array $ids, Collection $siswa, Collection $syncEraporRows, array &$diag, array &$kondisi): Collection
    {
        $rows = $this->scopeTenant(NilaiErapot::query()->withoutGlobalScopes())
            ->whereIn('siswa_id', $ids)
            ->get(['id', 'siswa_id', 'mata_pelajaran', 'kelas', 'semester', 'tahun_ajaran', 'nilai_formatif', 'nilai_sumatif', 'nilai_sumatif_akhir', 'nilai_akhir']);

        return $rows->map(function ($r) use ($siswa, $syncEraporRows, &$diag, &$kondisi) {
            $sid = (int) $r->siswa_id;
            $kosong = (float) $r->nilai_akhir <= 0 && (float) $r->nilai_formatif <= 0
                && (float) $r->nilai_sumatif <= 0 && (float) $r->nilai_sumatif_akhir <= 0;
            if ($kosong) {
                $diag[$sid]['skala_atau_nilai_kosong']++;

                return null;
            }

            $catatan = null;
            if ($syncEraporRows->has($r->id)) {
                $konversi = $syncEraporRows->get($r->id)->pluck('skor_tka_konversi')->map(fn ($v) => (float) $v)->all();
                $komponen = array_filter([
                    (float) $r->nilai_formatif,
                    (float) $r->nilai_sumatif,
                    (float) $r->nilai_sumatif_akhir,
                ], fn ($v) => $v > 0);
                $semuaCocokSync = $komponen !== [] && collect($komponen)->every(
                    fn ($v) => collect($konversi)->contains(fn ($k) => abs($v - $k) <= 0.05)
                );
                if ($semuaCocokSync) {
                    $diag[$sid]['duplikat_tka_sync']++;

                    return null;
                }
                $catatan = 'Sebagian komponen berasal dari sinkronisasi TKA (tka_erapor_sync_logs); perlu tinjauan guru.';
            }

            $tahunNorm = KesiapanPeriode::normalisasiTahunAjaran((string) $r->tahun_ajaran);
            $tahunMulai = KesiapanPeriode::tahunMulai((string) $r->tahun_ajaran);
            $semester = KesiapanPeriode::normalisasiSemester($r->semester);

            if ($tahunNorm === null || $tahunMulai === null || $semester === null) {
                $diag[$sid]['kalender_tidak_valid']++;
                $kondisi[$sid]['kalender_tidak_konsisten'][] = "e-Rapor {$r->mata_pelajaran}: periode '{$r->tahun_ajaran} {$r->semester}' tidak konsisten.";

                return null;
            }

            $tanggal = $semester === 'Ganjil' ? Carbon::create($tahunMulai, 12, 31) : Carbon::create($tahunMulai + 1, 6, 30);

            return $this->baris(
                $siswa[$sid], 'erapor', 'erapor:'.$r->id, 'e-Rapor '.$r->mata_pelajaran.' '.$tahunNorm.' '.$semester,
                $tanggal, $r->kelas, $r->mata_pelajaran, $r->mata_pelajaran, (float) $r->nilai_akhir, 100.0,
                (string) $r->nilai_akhir.' / 100', $catatan, $tahunNorm, $semester
            );
        })->filter()->values();
    }

    /** @return array{0: Collection<int, array<string, mixed>>, 1: array<string, true>} */
    private function ujianEngine(array $ids, Collection $siswa, array &$diag): array
    {
        $attempts = $this->scopeTenant(Attempt::query()->withoutGlobalScopes(), 'exam_attempts.tenant_id')
            ->whereIn('siswa_id', $ids)
            ->with(['result', 'session.bank', 'session.rombel', 'session.semester'])
            ->get();

        $seenLegacy = [];
        $sah = collect();

        foreach ($attempts as $a) {
            if ($a->sumber_tabel && $a->sumber_id) {
                $seenLegacy[$a->sumber_tabel.':'.$a->sumber_id] = true;
            }

            $sid = (int) $a->siswa_id;
            $session = $a->session;
            $bank = $session?->bank;
            if (! $session || $session->deleted_at !== null || ! $bank || $bank->deleted_at !== null) {
                continue;
            }

            $statusSesi = strtolower((string) $session->status);
            if (in_array($statusSesi, ['draft', 'dibatalkan', 'cancelled', 'batal'], true)) {
                $diag[$sid]['ujian_dibatalkan_atau_draft']++;

                continue;
            }

            if (! in_array($a->status, ['dikumpulkan', 'dinilai'], true) || $a->selesai_at === null) {
                $diag[$sid]['ujian_belum_final']++;

                continue;
            }

            $sah->push($a);
        }

        // Ambil attempt terakhir per (sesi, siswa).
        $dikelompokkan = $sah->groupBy(fn ($a) => $a->session_id.'|'.$a->siswa_id);
        $terbaru = collect();
        foreach ($dikelompokkan as $grup) {
            $urut = $grup->sortBy([
                ['attempt_number', 'desc'],
                ['selesai_at', 'desc'],
                ['id', 'desc'],
            ])->values();
            $terbaru->push($urut->first());
            if ($urut->count() > 1) {
                $sid = (int) $urut->first()->siswa_id;
                $diag[$sid]['duplikat_attempt_ulang'] += ($urut->count() - 1);
            }
        }

        $mapelIds = $terbaru->flatMap(function ($a) {
            return collect($a->result?->detail_per_section ?? [])->pluck('mata_pelajaran_id');
        })->filter()->unique()->all();
        $mapelNamaById = $mapelIds === [] ? collect() : MataPelajaran::query()->whereIn('id', $mapelIds)->pluck('nama', 'id');

        $hasil = collect();
        foreach ($terbaru as $a) {
            $sid = (int) $a->siswa_id;
            $res = $a->result;
            $tgl = $a->selesai_at ?? $a->mulai_at ?? $a->created_at;
            $sem = $a->session->semester;
            $tahun = $sem?->tahun_ajaran;
            $semNama = KesiapanPeriode::normalisasiSemester($sem?->nama_semester);
            $kelasSesi = $a->session->rombel?->tingkat !== null
                ? (string) $a->session->rombel->tingkat
                : ($a->session->kelas ?: $a->session->rombel?->nama_rombel);

            $detail = is_array($res?->detail_per_section) ? $res->detail_per_section : [];
            $sectionsValid = collect($detail)->values()->filter(function ($sec) use ($a) {
                $maksSec = (float) ($sec['maksimal'] ?? 0);
                if ($maksSec <= 0) {
                    return false;
                }
                $nama = trim((string) ($sec['nama_mapel'] ?? ''));

                return ($nama !== '' && strtolower($nama) !== 'umum')
                    || ! empty($sec['mata_pelajaran_id'])
                    || $a->session->bank?->mata_pelajaran === null;
            });

            if ($sectionsValid->isNotEmpty()) {
                foreach ($sectionsValid as $idx => $sec) {
                    $maksSec = (float) ($sec['maksimal'] ?? 0);
                    $skorSec = (float) ($sec['skor'] ?? 0);
                    $namaMapel = ! empty($sec['mata_pelajaran_id']) && $mapelNamaById->has($sec['mata_pelajaran_id'])
                        ? (string) $mapelNamaById->get($sec['mata_pelajaran_id'])
                        : (string) ($sec['nama_mapel'] ?? ($a->session->bank?->mata_pelajaran ?? ''));
                    if (strtolower(trim($namaMapel)) === 'umum' && $a->session->bank?->mata_pelajaran) {
                        $namaMapel = (string) $a->session->bank->mata_pelajaran;
                    }

                    $hasil->push($this->baris(
                        $siswa[$sid], 'ujian', 'exam:'.$a->id.':s'.$idx,
                        ($a->session->nama ?: 'Ujian').($namaMapel !== '' ? ' — '.$namaMapel : ''),
                        Carbon::parse($tgl), $kelasSesi, $namaMapel !== '' ? $namaMapel : null, $namaMapel,
                        ($skorSec / $maksSec) * 100.0, 100.0, round($skorSec, 2).' / '.round($maksSec, 2),
                        null, $tahun, $semNama
                    ));
                }

                continue;
            }

            $maks = (float) ($res?->nilai_maksimal ?? $a->nilai_maksimal ?? 0);
            $total = $res?->nilai_total ?? $a->nilai_total;
            if ($maks <= 0 || $total === null) {
                $diag[$sid]['ujian_belum_final']++;

                continue;
            }

            $mapel = $a->session->bank?->mata_pelajaran;
            $hasil->push($this->baris(
                $siswa[$sid], 'ujian', 'exam:'.$a->id, ($a->session->nama ?: 'Ujian'),
                Carbon::parse($tgl), $kelasSesi, $mapel, $mapel, ((float) $total / $maks) * 100.0, 100.0,
                $total.' / '.$maks, null, $tahun, $semNama
            ));
        }

        return [$hasil, $seenLegacy];
    }

    private function cbtLegacy(array $ids, Collection $siswa, array $seenLegacy, array &$diag): Collection
    {
        if (! Schema::hasTable('cbt_results')) {
            return collect();
        }

        $rows = $this->scopeTenant(CbtResult::query()->withoutGlobalScopes(), 'cbt_results.tenant_id')
            ->whereIn('siswa_id', $ids)
            ->with('session.bank')
            ->get();

        return $rows->map(function ($cr) use ($siswa, $seenLegacy, &$diag) {
            if (isset($seenLegacy['cbt_results:'.$cr->id])) {
                return null;
            }
            $sid = (int) $cr->siswa_id;
            $session = $cr->session;
            if (! $session) {
                return null;
            }
            if (in_array(strtolower((string) $session->status), ['draft', 'dibatalkan', 'cancelled', 'batal'], true)) {
                $diag[$sid]['ujian_dibatalkan_atau_draft']++;

                return null;
            }
            if ($cr->submitted_at === null || $cr->score === null) {
                $diag[$sid]['ujian_belum_final']++;

                return null;
            }

            $mapel = $session->bank?->subject;

            return $this->baris(
                $siswa[$sid], 'ujian', 'cbt:'.$cr->id, ($session->title ?: 'CBT'),
                Carbon::parse($cr->submitted_at), $session->kelas, $mapel, $mapel,
                (float) $cr->score, 100.0, $cr->score.' / 100', 'Dibaca dari hasil CBT legacy.'
            );
        })->filter()->values();
    }

    private function tryoutLegacy(array $ids, Collection $siswa, array $seenLegacy, array &$diag): Collection
    {
        if (! Schema::hasTable('tryout_results')) {
            return collect();
        }

        $rows = $this->scopeTenant(TryoutResult::query()->withoutGlobalScopes(), 'tryout_results.tenant_id')
            ->whereIn('siswa_id', $ids)
            ->with(['session.package', 'session.rombel', 'session.semester'])
            ->get();

        $sah = collect();
        foreach ($rows as $tr) {
            if (isset($seenLegacy['tryout_results:'.$tr->id])) {
                continue;
            }
            $sid = (int) $tr->siswa_id;
            $session = $tr->session;
            if (! $session || $session->deleted_at !== null) {
                continue;
            }
            if (in_array(strtolower((string) $session->status), ['draft', 'dibatalkan', 'cancelled', 'batal'], true)) {
                $diag[$sid]['ujian_dibatalkan_atau_draft']++;

                continue;
            }
            if ($tr->waktu_selesai === null) {
                $diag[$sid]['ujian_belum_final']++;

                continue;
            }
            $sah->push($tr);
        }

        $terbaru = collect();
        foreach ($sah->groupBy(fn ($tr) => $tr->tryout_session_id.'|'.$tr->siswa_id) as $grup) {
            $urut = $grup->sortBy([['attempt_number', 'desc'], ['waktu_selesai', 'desc'], ['id', 'desc']])->values();
            $terbaru->push($urut->first());
            if ($urut->count() > 1) {
                $diag[(int) $urut->first()->siswa_id]['duplikat_attempt_ulang'] += ($urut->count() - 1);
            }
        }

        $hasil = collect();
        foreach ($terbaru as $tr) {
            $sid = (int) $tr->siswa_id;
            $session = $tr->session;
            $sem = $session->semester;
            $kelasSesi = $session->rombel?->tingkat !== null
                ? (string) $session->rombel->tingkat
                : ($session->kelas ?: $session->rombel?->nama_rombel);
            $tahun = $sem?->tahun_ajaran;
            $semNama = KesiapanPeriode::normalisasiSemester($sem?->nama_semester);
            $tgl = Carbon::parse($tr->waktu_selesai);

            $detail = is_array($tr->detail_nilai_mapel) ? $tr->detail_nilai_mapel : [];
            if ($detail !== []) {
                foreach ($detail as $idx => $baris) {
                    $maks = (float) ($baris['maksimal'] ?? 0);
                    $skor = (float) ($baris['skor'] ?? 0);
                    if ($maks <= 0) {
                        continue;
                    }
                    $namaMapel = (string) ($baris['nama_mapel'] ?? '');
                    $hasil->push($this->baris(
                        $siswa[$sid], 'ujian', 'tryout:'.$tr->id.':m'.$idx,
                        ($session->nama_sesi ?: 'Tryout').($namaMapel !== '' ? ' — '.$namaMapel : ''),
                        $tgl, $kelasSesi, $namaMapel !== '' ? $namaMapel : null, $namaMapel,
                        ($skor / $maks) * 100.0, 100.0, round($skor, 2).' / '.round($maks, 2),
                        'Dibaca dari hasil Tryout legacy.', $tahun, $semNama
                    ));
                }
            } elseif ((float) $tr->nilai_maksimal > 0) {
                $hasil->push($this->baris(
                    $siswa[$sid], 'ujian', 'tryout:'.$tr->id, ($session->nama_sesi ?: 'Tryout'),
                    $tgl, $kelasSesi, null, null, ((float) $tr->nilai_total / (float) $tr->nilai_maksimal) * 100.0, 100.0,
                    $tr->nilai_total.' / '.$tr->nilai_maksimal, 'Dibaca dari hasil Tryout legacy.', $tahun, $semNama
                ));
            }
        }

        return $hasil;
    }

    private function asesmenTka(array $ids, Collection $siswa): Collection
    {
        $rows = $this->scopeTenant(StudentAssessment::query()->withoutGlobalScopes(), 'tka_student_assessments.tenant_id')
            ->whereIn('siswa_id', $ids)
            ->with('semester')
            ->get();

        $hasil = collect();
        foreach ($rows as $a) {
            $tgl = $a->tanggal_penilaian ?? $a->created_at;
            $sem = $a->semester;
            foreach ((array) ($a->skor_per_dimensi ?? []) as $i => $dim) {
                $maks = (float) ($dim['maksimal'] ?? 0);
                $tot = (float) ($dim['total_skor'] ?? 0);
                if ($maks <= 0) {
                    continue;
                }
                $label = (string) ($dim['nama_dimensi'] ?? '');
                $hasil->push($this->baris(
                    $siswa[(int) $a->siswa_id], 'asesmen_tka', 'tkaa:'.$a->id.':'.$i, 'Asesmen TKA: '.$label,
                    Carbon::parse($tgl), null, $label, $label, ($tot / $maks) * 100.0, 100.0, $tot.' / '.$maks, null,
                    $sem?->tahun_ajaran, KesiapanPeriode::normalisasiSemester($sem?->nama_semester)
                ));
            }
        }

        return $hasil;
    }

    private function simulasiTka(array $ids, Collection $siswa): Collection
    {
        $rows = $this->scopeTenant(AdaptiveSimulation::query()->withoutGlobalScopes())
            ->whereIn('siswa_id', $ids)
            ->where('status', 'selesai')
            ->whereNotNull('submitted_at')
            ->get();

        $hasil = collect();
        foreach ($rows as $s) {
            $tgl = $s->submitted_at ?? $s->created_at;
            $catatan = $s->mode_simulasi === 'adaptif'
                ? 'Simulasi adaptif (CAT): tingkat kesulitan berubah dinamis, tidak setara langsung dengan ujian linier atau nilai rapor.'
                : 'Simulasi mandiri TKA: latihan mandiri, bukan nilai resmi TKA atau nilai rapor.';

            foreach ((array) ($s->skor_indikator ?? []) as $nama => $nilai) {
                if (! is_numeric($nilai) || (float) $nilai < 0 || (float) $nilai > 100) {
                    continue;
                }
                $hasil->push($this->baris(
                    $siswa[(int) $s->siswa_id], 'simulasi_tka', 'sim:'.$s->id.':'.$nama,
                    'Simulasi: '.$s->paket_judul.' — '.$nama,
                    Carbon::parse($tgl), null, (string) $nama, (string) $nama,
                    (float) $nilai, 100.0, $nilai.' / 100', $catatan
                ));
            }
        }

        return $hasil;
    }

    // ── normalisasi baris & validasi histori kelas ─────────────────────────

    /** @return array<string, mixed> */
    private function baris(
        Siswa $siswa,
        string $sumber,
        string $id,
        string $judul,
        Carbon $tanggal,
        ?string $kelasSumber,
        ?string $mapel,
        ?string $labelPemetaan,
        float $skor,
        float $skala,
        string $nilaiAsli,
        ?string $catatan,
        ?string $tahunAjaran = null,
        ?string $semester = null,
    ): array {
        $periodeDariTgl = KesiapanPeriode::dariTanggal($tanggal);
        $tahun = KesiapanPeriode::normalisasiTahunAjaran($tahunAjaran) ?? $periodeDariTgl['tahun_ajaran'];
        $sem = $semester ?: $periodeDariTgl['semester'];
        $tingkatLangsung = KesiapanPeriode::tingkatDariKelas($kelasSumber);

        return [
            'id' => $id,
            'siswa_id' => $siswa->id,
            'sumber' => $sumber,
            'sumber_label' => (string) config("tka_kesiapan.sumber.$sumber.label", $sumber),
            'ref' => $id,
            'judul' => $judul,
            'tanggal' => $tanggal->format('Y-m-d'),
            'tahun_ajaran' => $tahun,
            'semester' => $sem,
            'periode_valid' => KesiapanPeriode::tahunAjaranValid($tahun) && $sem !== null,
            'kelas_sumber' => $kelasSumber !== null && trim($kelasSumber) !== '' ? trim($kelasSumber) : null,
            'tingkat' => $tingkatLangsung,
            'tingkat_sumber' => $tingkatLangsung !== null ? 'sumber_langsung' : 'tidak_diketahui',
            'tingkat_valid' => $tingkatLangsung !== null,
            'mapel' => $mapel,
            'indikator' => $this->resolver->resolve($labelPemetaan),
            'skor' => round(max(0.0, min(100.0, ($skor / $skala) * 100.0)), 2),
            'nilai_asli' => $nilaiAsli,
            'skala' => (string) config("tka_kesiapan.sumber.$sumber.skala", ''),
            'catatan' => $catatan,
        ];
    }

    /**
     * Validasi jenjang historis:
     *  - Tidak pernah menebak kelas masa lalu dengan mengurangi tahun dari kelas sekarang.
     *  - Hanya melengkapi jenjang periode dari sumber faktual di periode (tahun_ajaran, semester) yang sama,
     *    itu pun hanya jika tidak ada konflik/perpindahan kelas dalam periode tersebut.
     *  - Mendeteksi tinggal kelas, akselerasi, pindah rombel, kalender tak konsisten, dan mutasi.
     *
     * @param  Collection<int, array<string, mixed>>  $mentah
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function validasiJenjangHistoris(
        Collection $mentah,
        Collection $siswaList,
        Collection $semestersMap,
        Collection $rombelsMap,
        array $kondisi
    ): array {
        $ids = $siswaList->keys()->all();
        $kenaikan = Schema::hasTable('kenaikan_kelas')
            ? $this->scopeTenant(KenaikanKelas::query()->withoutGlobalScopes())->whereIn('siswa_id', $ids)->get()
            : collect();

        foreach ($kenaikan->where('naik_kelas', false) as $kk) {
            $kondisi[(int) $kk->siswa_id]['tinggal_kelas'][] = "Catatan kenaikan kelas {$kk->tahun_ajaran} {$kk->semester}: tinggal kelas.";
        }

        // Kumpulkan seluruh rekaman kelas faktual per (siswa_id, periode).
        $faktaPeriode = []; // [sid][ta|sem] => ['tingkat' => [int=>true], 'kelas' => [str=>true]]
        foreach ($mentah as $b) {
            if (! $b['tingkat_valid'] || ! $b['periode_valid']) {
                continue;
            }
            $sid = (int) $b['siswa_id'];
            $pk = $b['tahun_ajaran'].'|'.$b['semester'];
            $faktaPeriode[$sid][$pk]['tingkat'][$b['tingkat']] = true;
            if ($b['kelas_sumber'] !== null) {
                $faktaPeriode[$sid][$pk]['kelas'][strtoupper(trim($b['kelas_sumber']))] = true;
            }
        }

        // Tambahkan kelas siswa saat ini HANYA untuk periode `siswa.semester_id` yang eksplisit cocok.
        foreach ($siswaList as $sid => $siswa) {
            $sem = $siswa->semester_id ? $semestersMap->get($siswa->semester_id) : null;
            if ($sem) {
                $ta = KesiapanPeriode::normalisasiTahunAjaran($sem->tahun_ajaran);
                $sm = KesiapanPeriode::normalisasiSemester($sem->nama_semester);
                $rombel = $siswa->rombel_id ? $rombelsMap->get($siswa->rombel_id) : null;
                $tingkatAktif = $rombel && $rombel->tingkat !== null
                    ? KesiapanPeriode::tingkatDariKelas((string) $rombel->tingkat)
                    : KesiapanPeriode::tingkatDariKelas($siswa->kelas);
                if ($ta !== null && $sm !== null && $tingkatAktif !== null) {
                    $pk = $ta.'|'.$sm;
                    $faktaPeriode[$sid][$pk]['tingkat'][$tingkatAktif] = true;
                    if (! empty($siswa->kelas)) {
                        $faktaPeriode[$sid][$pk]['kelas'][strtoupper(trim((string) $siswa->kelas))] = true;
                    }
                } elseif ($ta === null || $sm === null) {
                    $kondisi[$sid]['kalender_tidak_konsisten'][] = 'Semester pada profil siswa tidak valid.';
                }
            } elseif ($mentah->where('siswa_id', $sid)->isNotEmpty()) {
                $kondisi[$sid]['kalender_tidak_konsisten'][] = 'Profil siswa belum memiliki semester_id; bukti tanpa kelas sumber ditandai tidak diketahui.';
            }

            if ($siswa->status_siswa && strtolower((string) $siswa->status_siswa) !== 'aktif') {
                $kondisi[$sid]['catatan_mutasi'] = 'Status siswa tercatat: '.$siswa->status_siswa.'.';
            }
        }

        // Identifikasi konflik pindah rombel & periode tak ambigu.
        $konflikPeriode = [];
        $tingkatTunggal = [];
        foreach ($faktaPeriode as $sid => $perMap) {
            foreach ($perMap as $pk => $info) {
                $tingkatSet = array_keys($info['tingkat'] ?? []);
                $kelasSet = array_keys($info['kelas'] ?? []);
                if (count($tingkatSet) > 1 || count($kelasSet) > 1) {
                    $konflikPeriode[$sid][$pk] = true;
                    $kondisi[$sid]['pindah_rombel'][] = str_replace('|', ' ', $pk).' (tercatat lebih dari satu kelas/rombel: '.implode(', ', $kelasSet).')';
                } elseif (count($tingkatSet) === 1) {
                    $tingkatTunggal[$sid][$pk] = $tingkatSet[0];
                }
            }
        }

        // Terapkan resolusi tervalidasi ke setiap bukti (tanpa menebak).
        $hasil = $mentah->map(function (array $b) use ($konflikPeriode, $tingkatTunggal, &$kondisi) {
            $sid = (int) $b['siswa_id'];
            $pk = $b['tahun_ajaran'].'|'.$b['semester'];

            // Cek kesesuaian tanggal bukti dengan tahun ajaran (hanya untuk sumber bertanggal nyata bukan e-Rapor).
            if ($b['sumber'] !== 'erapor' && $b['periode_valid']) {
                $tgl = Carbon::parse($b['tanggal']);
                if (! KesiapanPeriode::tanggalCocokTahunAjaran($tgl, $b['tahun_ajaran'])) {
                    $kondisi[$sid]['kalender_tidak_konsisten'][] = "{$b['judul']} ({$b['tanggal']}) di luar rentang tahun ajaran {$b['tahun_ajaran']}.";
                }
            }

            if ($b['tingkat_valid']) {
                return $b;
            }

            if (isset($konflikPeriode[$sid][$pk])) {
                $b['tingkat_sumber'] = 'pindah_rombel_konflik';

                return $b;
            }

            if ($b['periode_valid'] && isset($tingkatTunggal[$sid][$pk])) {
                $b['tingkat'] = $tingkatTunggal[$sid][$pk];
                $b['tingkat_sumber'] = 'periode_tervalidasi';
                $b['tingkat_valid'] = true;

                return $b;
            }

            return $b;
        });

        // Deteksi tinggal kelas & akselerasi dari urutan tahun ajaran faktual.
        foreach ($tingkatTunggal as $sid => $perMap) {
            $perTahun = [];
            foreach ($perMap as $pk => $t) {
                [$ta] = explode('|', $pk);
                $mulai = KesiapanPeriode::tahunMulai($ta);
                if ($mulai !== null) {
                    $perTahun[$mulai][$t] = true;
                }
            }
            ksort($perTahun);
            $tahunByTingkat = [];
            $prevMulai = null;
            $prevTingkat = null;
            foreach ($perTahun as $mulai => $tingkatMap) {
                foreach (array_keys($tingkatMap) as $t) {
                    $tahunByTingkat[$t][] = $mulai.'/'.($mulai + 1);
                    if ($prevMulai !== null && $mulai === $prevMulai + 1 && ($t - $prevTingkat) >= 2) {
                        $kondisi[$sid]['akselerasi'][] = "Kenaikan kelas melompat dari Kelas {$prevTingkat} ke Kelas {$t}.";
                    }
                    $prevMulai = $mulai;
                    $prevTingkat = $t;
                }
            }
            foreach ($tahunByTingkat as $t => $tahunArr) {
                $unik = array_values(array_unique($tahunArr));
                if (count($unik) > 1) {
                    $kondisi[$sid]['tinggal_kelas'][] = "Tercatat pada Kelas {$t} di lebih dari satu tahun ajaran (".implode(', ', $unik).').';
                }
            }
        }

        // Unikkan daftar pesan per siswa.
        foreach ($kondisi as $sid => $k) {
            foreach (['tinggal_kelas', 'akselerasi', 'pindah_rombel', 'kalender_tidak_konsisten'] as $kunci) {
                $kondisi[$sid][$kunci] = array_values(array_unique($k[$kunci]));
            }
        }

        return [$hasil, $kondisi];
    }
}
