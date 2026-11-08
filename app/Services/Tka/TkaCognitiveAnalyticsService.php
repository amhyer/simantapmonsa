<?php

namespace App\Services\Tka;

use App\Models\Dimensi;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\PengaturanGuru;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Tka\AdaptiveSimulation;
use App\Models\Tka\EraporSyncLog;
use App\Models\Tka\LearningRecommendation;
use App\Models\Tka\StudentAssessment;
use App\Models\User;
use App\Services\AktivitasService;
use App\Services\JadwalPelajaranResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TkaCognitiveAnalyticsService
{
    public const INDIKATOR_STANDAR = [
        'Literasi Membaca',
        'Numerasi Kuantitatif',
        'Penalaran Sains',
        'Logika & Penalaran Kritis',
        'Evaluasi & Pemecahan Masalah',
    ];

    public const KATALOG_TINDAK_LANJUT = [
        'Literasi Membaca' => [
            'remedial_judul' => 'Paket Klinik Literasi Wacana & Inferensi Teks',
            'remedial_aktivitas' => [
                'Latihan menemukan gagasan pokok dan fakta tersurat pada teks informasi 2–3 paragraf.',
                'Latihan menyimpulkan hubungan sebab-akibat antarparagraf dengan peta pikiran.',
                'Kerjakan Simulasi Adaptif Subtes Literasi Membaca minimal 1 sesi per minggu.',
            ],
            'pengayaan_judul' => 'Paket Pengayaan Sintesis Multi-Teks & Evaluasi Argumen',
            'pengayaan_aktivitas' => [
                'Membandingkan dua teks opini/informasi dan mengevaluasi kekuatan bukti pendukungnya.',
                'Menjadi tutor sebaya diskusi bedah wacana di kelompok belajar kelas.',
            ],
            'paket_simulasi' => 'literasi',
            'tips_orang_tua' => 'Dampingi anak membaca artikel sains/cerita pendek 15 menit setiap malam lalu ajukan 2 pertanyaan "mengapa" dan "bagaimana".',
        ],
        'Numerasi Kuantitatif' => [
            'remedial_judul' => 'Paket Penguatan Numerasi Kontekstual & Rasio Terapan',
            'remedial_aktivitas' => [
                'Latihan bertahap operasi pecahan, persentase, dan perbandingan senilai dalam soal cerita sehari-hari.',
                'Latihan menerjemahkan tabel/grafik sederhana ke kalimat matematika.',
                'Kerjakan Simulasi Adaptif Subtes Numerasi dimulai dari level dasar-menengah.',
            ],
            'pengayaan_judul' => 'Paket Tantangan Pemodelan Aljabar & Optimasi Data',
            'pengayaan_aktivitas' => [
                'Menyelesaikan soal non-rutin bertema estimasi anggaran, geometri ruang, dan interpretasi rata-rata gabungan.',
                'Latihan soal HOTS numerasi lintas mata pelajaran.',
            ],
            'paket_simulasi' => 'numerasi',
            'tips_orang_tua' => 'Libatkan anak menghitung perbandingan harga, diskon belanja, atau takaran bahan masakan di rumah.',
        ],
        'Penalaran Sains' => [
            'remedial_judul' => 'Paket Eksplorasi Konsep IPA & Penyelidikan Ilmiah',
            'remedial_aktivitas' => [
                'Membedakan variabel bebas, variabel terikat, dan variabel kontrol melalui contoh percobaan sederhana.',
                'Menganalisis rantai sebab-akibat pada fenomena ekosistem dan perubahan wujud benda.',
                'Kerjakan Simulasi Adaptif Subtes Penalaran Sains untuk memperkuat literasi data percobaan.',
            ],
            'pengayaan_judul' => 'Paket Proyek Inkuiri & Analisis Data Eksperimen',
            'pengayaan_aktivitas' => [
                'Merancang mini-eksperimen terukur dan menarik kesimpulan berbasis tabel pengamatan.',
                'Menganalisis grafik perubahan fisika/hayati multi-variabel.',
            ],
            'paket_simulasi' => 'sains',
            'tips_orang_tua' => 'Ajak anak mengamati fenomena alam di rumah (embun, bayangan, tanaman) dan mendiskusikan penyebab ilmiahnya.',
        ],
        'Logika & Penalaran Kritis' => [
            'remedial_judul' => 'Paket Latihan Logika Deduktif, Pola & Silogisme',
            'remedial_aktivitas' => [
                'Latihan mengenali pola barisan bilangan/gambar serta aturan pengurutan posisi.',
                'Latihan menarik simpulan logis dari pernyataan bersyarat ("Jika ... maka ...").',
                'Kerjakan Simulasi Adaptif Subtes Penalaran Logika secara berkala.',
            ],
            'pengayaan_judul' => 'Paket Olimpiade Penalaran Analitis & Teka-Teki Aturan',
            'pengayaan_aktivitas' => [
                'Menyelesaikan soal penjadwalan bersyarat (constraint satisfaction) dan prinsip sarang merpati.',
                'Mengevaluasi kesesatan berpikir (logical fallacy) pada studi kasus pendek.',
            ],
            'paket_simulasi' => 'logika',
            'tips_orang_tua' => 'Bermain permainan strategi, teka-teki pola, atau catur ringan bersama anak di akhir pekan.',
        ],
        'Evaluasi & Pemecahan Masalah' => [
            'remedial_judul' => 'Paket Strategi Pemecahan Masalah Bertahap (Polya)',
            'remedial_aktivitas' => [
                'Membiasakan 4 langkah: pahami masalah, susun rencana, jalankan perhitungan, dan periksa kembali hasil.',
                'Mengurai soal cerita panjang menjadi poin informasi diketahui dan ditanyakan.',
                'Latihan terarah pada Simulasi Adaptif Literasi & Numerasi.',
            ],
            'pengayaan_judul' => 'Paket Studi Kasus HOTS & Pengambilan Keputusan Berbasis Bukti',
            'pengayaan_aktivitas' => [
                'Menyusun solusi alternatif atas permasalahan lingkungan/sekolah berbasis data kuantitatif.',
                'Mempresentasikan argumen solusi di depan kelas.',
            ],
            'paket_simulasi' => 'logika',
            'tips_orang_tua' => 'Saat anak menghadapi kesulitan tugas, bimbing dengan pertanyaan pemandu alih-alih langsung memberi jawaban akhir.',
        ],
    ];

    /**
     * Bangun rekap profil kognitif lengkap untuk satu kumpulan siswa sekaligus
     * menghitung zona kemampuan relatif terhadap rata-rata kelompok/kelas.
     *
     * @param  Collection<int, Siswa>  $siswaList
     * @return array{rata_rata_kelompok: float, zona_counts: array<string, int>, profiles: array<int, array<string, mixed>>}
     */
    public function buildCohortProfiles(Collection $siswaList): array
    {
        if ($siswaList->isEmpty()) {
            return [
                'rata_rata_kelompok' => 0.0,
                'zona_counts' => [
                    'di_atas_rata_rata' => 0,
                    'rata_rata' => 0,
                    'di_bawah_rata_rata' => 0,
                ],
                'profiles' => [],
            ];
        }

        $siswaIds = $siswaList->pluck('id')->all();

        $assessmentsBySiswa = StudentAssessment::with(['responses.assessmentItem.dimension', 'semester'])
            ->whereIn('siswa_id', $siswaIds)
            ->orderBy('tanggal_penilaian')
            ->orderBy('id')
            ->get()
            ->groupBy('siswa_id');

        $simulationsBySiswa = AdaptiveSimulation::query()
            ->whereIn('siswa_id', $siswaIds)
            ->where('status', 'selesai')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get()
            ->groupBy('siswa_id');

        $dimensiBySiswa = Dimensi::query()
            ->whereIn('siswa_id', $siswaIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy('siswa_id');

        $recommendationsBySiswa = LearningRecommendation::query()
            ->whereIn('siswa_id', $siswaIds)
            ->latest('updated_at')
            ->get()
            ->groupBy('siswa_id')
            ->map(fn (Collection $items) => $items->first());

        $rawProfiles = [];
        $activeScores = [];

        foreach ($siswaList as $siswa) {
            $profile = $this->computeRawProfile(
                $siswa,
                $assessmentsBySiswa->get($siswa->id, collect()),
                $simulationsBySiswa->get($siswa->id, collect()),
                $dimensiBySiswa->get($siswa->id, collect()),
                $recommendationsBySiswa->get($siswa->id)
            );
            $rawProfiles[$siswa->id] = $profile;
            if ($profile['jumlah_pengujian'] > 0) {
                $activeScores[] = $profile['skor_kognitif'];
            }
        }

        $cohortMean = count($activeScores) > 0
            ? round(array_sum($activeScores) / count($activeScores), 2)
            : 70.0;

        $zonaCounts = [
            'di_atas_rata_rata' => 0,
            'rata_rata' => 0,
            'di_bawah_rata_rata' => 0,
        ];

        $finalProfiles = [];
        foreach ($rawProfiles as $profile) {
            $zona = $this->tentukanZona($profile['skor_kognitif'], $cohortMean, $profile['jumlah_pengujian'] > 0);
            $profile['rata_rata_pembanding'] = $cohortMean;
            $profile['selisih_rata_rata'] = round($profile['skor_kognitif'] - $cohortMean, 2);
            $profile['zona_kemampuan'] = $zona['kode'];
            $profile['zona_label'] = $zona['label'];
            $profile['zona_badge'] = $zona['badge'];

            if ($profile['jumlah_pengujian'] > 0) {
                $zonaCounts[$zona['kode']]++;
            }

            $finalProfiles[] = $profile;
        }

        return [
            'rata_rata_kelompok' => $cohortMean,
            'zona_counts' => $zonaCounts,
            'profiles' => $finalProfiles,
        ];
    }

    /**
     * Bangun rekap profil kognitif lengkap untuk satu siswa dibandingkan rekan sekelasnya.
     *
     * @return array<string, mixed>
     */
    public function buildStudentCognitiveProfile(Siswa $siswa, ?float $cohortAverageOverride = null): array
    {
        if ($cohortAverageOverride === null) {
            $peers = Siswa::query()
                ->where('kelas', $siswa->kelas)
                ->where('aktif', true)
                ->get();
            if ($peers->isEmpty() || ! $peers->contains('id', $siswa->id)) {
                $peers = collect([$siswa]);
            }

            $cohort = $this->buildCohortProfiles($peers);
            foreach ($cohort['profiles'] as $p) {
                if ((int) $p['siswa']->id === (int) $siswa->id) {
                    return $p;
                }
            }
        }

        $assessments = StudentAssessment::with(['responses.assessmentItem.dimension', 'semester'])
            ->where('siswa_id', $siswa->id)
            ->orderBy('tanggal_penilaian')
            ->orderBy('id')
            ->get();

        $simulations = AdaptiveSimulation::query()
            ->where('siswa_id', $siswa->id)
            ->where('status', 'selesai')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $dimensi = Dimensi::query()
            ->where('siswa_id', $siswa->id)
            ->orderBy('created_at')
            ->get();

        $recommendation = LearningRecommendation::query()
            ->where('siswa_id', $siswa->id)
            ->latest('updated_at')
            ->first();

        $profile = $this->computeRawProfile($siswa, $assessments, $simulations, $dimensi, $recommendation);
        $mean = $cohortAverageOverride ?? 70.0;
        $zona = $this->tentukanZona($profile['skor_kognitif'], $mean, $profile['jumlah_pengujian'] > 0);

        $profile['rata_rata_pembanding'] = $mean;
        $profile['selisih_rata_rata'] = round($profile['skor_kognitif'] - $mean, 2);
        $profile['zona_kemampuan'] = $zona['kode'];
        $profile['zona_label'] = $zona['label'];
        $profile['zona_badge'] = $zona['badge'];

        return $profile;
    }

    /**
     * Susun rekomendasi personalisasi pembelajaran otomatis berdasarkan indikator lemah & kuat.
     */
    public function generateRecommendation(
        Siswa $siswa,
        ?int $semesterId = null,
        ?int $actorId = null,
        ?float $cohortAverageOverride = null
    ): LearningRecommendation {
        $profile = $this->buildStudentCognitiveProfile($siswa, $cohortAverageOverride);
        $indikatorScores = $profile['indikator_scores'];

        $lemah = [];
        $kuat = [];
        foreach ($indikatorScores as $nama => $data) {
            if (! $data['terukur']) {
                continue;
            }
            if ($data['skor'] < 70.0) {
                $lemah[] = [
                    'indikator' => $nama,
                    'skor' => $data['skor'],
                    'target' => 70.0,
                    'kesenjangan' => round(70.0 - $data['skor'], 2),
                ];
            } elseif ($data['skor'] >= 80.0) {
                $kuat[] = [
                    'indikator' => $nama,
                    'skor' => $data['skor'],
                ];
            }
        }

        // Jika semua indikator >= 70 tetapi ada yang terendah (< 80), tetap beri 1 fokus penguatan.
        if ($lemah === [] && $profile['jumlah_pengujian'] > 0) {
            $measured = array_filter($indikatorScores, fn (array $d) => $d['terukur']);
            uasort($measured, fn (array $a, array $b) => $a['skor'] <=> $b['skor']);
            $firstKey = array_key_first($measured);
            if ($firstKey !== null && $measured[$firstKey]['skor'] < 80.0) {
                $lemah[] = [
                    'indikator' => $firstKey,
                    'skor' => $measured[$firstKey]['skor'],
                    'target' => 80.0,
                    'kesenjangan' => round(80.0 - $measured[$firstKey]['skor'], 2),
                ];
            }
        }

        usort($lemah, fn (array $a, array $b) => $a['skor'] <=> $b['skor']);
        usort($kuat, fn (array $a, array $b) => $b['skor'] <=> $a['skor']);

        $paketTindakLanjut = [];
        foreach ($lemah as $item) {
            $katalog = $this->resolveKatalogIndikator($item['indikator']);
            $paketTindakLanjut[] = [
                'tipe' => 'remedial',
                'prioritas' => $item['skor'] < 55 ? 'tinggi' : 'sedang',
                'indikator' => $item['indikator'],
                'skor_saat_ini' => $item['skor'],
                'judul_paket' => $katalog['remedial_judul'],
                'langkah_kegiatan' => $katalog['remedial_aktivitas'],
                'paket_simulasi' => $katalog['paket_simulasi'],
                'tips_orang_tua' => $katalog['tips_orang_tua'],
            ];
        }

        foreach (array_slice($kuat, 0, 2) as $item) {
            $katalog = $this->resolveKatalogIndikator($item['indikator']);
            $paketTindakLanjut[] = [
                'tipe' => 'pengayaan',
                'prioritas' => 'pengembangan',
                'indikator' => $item['indikator'],
                'skor_saat_ini' => $item['skor'],
                'judul_paket' => $katalog['pengayaan_judul'],
                'langkah_kegiatan' => $katalog['pengayaan_aktivitas'],
                'paket_simulasi' => $katalog['paket_simulasi'],
                'tips_orang_tua' => $katalog['tips_orang_tua'],
            ];
        }

        if ($paketTindakLanjut === []) {
            $katalogDefault = self::KATALOG_TINDAK_LANJUT['Literasi Membaca'];
            $paketTindakLanjut[] = [
                'tipe' => 'diagnostik_awal',
                'prioritas' => 'awal',
                'indikator' => 'Pemetaan Diagnostik Awal',
                'skor_saat_ini' => 0,
                'judul_paket' => 'Paket Pemetaan Awal Simulasi Adaptif TKA',
                'langkah_kegiatan' => [
                    'Arahkan siswa mengikuti Simulasi Adaptif Literasi dan Numerasi untuk memetakan profil kognitif awal.',
                    'Lakukan penilaian diagnostik kisi-kisi TKA semester berjalan.',
                ],
                'paket_simulasi' => $katalogDefault['paket_simulasi'],
                'tips_orang_tua' => $katalogDefault['tips_orang_tua'],
            ];
        }

        $resolvedSemesterId = $semesterId ?? Semester::query()->orderByDesc('tahun_ajaran')->orderByDesc('id')->value('id');

        $existing = LearningRecommendation::query()
            ->where('siswa_id', $siswa->id)
            ->when($resolvedSemesterId, fn ($q) => $q->where('semester_id', $resolvedSemesterId))
            ->first();

        if ($existing) {
            $existing->update([
                'dibuat_oleh' => $actorId ?? $existing->dibuat_oleh,
                'zona_kemampuan' => $profile['zona_kemampuan'],
                'skor_kognitif' => $profile['skor_kognitif'],
                'rata_rata_pembanding' => $profile['rata_rata_pembanding'],
                'indikator_lemah' => $lemah,
                'indikator_kuat' => $kuat,
                'paket_tindak_lanjut' => $paketTindakLanjut,
            ]);

            return $existing->fresh();
        }

        return LearningRecommendation::create([
            'tenant_id' => $siswa->tenant_id,
            'siswa_id' => $siswa->id,
            'semester_id' => $resolvedSemesterId,
            'dibuat_oleh' => $actorId,
            'zona_kemampuan' => $profile['zona_kemampuan'],
            'skor_kognitif' => $profile['skor_kognitif'],
            'rata_rata_pembanding' => $profile['rata_rata_pembanding'],
            'indikator_lemah' => $lemah,
            'indikator_kuat' => $kuat,
            'paket_tindak_lanjut' => $paketTindakLanjut,
            'status_tindak_lanjut' => 'direkomendasikan',
        ]);
    }

    /**
     * Sinkronkan capaian kognitif TKA siswa ke tabel `nilai` (penilaian harian/kognitif)
     * sekaligus `nilai_erapor` (alur e-Rapor resmi beserta audit rantai hash).
     *
     * @param  array{mata_pelajaran: string, komponen_target?: string, semester?: string, tahun_ajaran?: string, semester_id?: int|null}  $options
     */
    public function syncStudentToErapor(Siswa $siswa, User $guru, array $options): EraporSyncLog
    {
        $profile = $this->buildStudentCognitiveProfile($siswa);
        if ($profile['jumlah_pengujian'] === 0) {
            throw new RuntimeException("Siswa {$siswa->nama_peserta_didik} belum memiliki data penilaian atau simulasi TKA untuk disinkronkan.");
        }

        $mapel = trim((string) ($options['mata_pelajaran'] ?? 'Matematika'));
        $komponen = in_array($options['komponen_target'] ?? 'sumatif', ['formatif', 'sumatif', 'sumatif_akhir'], true)
            ? (string) $options['komponen_target']
            : 'sumatif';

        $pengaturan = PengaturanGuru::query()->where('guru_id', $guru->id)->first();
        $semesterModel = ! empty($options['semester_id'])
            ? Semester::find($options['semester_id'])
            : Semester::query()->orderByDesc('tahun_ajaran')->orderByDesc('id')->first();

        $semesterLabel = $options['semester']
            ?? ($semesterModel ? ucfirst(strtolower((string) $semesterModel->nama_semester)) : null)
            ?? ($pengaturan->semester ?? 'Ganjil');
        if (! in_array($semesterLabel, ['Ganjil', 'Genap'], true)) {
            $semesterLabel = str_contains(strtolower($semesterLabel), 'genap') ? 'Genap' : 'Ganjil';
        }

        $tahunAjaran = $options['tahun_ajaran']
            ?? ($semesterModel->tahun_ajaran ?? null)
            ?? ($pengaturan->tahun_pelajaran ?? (date('Y').'/'.(date('Y') + 1)));

        $kkm = (int) ($pengaturan->kkm ?? getKKM());
        $bobot = ($pengaturan->pengaturan ?? [])['bobot_erapor']
            ?? ['formatif' => 0.30, 'sumatif' => 0.40, 'sumatif_akhir' => 0.30];

        $skorKonversi = round(max(0.0, min(100.0, (float) $profile['skor_kognitif'])), 2);
        $latestAssessment = StudentAssessment::query()
            ->where('siswa_id', $siswa->id)
            ->latest('tanggal_penilaian')
            ->latest('id')
            ->first();

        $measured = array_filter($profile['indikator_scores'], fn (array $d) => $d['terukur']);
        uasort($measured, fn (array $a, array $b) => $b['skor'] <=> $a['skor']);
        $indikatorTertinggi = array_key_first($measured) ?? 'penalaran kognitif TKA';
        $indikatorTerendah = count($measured) > 1 ? array_key_last($measured) : null;

        $jadwalId = app(JadwalPelajaranResolver::class)->resolve(
            $guru->id,
            $mapel,
            (string) $siswa->kelas,
            $semesterLabel,
            $tahunAjaran
        );

        return DB::transaction(function () use (
            $siswa,
            $guru,
            $mapel,
            $komponen,
            $semesterLabel,
            $tahunAjaran,
            $semesterModel,
            $kkm,
            $bobot,
            $skorKonversi,
            $latestAssessment,
            $indikatorTertinggi,
            $indikatorTerendah,
            $jadwalId,
            $profile
        ) {
            $judulPenilaian = "Integrasi Kognitif TKA ({$semesterLabel} {$tahunAjaran})";

            $jenisNilai = match ($komponen) {
                'formatif' => 'Tugas',
                'sumatif_akhir' => 'PAS',
                default => 'Ulangan Harian',
            };

            $nilaiRow = Nilai::query()->updateOrCreate(
                [
                    'siswa_id' => $siswa->id,
                    'guru_id' => $guru->id,
                    'mata_pelajaran' => $mapel,
                    // `nilai.jenis` adalah enum (Tugas/Ulangan Harian/Praktik/PTS/PAS):
                    // nilai di luar daftar itu melanggar check constraint PostgreSQL
                    // (nilai_jenis_check) dan membuat sinkronisasi gagal diam-diam.
                    'jenis' => $jenisNilai,
                    'judul_penilaian' => $judulPenilaian,
                ],
                [
                    'tenant_id' => $siswa->tenant_id,
                    'nama_guru' => $guru->nama_lengkap,
                    'nama_siswa' => $siswa->nama_peserta_didik,
                    'tanggal' => now()->toDateString(),
                    'nilai' => $skorKonversi,
                    'rekaman' => [
                        'sumber' => 'tka_kognitif',
                        'komponen_target' => $komponen,
                        'zona_kemampuan' => $profile['zona_kemampuan'],
                        'jumlah_pengujian' => $profile['jumlah_pengujian'],
                    ],
                ]
            );

            $erapor = NilaiErapot::query()
                ->where('siswa_id', $siswa->id)
                ->where('mata_pelajaran', $mapel)
                ->where('semester', $semesterLabel)
                ->where('tahun_ajaran', $tahunAjaran)
                ->first();

            $kolomTarget = 'nilai_'.$komponen;
            if (! $erapor) {
                $erapor = new NilaiErapot([
                    'tenant_id' => $siswa->tenant_id,
                    'siswa_id' => $siswa->id,
                    'guru_id' => $guru->id,
                    'jadwal_pelajaran_id' => $jadwalId,
                    'mata_pelajaran' => $mapel,
                    'kelas' => (string) $siswa->kelas,
                    'semester' => $semesterLabel,
                    'tahun_ajaran' => $tahunAjaran,
                    'fase' => $this->inferFaseFromKelas((string) $siswa->kelas),
                    'nilai_formatif' => $skorKonversi,
                    'nilai_sumatif' => $skorKonversi,
                    'nilai_sumatif_akhir' => $skorKonversi,
                ]);
            } else {
                $erapor->guru_id = $guru->id;
                $erapor->kelas = (string) $siswa->kelas;
                if ($jadwalId !== null && $erapor->jadwal_pelajaran_id === null) {
                    $erapor->jadwal_pelajaran_id = $jadwalId;
                }
                $erapor->{$kolomTarget} = $skorKonversi;
            }

            $erapor->capaian_tertinggi = 'indikator TKA '.strtolower($indikatorTertinggi);
            if ($indikatorTerendah !== null && ($profile['indikator_scores'][$indikatorTerendah]['skor'] ?? 100) < 75) {
                $erapor->capaian_terendah = 'indikator TKA '.strtolower($indikatorTerendah);
            }

            $erapor->nilai_akhir = $erapor->hitungNilaiAkhir(
                (float) ($bobot['formatif'] ?? 0.30),
                (float) ($bobot['sumatif'] ?? 0.40),
                (float) ($bobot['sumatif_akhir'] ?? 0.30)
            );
            $erapor->predikat = $erapor->generatePredikat($kkm);
            $erapor->deskripsi_capaian = $erapor->generateDeskripsi();
            $erapor->save();

            $syncLog = EraporSyncLog::create([
                'tenant_id' => $siswa->tenant_id,
                'siswa_id' => $siswa->id,
                'guru_id' => $guru->id,
                'semester_id' => $semesterModel?->id,
                'assessment_id' => $latestAssessment?->id,
                'nilai_id' => $nilaiRow->id,
                'nilai_erapor_id' => $erapor->id,
                'mata_pelajaran' => $mapel,
                'komponen_target' => $komponen,
                'skor_tka_konversi' => $skorKonversi,
                'nilai_akhir_erapor' => $erapor->nilai_akhir,
                'ringkasan_sumber' => [
                    'skor_kognitif' => $profile['skor_kognitif'],
                    'zona_kemampuan' => $profile['zona_kemampuan'],
                    'jumlah_pengujian' => $profile['jumlah_pengujian'],
                    'indikator_tertinggi' => $indikatorTertinggi,
                    'indikator_terendah' => $indikatorTerendah,
                ],
                'synced_at' => now(),
            ]);

            AktivitasService::log(
                'ubah',
                'Sinkronisasi TKA ke Penilaian & e-Rapor',
                "Skor kognitif TKA {$siswa->nama_peserta_didik} ({$skorKonversi}) disinkronkan ke {$mapel} ({$komponen}).",
                'nilai_erapor',
                $erapor->id,
                null,
                [
                    'siswa_id' => $siswa->id,
                    'mata_pelajaran' => $mapel,
                    'komponen' => $komponen,
                    'skor_tka' => $skorKonversi,
                    'nilai_akhir_erapor' => $erapor->nilai_akhir,
                ]
            );

            return $syncLog;
        });
    }

    /**
     * Laporan Sekolah per Kelas/Jenjang lengkap dengan matriks distribusi indikator
     * dan Heatmap Kesenjangan terhadap target kesiapan sekolah.
     *
     * @return array<string, mixed>
     */
    public function buildSchoolClassHeatmap(?string $jenjangFilter = null, float $targetSkor = 70.0, ?int $guruId = null): array
    {
        $siswaQuery = Siswa::query()
            ->where('aktif', true)
            ->when($guruId !== null, fn ($q) => $q->where('guru_id', $guruId))
            ->orderBy('kelas')
            ->orderBy('nama_peserta_didik');

        $allSiswa = $siswaQuery->get();

        $jenjangOptions = $allSiswa
            ->map(fn (Siswa $s) => $this->extractTingkat((string) $s->kelas))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($jenjangFilter !== null && $jenjangFilter !== '') {
            $allSiswa = $allSiswa->filter(
                fn (Siswa $s) => $this->extractTingkat((string) $s->kelas) === $jenjangFilter
            )->values();
        }

        $cohort = $this->buildCohortProfiles($allSiswa);
        $profilesBySiswa = collect($cohort['profiles'])->keyBy(fn (array $p) => $p['siswa']->id);

        $indikatorColumns = self::INDIKATOR_STANDAR;
        foreach ($cohort['profiles'] as $prof) {
            foreach (array_keys($prof['indikator_scores']) as $indName) {
                if (! in_array($indName, $indikatorColumns, true)) {
                    $indikatorColumns[] = $indName;
                }
            }
        }

        $groupedByKelas = $allSiswa->groupBy(fn (Siswa $s) => trim((string) $s->kelas) ?: 'Tanpa Kelas');

        $kelasRows = [];
        $priorityGaps = [];
        $jenjangRollup = [];

        foreach ($groupedByKelas as $namaKelas => $siswaKelas) {
            $tingkat = $this->extractTingkat((string) $namaKelas);
            $indikatorCells = [];
            $kelasMeasuredScores = [];
            $terukurSiswaCount = 0;
            $zonaKelas = [
                'di_atas_rata_rata' => 0,
                'rata_rata' => 0,
                'di_bawah_rata_rata' => 0,
            ];

            foreach ($siswaKelas as $s) {
                $prof = $profilesBySiswa->get($s->id);
                if ($prof && $prof['jumlah_pengujian'] > 0) {
                    $terukurSiswaCount++;
                    $kelasMeasuredScores[] = $prof['skor_kognitif'];
                    $zonaKelas[$prof['zona_kemampuan']]++;
                }
            }

            foreach ($indikatorColumns as $ind) {
                $vals = [];
                foreach ($siswaKelas as $s) {
                    $prof = $profilesBySiswa->get($s->id);
                    if ($prof && ! empty($prof['indikator_scores'][$ind]['terukur'])) {
                        $vals[] = (float) $prof['indikator_scores'][$ind]['skor'];
                    }
                }

                if ($vals === []) {
                    $indikatorCells[$ind] = [
                        'terukur' => false,
                        'rata_skor' => null,
                        'kesenjangan' => null,
                        'level_heatmap' => 'kosong',
                        'jumlah_sampel' => 0,
                    ];

                    continue;
                }

                $avgInd = round(array_sum($vals) / count($vals), 1);
                $gap = round($avgInd - $targetSkor, 1);
                $heatLevel = $this->heatmapSeverity($avgInd, $targetSkor);

                $indikatorCells[$ind] = [
                    'terukur' => true,
                    'rata_skor' => $avgInd,
                    'kesenjangan' => $gap,
                    'level_heatmap' => $heatLevel,
                    'jumlah_sampel' => count($vals),
                ];

                if ($gap < 0) {
                    $priorityGaps[] = [
                        'kelas' => $namaKelas,
                        'tingkat' => $tingkat,
                        'indikator' => $ind,
                        'rata_skor' => $avgInd,
                        'target_skor' => $targetSkor,
                        'kesenjangan' => $gap,
                        'level_heatmap' => $heatLevel,
                    ];
                }
            }

            $rataKelas = count($kelasMeasuredScores) > 0
                ? round(array_sum($kelasMeasuredScores) / count($kelasMeasuredScores), 1)
                : null;

            $kelasRows[] = [
                'kelas' => $namaKelas,
                'tingkat' => $tingkat,
                'jumlah_siswa' => $siswaKelas->count(),
                'siswa_terukur' => $terukurSiswaCount,
                'rata_skor' => $rataKelas,
                'kesenjangan_umum' => $rataKelas !== null ? round($rataKelas - $targetSkor, 1) : null,
                'zona' => $zonaKelas,
                'cells' => $indikatorCells,
            ];

            if (! isset($jenjangRollup[$tingkat])) {
                $jenjangRollup[$tingkat] = [
                    'tingkat' => $tingkat,
                    'jumlah_kelas' => 0,
                    'jumlah_siswa' => 0,
                    'siswa_terukur' => 0,
                    'scores' => [],
                ];
            }
            $jenjangRollup[$tingkat]['jumlah_kelas']++;
            $jenjangRollup[$tingkat]['jumlah_siswa'] += $siswaKelas->count();
            $jenjangRollup[$tingkat]['siswa_terukur'] += $terukurSiswaCount;
            if ($rataKelas !== null) {
                $jenjangRollup[$tingkat]['scores'][] = $rataKelas;
            }
        }

        usort($priorityGaps, fn (array $a, array $b) => $a['kesenjangan'] <=> $b['kesenjangan']);

        $jenjangSummary = [];
        ksort($jenjangRollup);
        foreach ($jenjangRollup as $t => $info) {
            $avgJenjang = count($info['scores']) > 0
                ? round(array_sum($info['scores']) / count($info['scores']), 1)
                : null;
            $jenjangSummary[] = [
                'tingkat' => $t,
                'jumlah_kelas' => $info['jumlah_kelas'],
                'jumlah_siswa' => $info['jumlah_siswa'],
                'siswa_terukur' => $info['siswa_terukur'],
                'rata_skor' => $avgJenjang,
                'kesenjangan' => $avgJenjang !== null ? round($avgJenjang - $targetSkor, 1) : null,
            ];
        }

        return [
            'target_skor' => $targetSkor,
            'jenjang_filter' => $jenjangFilter,
            'jenjang_options' => $jenjangOptions,
            'indikator_columns' => $indikatorColumns,
            'rata_rata_sekolah' => $cohort['rata_rata_kelompok'],
            'zona_sekolah' => $cohort['zona_counts'],
            'total_siswa' => $allSiswa->count(),
            'kelas_rows' => $kelasRows,
            'jenjang_summary' => $jenjangSummary,
            'priority_gaps' => array_slice($priorityGaps, 0, 10),
        ];
    }

    /**
     * @param  Collection<int, StudentAssessment>  $assessments
     * @param  Collection<int, AdaptiveSimulation>  $simulations
     * @param  Collection<int, Dimensi>  $dimensi
     * @return array<string, mixed>
     */
    private function computeRawProfile(
        Siswa $siswa,
        Collection $assessments,
        Collection $simulations,
        Collection $dimensi,
        ?LearningRecommendation $recommendation
    ): array {
        $buckets = [];
        foreach (self::INDIKATOR_STANDAR as $std) {
            $buckets[$std] = [];
        }

        $timeline = [];

        foreach ($assessments as $assessment) {
            $tanggal = $assessment->tanggal_penilaian
                ? $assessment->tanggal_penilaian->format('Y-m-d')
                : $assessment->created_at?->format('Y-m-d');

            $timeline[] = [
                'tipe' => 'Penilaian Diagnostik TKA',
                'judul' => 'Asesmen Kisi-kisi '.($assessment->semester?->nama_semester ? ucfirst($assessment->semester->nama_semester) : 'TKA'),
                'tanggal' => $tanggal ?? now()->toDateString(),
                'skor' => round((float) $assessment->persentase, 2),
                'badge' => ucfirst(str_replace('_', ' ', (string) $assessment->status_kesiapan)),
            ];

            if ($assessment->relationLoaded('responses') && $assessment->responses->isNotEmpty()) {
                foreach ($assessment->responses as $resp) {
                    $item = $resp->assessmentItem;
                    if (! $item) {
                        continue;
                    }
                    $mappedInd = $this->mapToCognitiveIndicator(
                        (string) ($item->indikator ?: ($item->dimension?->nama_dimensi ?? '')),
                        (string) ($item->dimension?->kategori ?? '')
                    );
                    $buckets[$mappedInd][] = round(((float) $resp->skor / 4.0) * 100.0, 2);
                }
            } else {
                foreach ((array) ($assessment->skor_per_dimensi ?? []) as $dimRow) {
                    $maks = (float) ($dimRow['maksimal'] ?? 0);
                    $tot = (float) ($dimRow['total_skor'] ?? 0);
                    if ($maks <= 0) {
                        continue;
                    }
                    $mappedInd = $this->mapToCognitiveIndicator(
                        (string) ($dimRow['nama_dimensi'] ?? ''),
                        (string) ($dimRow['kategori'] ?? '')
                    );
                    $buckets[$mappedInd][] = round(($tot / $maks) * 100.0, 2);
                }
            }
        }

        foreach ($simulations as $sim) {
            $tanggal = $sim->submitted_at
                ? $sim->submitted_at->format('Y-m-d H:i')
                : ($sim->created_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'));

            $timeline[] = [
                'tipe' => $sim->mode_simulasi === 'adaptif' ? 'Simulasi Adaptif (CAT)' : 'Simulasi Mandiri',
                'judul' => $sim->paket_judul,
                'tanggal' => $tanggal,
                'skor' => round((float) $sim->skor_akhir, 2),
                'badge' => ($sim->level_capaian ?? 'Selesai').' • '.strtoupper((string) $sim->tingkat_akhir),
            ];

            foreach ((array) ($sim->skor_indikator ?? []) as $indName => $val) {
                $mappedInd = $this->mapToCognitiveIndicator((string) $indName, '');
                $buckets[$mappedInd][] = round((float) $val, 2);
            }
        }

        if ($assessments->isEmpty() && $simulations->isEmpty() && $dimensi->isNotEmpty()) {
            foreach ($dimensi as $dim) {
                $mappedInd = $this->mapToCognitiveIndicator((string) $dim->dimensi, '');
                $skor100 = round(((float) $dim->skor / 4.0) * 100.0, 2);
                $buckets[$mappedInd][] = $skor100;
            }

            $avgDim = round($dimensi->avg(fn (Dimensi $d) => ((float) $d->skor / 4.0) * 100.0), 2);
            $timeline[] = [
                'tipe' => 'Pemetaan Profil Awal',
                'judul' => 'Observasi Kesiapan Kognitif Guru',
                'tanggal' => $dimensi->last()?->created_at?->format('Y-m-d') ?? now()->toDateString(),
                'skor' => $avgDim,
                'badge' => 'Observasi Kelas',
            ];
        }

        usort($timeline, fn (array $a, array $b) => strcmp($a['tanggal'], $b['tanggal']));
        for ($i = 0; $i < count($timeline); $i++) {
            $timeline[$i]['urutan_uji'] = 'Uji #'.($i + 1);
            $timeline[$i]['perubahan'] = $i > 0
                ? round($timeline[$i]['skor'] - $timeline[$i - 1]['skor'], 2)
                : null;
        }

        $indikatorScores = [];
        $measuredAverages = [];
        foreach ($buckets as $indName => $values) {
            if ($values === []) {
                $indikatorScores[$indName] = [
                    'skor' => 0.0,
                    'terukur' => false,
                    'sampel' => 0,
                    'kategori_capaian' => 'Belum Terukur',
                ];

                continue;
            }

            $avg = round(array_sum($values) / count($values), 2);
            $measuredAverages[] = $avg;
            $indikatorScores[$indName] = [
                'skor' => $avg,
                'terukur' => true,
                'sampel' => count($values),
                'kategori_capaian' => $avg >= 80 ? 'Kuat' : ($avg >= 70 ? 'Memadai' : 'Perlu Penguatan'),
            ];
        }

        $skorKognitif = count($measuredAverages) > 0
            ? round(array_sum($measuredAverages) / count($measuredAverages), 2)
            : 0.0;

        $trenStatus = 'belum_ada_tren';
        $trenDelta = 0.0;
        if (count($timeline) >= 2) {
            $last = $timeline[count($timeline) - 1]['skor'];
            $prev = $timeline[count($timeline) - 2]['skor'];
            $trenDelta = round($last - $prev, 2);
            $trenStatus = $trenDelta >= 2.0 ? 'meningkat' : ($trenDelta <= -2.0 ? 'menurun' : 'stabil');
        } elseif (count($timeline) === 1) {
            $trenStatus = 'awal';
        }

        return [
            'siswa' => $siswa,
            'skor_kognitif' => $skorKognitif,
            'jumlah_pengujian' => count($timeline),
            'jumlah_asesmen_guru' => $assessments->count(),
            'jumlah_simulasi' => $simulations->count(),
            'indikator_scores' => $indikatorScores,
            'timeline' => $timeline,
            'tren_status' => $trenStatus,
            'tren_delta' => $trenDelta,
            'rekomendasi' => $recommendation,
            'simulasi_terakhir' => $simulations->last(),
        ];
    }

    private function mapToCognitiveIndicator(string $label, string $kategori): string
    {
        if (in_array($label, self::INDIKATOR_STANDAR, true)) {
            return $label;
        }

        $haystack = strtolower($label.' '.$kategori);

        if (str_contains($haystack, 'literasi') || str_contains($haystack, 'baca') || str_contains($haystack, 'bahasa') || str_contains($haystack, 'wacana')) {
            return 'Literasi Membaca';
        }
        if (str_contains($haystack, 'numerasi') || str_contains($haystack, 'hitung') || str_contains($haystack, 'matematika') || str_contains($haystack, 'kuantitatif')) {
            return 'Numerasi Kuantitatif';
        }
        if (str_contains($haystack, 'sains') || str_contains($haystack, 'ipa') || str_contains($haystack, 'ilmiah') || str_contains($haystack, 'eksperimen')) {
            return 'Penalaran Sains';
        }
        if (str_contains($haystack, 'logika') || str_contains($haystack, 'nalar') || str_contains($haystack, 'kritis')) {
            return 'Logika & Penalaran Kritis';
        }
        if (str_contains($haystack, 'evaluasi') || str_contains($haystack, 'kreatif') || str_contains($haystack, 'masalah') || str_contains($haystack, 'mandiri')) {
            return 'Evaluasi & Pemecahan Masalah';
        }

        return $label !== '' ? $label : 'Logika & Penalaran Kritis';
    }

    /**
     * @return array{kode: string, label: string, badge: string}
     */
    private function tentukanZona(float $skor, float $mean, bool $hasData): array
    {
        if (! $hasData) {
            return [
                'kode' => 'di_bawah_rata_rata',
                'label' => 'Belum Terpetakan',
                'badge' => 'tag-secondary',
            ];
        }

        $margin = 5.0;
        if ($skor >= $mean + $margin) {
            return [
                'kode' => 'di_atas_rata_rata',
                'label' => 'Di Atas Rata-rata',
                'badge' => 'tag-ok',
            ];
        }

        if ($skor <= $mean - $margin) {
            return [
                'kode' => 'di_bawah_rata_rata',
                'label' => 'Di Bawah Rata-rata',
                'badge' => 'tag-bad',
            ];
        }

        return [
            'kode' => 'rata_rata',
            'label' => 'Rata-rata',
            'badge' => 'tag-primary',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveKatalogIndikator(string $indikator): array
    {
        if (isset(self::KATALOG_TINDAK_LANJUT[$indikator])) {
            return self::KATALOG_TINDAK_LANJUT[$indikator];
        }

        $mapped = $this->mapToCognitiveIndicator($indikator, '');

        return self::KATALOG_TINDAK_LANJUT[$mapped] ?? self::KATALOG_TINDAK_LANJUT['Logika & Penalaran Kritis'];
    }

    private function extractTingkat(string $kelas): string
    {
        $normalized = strtoupper(trim($kelas));
        if ($normalized === '') {
            return 'Lainnya';
        }

        if (preg_match('/^(XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I)\b/', $normalized, $m)) {
            $romanMap = [
                'I' => '1', 'II' => '2', 'III' => '3', 'IV' => '4', 'V' => '5', 'VI' => '6',
                'VII' => '7', 'VIII' => '8', 'IX' => '9', 'X' => '10', 'XI' => '11', 'XII' => '12',
            ];

            return 'Kelas '.($romanMap[$m[1]] ?? $m[1]);
        }

        if (preg_match('/(\d{1,2})/', $normalized, $m)) {
            return 'Kelas '.(int) $m[1];
        }

        return 'Kelas '.$normalized;
    }

    private function inferFaseFromKelas(string $kelas): string
    {
        $tingkatLabel = $this->extractTingkat($kelas);
        if (preg_match('/(\d{1,2})/', $tingkatLabel, $m)) {
            $num = (int) $m[1];

            return match (true) {
                $num <= 2 => 'A',
                $num <= 4 => 'B',
                $num <= 6 => 'C',
                $num <= 9 => 'D',
                $num === 10 => 'E',
                default => 'F',
            };
        }

        return 'C';
    }

    private function heatmapSeverity(float $skor, float $target): string
    {
        if ($skor >= max(85.0, $target + 10.0)) {
            return 'unggul';
        }
        if ($skor >= $target) {
            return 'memadai';
        }
        if ($skor >= $target - 15.0) {
            return 'waspada';
        }

        return 'kritis';
    }
}
