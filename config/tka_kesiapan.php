<?php

/*
|--------------------------------------------------------------------------
| Pemantauan Kesiapan TKA (SIMANTAP)
|--------------------------------------------------------------------------
|
| Konfigurasi default modul "Pemantauan Kesiapan Siswa Menghadapi Tes
| Kemampuan Akademik (TKA) Kementerian". Semua nilai aturan dapat ditimpa
| per sekolah lewat tabel `tka_kesiapan_pengaturan` (menu Admin).
|
| PENTING — pelabelan & verifikasi:
|  - Metadata regulasi di `acuan_resmi` diverifikasi lewat penelusuran web
|    pada 2026-11-16, tetapi naskah PDF primer BELUM tersedia di repository.
|    Karena itu pemetaan indikator utama ditandai:
|    "Pemetaan sementara — perlu verifikasi sekolah".
|  - Indikator penunjang (IPAS, Penalaran Umum) adalah indikator internal
|    SIMANTAP, BUKAN bagian dari mata uji TKA SD/MI resmi.
|  - Semua ambang, minimum bukti, dan bobot adalah aturan internal SIMANTAP
|    yang dapat dikonfigurasi dan diaudit sekolah.
*/

return [
    // Feature flag: matikan untuk menyembunyikan menu & menolak seluruh rute kesiapan (404).
    'aktif' => env('TKA_KESIAPAN_AKTIF', true),

    'disclaimer' => 'Hasil ini adalah pemantauan internal untuk membantu perencanaan dukungan belajar, '
        .'bukan nilai, prediksi, atau hasil resmi TKA. Pemetaan dan aturan internal perlu ditinjau sekolah.',

    'acuan_resmi' => [
        'penyelenggara' => 'Badan Standar, Kurikulum, dan Asesmen Pendidikan (BSKAP) & Pusat Asesmen Pendidikan (Pusmendik), Kemendikdasmen',
        'dokumen' => 'Peraturan Kepala BSKAP Nomor 047/H/AN/2025 tentang Kerangka Asesmen Tes Kemampuan Akademik (TKA) Jenjang SD/MI/Sederajat dan SMP/MTs/Sederajat',
        'regulasi_pendukung' => 'Permendikdasmen Nomor 9 Tahun 2025 tentang Tes Kemampuan Akademik; Kepmendikdasmen Nomor 95/M/2025 tentang Pedoman Penyelenggaraan TKA',
        'tanggal_dokumen' => '2025-07-24',
        'url_portal' => 'https://pusmendik.kemendikdasmen.go.id/',
        'lokasi_dokumen' => 'Salinan PDF primer belum disimpan di repository; metadata diverifikasi melalui portal/ringkasan web.',
        'tanggal_verifikasi' => '2026-11-16',
        'dokumen_primer_tersedia' => false,
        'verifikasi' => 'Metadata regulasi (Perkaban BSKAP 047/H/AN/2025, ditetapkan 24 Juli 2025) diverifikasi pada 2026-11-16; '
            .'naskah PDF primer lengkap belum ada di repository. Pemetaan indikator berstatus "Pemetaan sementara — perlu verifikasi sekolah".',
        // Mata pelajaran TKA SD/MI menurut metadata ringkasan regulasi (sasaran kelas 6, tidak wajib, bukan penentu kelulusan).
        'mapel_diujikan' => ['bahasa_indonesia', 'matematika'],
    ],

    /*
     | Indikator pemantauan. `asal`:
     |   kerangka_terverifikasi = telah diverifikasi penuh oleh sekolah terhadap naskah primer
     |   pemetaan_sementara     = pemetaan internal selaras ringkasan regulasi — perlu verifikasi sekolah
     |   penunjang_internal     = indikator penunjang internal SIMANTAP — perlu validasi sekolah
     */
    'indikator' => [
        'bahasa_indonesia' => [
            'nama' => 'Bahasa Indonesia (literasi membaca)',
            'asal' => 'pemetaan_sementara',
            'catatan' => 'Pemetaan sementara — perlu verifikasi sekolah. Mengacu pada ringkasan Perkaban BSKAP 047/H/AN/2025: keterampilan membaca teks informasi dan teks fiksi (menemukan informasi, interpretasi/integrasi, evaluasi/refleksi).',
            'alias' => ['bahasa indonesia', 'b. indonesia', 'b indonesia', 'bahasa indonesia (literasi membaca)', 'literasi membaca', 'literasi', 'membaca'],
        ],
        'matematika' => [
            'nama' => 'Matematika',
            'asal' => 'pemetaan_sementara',
            'catatan' => 'Pemetaan sementara — perlu verifikasi sekolah. Mengacu pada ringkasan Perkaban BSKAP 047/H/AN/2025: bilangan, geometri dan pengukuran, serta data.',
            'alias' => ['matematika', 'mtk', 'numerasi', 'numerasi kuantitatif', 'numerasi & penalaran matematika'],
        ],
        'ipas' => [
            'nama' => 'IPAS (penunjang internal)',
            'asal' => 'penunjang_internal',
            'catatan' => 'Indikator penunjang internal SIMANTAP — perlu validasi sekolah. Bukan mata uji utama TKA SD/MI pada ringkasan regulasi.',
            'alias' => ['ipas', 'ilmu pengetahuan alam dan sosial', 'ipa', 'sains', 'penalaran sains'],
        ],
        'penalaran_umum' => [
            'nama' => 'Penalaran & pemecahan masalah (penunjang internal)',
            'asal' => 'penunjang_internal',
            'catatan' => 'Indikator penunjang internal SIMANTAP — perlu validasi sekolah. Bukan indikator resmi tersendiri.',
            'alias' => ['logika', 'penalaran', 'pemecahan masalah', 'evaluasi', 'logika & penalaran kritis', 'evaluasi & pemecahan masalah'],
        ],
    ],

    /*
     | Aturan hitung internal (default). Semua merupakan aturan internal SIMANTAP
     | yang dapat dikonfigurasi & diaudit sekolah lewat menu Admin.
     */
    'aturan' => [
        // Bukti sah minimum per indikator pada rentang/filter aktif.
        'min_bukti' => 3,
        // Bukti harus tersebar pada minimal sekian tanggal berbeda (hindari satu kali uji dianggap cukup).
        'min_tanggal_berbeda' => 2,
        // Ambang kecenderungan untuk indeks estimasi internal (0–100). Hanya dipakai bila bukti cukup.
        'ambang_perlu_dukungan' => 60.0,
        'ambang_cukup_mantap' => 75.0,
        // Perubahan antar-periode (poin estimasi) agar dianggap meningkat/menurun.
        'ambang_tren' => 5.0,
        // Minimum bukti per periode agar periode tersebut dihitung dalam tren faktual.
        'min_bukti_per_periode' => 2,
        // Keyakinan analisis (kualitas bukti).
        'keyakinan_target_jumlah' => 8,
        'keyakinan_kebaruan_hari' => 120,
        'keyakinan_min_jenis_sumber' => 2,
        'keyakinan_maks_sebaran' => 15.0,
        // Agregat kelas/rombel dengan siswa kurang dari ini disembunyikan (perlindungan privasi).
        'min_kelompok_agregat' => 3,
        // Bobot antar-JENIS sumber. Kosong = sama rata (1.0 per jenis sumber yang tersedia).
        // Dalam satu jenis sumber, bukti dirata-rata lebih dulu agar instrumen harian tidak mendominasi.
        'bobot_sumber' => [],
    ],

    /*
     | Sumber bukti dan skala asli masing-masing. Normalisasi ke 0–100 hanya untuk
     | ringkasan internal; instrumen berbeda TIDAK otomatis sebanding.
     */
    'sumber' => [
        'nilai_harian' => ['label' => 'Nilai harian/ulangan/tugas', 'skala' => '0–100 (nilai guru)', 'konteks' => 'Penilaian kelas oleh guru'],
        'kuis' => ['label' => 'Kuis', 'skala' => 'benar ÷ total soal', 'konteks' => 'Latihan/kuis singkat terstruktur'],
        'erapor' => ['label' => 'Nilai e-Rapor (nilai akhir)', 'skala' => '0–100', 'konteks' => 'Rekap capaian akhir semester'],
        'ujian' => ['label' => 'CBT / Tryout', 'skala' => 'skor ÷ skor maksimal', 'konteks' => 'Ujian terstandar sekolah (engine exam_* & fallback legacy)'],
        'asesmen_tka' => ['label' => 'Asesmen diagnostik TKA', 'skala' => 'skor dimensi ÷ maksimal', 'konteks' => 'Observasi/rubrik diagnostik sekolah'],
        'simulasi_tka' => ['label' => 'Simulasi TKA mandiri/adaptif', 'skala' => '0–100 (skor simulasi)', 'konteks' => 'Latihan simulasi (adaptif menyesuaikan tingkat kesulitan)'],
    ],

    // Katalog saran awal tindak lanjut (selalu dapat disunting guru).
    'rekomendasi' => [
        'bahasa_indonesia' => [
            'Latihan membaca teks informasi dan teks fiksi pendek (menemukan informasi tersurat lalu menyimpulkan ide pokok/tersirat) 2 kali per minggu; tinjau ulang setelah asesmen berikutnya.',
            'Di rumah: baca satu teks pendek bersama 15 menit, lalu diskusikan 2 pertanyaan tentang isi tersurat dan simpulan bacaan.',
        ],
        'matematika' => [
            'Latihan soal cerita bilangan (pecahan/perbandingan), geometri sederhana, dan membaca tabel/diagram 2 kali per minggu dengan langkah: pahami, rencanakan, hitung, periksa.',
            'Di rumah: ajak anak membaca tabel jadwal/daftar harga dan membandingkan pecahan atau takaran sederhana 10 menit per hari.',
        ],
        'ipas' => [
            'Latihan membaca data pengamatan sederhana dan menjelaskan hubungan sebab-akibat 1–2 kali per minggu.',
        ],
        'penalaran_umum' => [
            'Latihan mengurai soal masalah menjadi informasi diketahui, ditanyakan, dan langkah penyelesaian 1–2 kali per minggu.',
        ],
    ],
];
