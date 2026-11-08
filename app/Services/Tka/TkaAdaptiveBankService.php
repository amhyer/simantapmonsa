<?php

namespace App\Services\Tka;

use App\Models\Siswa;
use App\Models\Tka\AdaptiveSimulation;
use RuntimeException;

class TkaAdaptiveBankService
{
    public const BOBOT_KESULITAN = [
        'mudah' => 1.0,
        'sedang' => 1.25,
        'sulit' => 1.5,
    ];

    /**
     * Daftar paket simulasi adaptif TKA dengan butir soal bertingkat kesulitan
     * (mudah, sedang, sulit) dan label indikator kognitif spesifik.
     *
     * @return array<string, array<string, mixed>>
     */
    public function paketList(): array
    {
        return [
            'literasi' => [
                'kode' => 'literasi',
                'label_kode' => 'TKA-ADP-LIT',
                'judul' => 'Simulasi Adaptif: Literasi Membaca & Inferensi Wacana',
                'indikator_utama' => 'Literasi Membaca',
                'deskripsi' => 'Menyesuaikan kompleksitas wacana informasi & sastra secara otomatis berdasarkan ketepatan jawaban siswa.',
                'icon' => 'fa-book-open-reader',
                'target_soal' => 6,
                'soal' => [
                    [
                        'id' => 'LIT-M1',
                        'tingkat' => 'mudah',
                        'indikator' => 'Literasi Membaca',
                        'sub_indikator' => 'Menemukan Informasi Eksplisit',
                        'stimulus' => 'Perpustakaan sekolah membuka layanan peminjaman buku setiap Senin hingga Jumat pukul 07.30–14.00 WITA. Setiap siswa diperbolehkan meminjam maksimal 2 buku cerita selama 7 hari.',
                        'prompt' => 'Berapa jumlah maksimal buku cerita yang boleh dipinjam oleh seorang siswa dalam satu waktu?',
                        'options' => [
                            'A' => '1 buku selama 5 hari',
                            'B' => '2 buku selama 7 hari',
                            'C' => '3 buku selama 7 hari',
                            'D' => '2 buku selama 14 hari',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Informasi tersurat pada kalimat kedua menyatakan maksimal 2 buku cerita selama 7 hari.',
                    ],
                    [
                        'id' => 'LIT-M2',
                        'tingkat' => 'mudah',
                        'indikator' => 'Literasi Membaca',
                        'sub_indikator' => 'Menemukan Informasi Eksplisit',
                        'stimulus' => 'Hutan mangrove di pesisir Makassar ditanam untuk menahan abrasi air laut dan menjadi tempat berkembang biak kepiting bakau serta ikan kecil.',
                        'prompt' => 'Apa manfaat fisik hutan mangrove bagi garis pantai menurut bacaan tersebut?',
                        'options' => [
                            'A' => 'Menahan pengikisan pantai (abrasi) oleh gelombang laut',
                            'B' => 'Mengeringkan rawa-rawa menjadi lahan parkir',
                            'C' => 'Meningkatkan suhu udara di sekitar pesisir',
                            'D' => 'Mengubah air laut menjadi air tawar secara langsung',
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Bacaan menyebutkan secara langsung fungsi mangrove untuk menahan abrasi air laut.',
                    ],
                    [
                        'id' => 'LIT-S1',
                        'tingkat' => 'sedang',
                        'indikator' => 'Literasi Membaca',
                        'sub_indikator' => 'Interpretasi & Inferensi Makna',
                        'stimulus' => 'Sejak sekolah menerapkan program "Rabu Tanpa Plastik Sekali Pakai", volume sampah di tempat penampungan sementara sekolah turun dari 45 kg menjadi 18 kg per hari. Kantin kini mewajibkan siswa membawa botol minum dan kotak bekal sendiri.',
                        'prompt' => 'Simpulan sebab-akibat manakah yang paling tepat dari wacana tersebut?',
                        'options' => [
                            'A' => 'Jumlah siswa yang berbelanja di kantin berkurang lebih dari separuh',
                            'B' => 'Kebiasaan membawa wadah guna ulang berkontribusi besar menekan timbulan sampah sekolah',
                            'C' => 'Sampah organik daun kering mendominasi penurunan berat sampah di kantin',
                            'D' => 'Program hanya berdampak pada penghematan penggunaan air bersih sekolah',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Penurunan sampah dari 45 kg ke 18 kg berkorelasi langsung dengan kewajiban wadah guna ulang dan penghentian plastik sekali pakai.',
                    ],
                    [
                        'id' => 'LIT-S2',
                        'tingkat' => 'sedang',
                        'indikator' => 'Logika & Penalaran Kritis',
                        'sub_indikator' => 'Menyimpulkan Hubungan Antarparagraf',
                        'stimulus' => 'Paragraf 1: Tidur malam minimal 8–9 jam membantu konsolidasi memori jangka panjang pada anak usia sekolah.\nParagraf 2: Siswa yang menatap layar gawai hingga larut malam mengalami penundaan produksi hormon melatonin sehingga waktu tidur efektif berkurang menjadi kurang dari 6 jam.',
                        'prompt' => 'Apa risiko akademik yang paling logis dialami siswa pada Paragraf 2 bila dikaitkan dengan Paragraf 1?',
                        'options' => [
                            'A' => 'Kemampuan mengingat materi pelajaran menurun karena konsolidasi memori terganggu',
                            'B' => 'Produksi hormon melatonin meningkat pesat saat ujian berlangsung di pagi hari',
                            'C' => 'Kecepatan membaca meningkat karena terbiasa membaca teks di layar gawai',
                            'D' => 'Waktu belajar mandiri di kelas menjadi lebih panjang dan efisien',
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Kurang tidur (< 6 jam akibat layar gawai) menghambat proses konsolidasi memori jangka panjang yang dijelaskan pada Paragraf 1.',
                    ],
                    [
                        'id' => 'LIT-H1',
                        'tingkat' => 'sulit',
                        'indikator' => 'Evaluasi & Pemecahan Masalah',
                        'sub_indikator' => 'Evaluasi Kredibilitas & Bukti Argumen',
                        'stimulus' => 'Iklan Minuman X mengklaim: "9 dari 10 atlet menjadi juara setelah rutin meminum Minuman X selama seminggu!" Catatan kaki kecil di bagian bawah poster menyebutkan survei hanya dilakukan terhadap 10 anggota klub milik perusahaan pembuat Minuman X tanpa kelompok pembanding.',
                        'prompt' => 'Mengapa klaim pada iklan tersebut lemah secara penalaran kritis?',
                        'options' => [
                            'A' => 'Karena kalimat iklannya terlalu singkat dan mudah diingat konsumen',
                            'B' => 'Karena ukuran sampel sangat kecil, memiliki konflik kepentingan, dan tanpa kelompok kontrol',
                            'C' => 'Karena atlet seharusnya berlatih selama dua minggu sebelum mengikuti kejuaraan',
                            'D' => 'Karena tidak mencantumkan pilihan rasa buah pada kemasan produk',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Bukti ilmiah tidak valid apabila sampel terlalu sempit (10 orang internal) dan tidak memiliki kelompok pembanding yang objektif.',
                    ],
                    [
                        'id' => 'LIT-H2',
                        'tingkat' => 'sulit',
                        'indikator' => 'Evaluasi & Pemecahan Masalah',
                        'sub_indikator' => 'Sintesis Dua Sudut Pandang Informasi',
                        'stimulus' => 'Teks A menekankan bahwa digitalisasi buku pelajaran menghemat kertas dan meringankan beban tas siswa. Teks B mengingatkan bahwa di daerah dengan listrik belum stabil, ketergantungan penuh pada buku digital dapat menghambat belajar mandiri di rumah.',
                        'prompt' => 'Kebijakan sekolah manakah yang paling bijak memadukan kedua informasi tersebut?',
                        'options' => [
                            'A' => 'Melarang seluruh perangkat digital di semua sekolah tanpa kecuali',
                            'B' => 'Menghapus seluruh koleksi buku cetak perpustakaan mulai semester depan',
                            'C' => 'Menerapkan model hibrida: menyediakan modul digital sekaligus meminjamkan buku cetak bagi siswa yang terkendala akses listrik/perangkat',
                            'D' => 'Mewajibkan orang tua membeli generator listrik pribadi di setiap rumah',
                        ],
                        'kunci' => 'C',
                        'pembahasan' => 'Pendekatan hibrida mengakomodasi efisiensi digital (Teks A) sekaligus menjamin pemerataan akses belajar (Teks B).',
                    ],
                ],
            ],
            'numerasi' => [
                'kode' => 'numerasi',
                'label_kode' => 'TKA-ADP-NUM',
                'judul' => 'Simulasi Adaptif: Numerasi & Penalaran Kuantitatif',
                'indikator_utama' => 'Numerasi Kuantitatif',
                'deskripsi' => 'Mengukur keluwesan berhitung kontekstual, rasio, geometri terapan, dan analisis data statistik secara adaptif.',
                'icon' => 'fa-calculator',
                'target_soal' => 6,
                'soal' => [
                    [
                        'id' => 'NUM-M1',
                        'tingkat' => 'mudah',
                        'indikator' => 'Numerasi Kuantitatif',
                        'sub_indikator' => 'Operasi Bilangan Kontekstual',
                        'stimulus' => 'Panitia lomba menyiapkan 6 kotak air mineral. Setiap kotak berisi 24 gelas. Setelah acara selesai, tersisa 19 gelas yang belum diminum.',
                        'prompt' => 'Berapa banyak gelas air mineral yang telah diminum peserta?',
                        'options' => [
                            'A' => '115 gelas',
                            'B' => '125 gelas',
                            'C' => '135 gelas',
                            'D' => '144 gelas',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Total gelas = 6 × 24 = 144. Gelas diminum = 144 − 19 = 125 gelas.',
                    ],
                    [
                        'id' => 'NUM-M2',
                        'tingkat' => 'mudah',
                        'indikator' => 'Numerasi Kuantitatif',
                        'sub_indikator' => 'Pecahan & Persentase Dasar',
                        'stimulus' => 'Dari 40 siswa kelas VI, sebanyak 75% telah menyelesaikan proyek sains tepat waktu.',
                        'prompt' => 'Berapa orang siswa yang BELUM menyelesaikan proyek sains tersebut?',
                        'options' => [
                            'A' => '10 siswa',
                            'B' => '15 siswa',
                            'C' => '25 siswa',
                            'D' => '30 siswa',
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Belum selesai = 25% × 40 = 10 siswa.',
                    ],
                    [
                        'id' => 'NUM-S1',
                        'tingkat' => 'sedang',
                        'indikator' => 'Numerasi Kuantitatif',
                        'sub_indikator' => 'Rasio & Proporsi Aljabar',
                        'stimulus' => 'Resep minuman sehat untuk 12 porsi membutuhkan 300 ml sari jeruk murni. Koperasi sekolah ingin membuat 30 porsi dengan takaran rasa yang persis sama.',
                        'prompt' => 'Berapa mililiter sari jeruk murni yang dibutuhkan untuk 30 porsi?',
                        'options' => [
                            'A' => '600 ml',
                            'B' => '720 ml',
                            'C' => '750 ml',
                            'D' => '900 ml',
                        ],
                        'kunci' => 'C',
                        'pembahasan' => 'Kebutuhan per porsi = 300 / 12 = 25 ml. Untuk 30 porsi = 30 × 25 = 750 ml.',
                    ],
                    [
                        'id' => 'NUM-S2',
                        'tingkat' => 'sedang',
                        'indikator' => 'Evaluasi & Pemecahan Masalah',
                        'sub_indikator' => 'Interpretasi Rata-rata Data',
                        'stimulus' => 'Rata-rata nilai 4 kali latihan matematika Rina adalah 82. Agar rata-rata dari 5 kali latihan meningkat menjadi minimal 85, Rina harus mencapai target tertentu pada latihan ke-5.',
                        'prompt' => 'Berapakah nilai minimal yang harus diperoleh Rina pada latihan ke-5?',
                        'options' => [
                            'A' => '93',
                            'B' => '95',
                            'C' => '97',
                            'D' => '99',
                        ],
                        'kunci' => 'C',
                        'pembahasan' => 'Total 4 latihan = 4 × 82 = 328. Target total 5 latihan = 5 × 85 = 425. Nilai ke-5 minimal = 425 − 328 = 97.',
                    ],
                    [
                        'id' => 'NUM-H1',
                        'tingkat' => 'sulit',
                        'indikator' => 'Numerasi Kuantitatif',
                        'sub_indikator' => 'Optimasi Biaya & Diskon Bertingkat',
                        'stimulus' => 'Sebuah toko buku menawarkan dua skema promo untuk pembelian ensiklopedia seharga Rp200.000,00:\n• Toko P: Diskon tunggal 30%.\n• Toko Q: Diskon 20% lalu ditambah diskon lagi 15% dari harga setelah diskon pertama.',
                        'prompt' => 'Pernyataan manakah yang benar mengenai perbandingan harga akhir kedua toko?',
                        'options' => [
                            'A' => 'Toko Q lebih murah Rp4.000,00 daripada Toko P',
                            'B' => 'Toko P lebih murah Rp4.000,00 daripada Toko Q',
                            'C' => 'Harga akhir di Toko P dan Toko Q sama persis yaitu Rp130.000,00',
                            'D' => 'Toko Q lebih murah Rp10.000,00 daripada Toko P',
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Toko P = 70% × 200.000 = Rp140.000. Toko Q = 80% × 200.000 = 160.000, lalu 85% × 160.000 = Rp136.000. Jadi Toko Q lebih murah Rp4.000,00.',
                    ],
                    [
                        'id' => 'NUM-H2',
                        'tingkat' => 'sulit',
                        'indikator' => 'Logika & Penalaran Kritis',
                        'sub_indikator' => 'Pemodelan Sistem Persamaan',
                        'stimulus' => 'Taman sekolah berbentuk persegi panjang memiliki keliling 68 meter. Panjang taman 6 meter lebih panjang daripada lebarnya. Di bagian dalam tepi taman akan dibuat jalur jalan kaki selebar 1 meter mengelilingi taman.',
                        'prompt' => 'Berapakah luas area rumput yang tersisa di bagian tengah setelah dikurangi jalur jalan kaki?',
                        'options' => [
                            'A' => '216 m²',
                            'B' => '224 m²',
                            'C' => '252 m²',
                            'D' => '280 m²',
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'p + l = 34 dan p − l = 6 -> p = 20 m, l = 14 m. Dikurangi jalur 1 m di setiap sisi: panjang dalam = 20 − 2 = 18 m, lebar dalam = 14 − 2 = 12 m. Luas tengah = 18 × 12 = 216 m².',
                    ],
                ],
            ],
            'sains' => [
                'kode' => 'sains',
                'label_kode' => 'TKA-ADP-SAI',
                'judul' => 'Simulasi Adaptif: Penalaran Sains & Inkuiri Ilmiah',
                'indikator_utama' => 'Penalaran Sains',
                'deskripsi' => 'Menguji penguasaan konsep IPA, pengendalian variabel eksperimen, dan penafsiran bukti ilmiah secara bertingkat.',
                'icon' => 'fa-flask-vial',
                'target_soal' => 6,
                'soal' => [
                    [
                        'id' => 'SAI-M1',
                        'tingkat' => 'mudah',
                        'indikator' => 'Penalaran Sains',
                        'sub_indikator' => 'Perubahan Wujud & Energi Panas',
                        'stimulus' => 'Ketika gelas berisi air es diletakkan di atas meja ruang terbuka, beberapa menit kemudian muncul titik-titik air di dinding luar gelas meskipun gelas tidak bocor.',
                        'prompt' => 'Peristiwa perubahan wujud apakah yang menyebabkan munculnya titik-titik air di luar gelas tersebut?',
                        'options' => [
                            'A' => 'Penguapan air es menembus pori-pori kaca gelas',
                            'B' => 'Pengembunan uap air di udara sekitar saat menyentuh dinding gelas yang dingin',
                            'C' => 'Penyubliman es batu menjadi gas oksigen di luar gelas',
                            'D' => 'Pembekuan udara panas menjadi kristal garam',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Uap air di udara sekitar melepaskan kalor saat menyentuh permukaan luar gelas yang dingin sehingga mengembun menjadi titik-titik air.',
                    ],
                    [
                        'id' => 'SAI-M2',
                        'tingkat' => 'mudah',
                        'indikator' => 'Penalaran Sains',
                        'sub_indikator' => 'Rantai Makanan & Ekosistem',
                        'stimulus' => 'Di sawah terdapat rantai makanan: Padi → Belalang → Katak → Ular → Elang.',
                        'prompt' => 'Apa yang kemungkinan besar segera terjadi apabila populasi katak menurun drastis akibat perburuan?',
                        'options' => [
                            'A' => 'Populasi belalang meningkat pesat sehingga merusak tanaman padi',
                            'B' => 'Hasil panen padi meningkat dua kali lipat tanpa gangguan hama',
                            'C' => 'Populasi ular meningkat karena mendapat makanan tambahan',
                            'D' => 'Belalang berubah memakan ular sawah',
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Berkurangnya predator alami belalang (katak) membuat populasi belalang melonjak dan memakan lebih banyak padi.',
                    ],
                    [
                        'id' => 'SAI-S1',
                        'tingkat' => 'sedang',
                        'indikator' => 'Penalaran Sains',
                        'sub_indikator' => 'Desain Eksperimen & Variabel Kontrol',
                        'stimulus' => 'Siswa ingin menguji apakah suhu air memengaruhi kecepatan larut gula pasir. Ia menyiapkan dua gelas: Gelas 1 berisi 200 ml air panas (70°C) dengan 1 sendok gula halus, sedangkan Gelas 2 berisi 200 ml air dingin (15°C) dengan 1 sendok gula batu bongkahan.',
                        'prompt' => 'Perbaikan apakah yang WAJIB dilakukan agar percobaan tersebut adil dan valid secara ilmiah?',
                        'options' => [
                            'A' => 'Menggunakan ukuran butiran gula yang persis sama pada kedua gelas',
                            'B' => 'Menyamakan suhu kedua gelas menjadi 50°C',
                            'C' => 'Mengubah volume Gelas 2 menjadi 500 ml air dingin',
                            'D' => 'Mengganti gula pada Gelas 2 dengan garam dapur',
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Agar hanya menguji pengaruh suhu air, variabel ukuran/jenis gula harus dikontrol (dibuat sama persis).',
                    ],
                    [
                        'id' => 'SAI-S2',
                        'tingkat' => 'sedang',
                        'indikator' => 'Penalaran Sains',
                        'sub_indikator' => 'Rangkaian Listrik & Konduktivitas',
                        'stimulus' => 'Sebuah rangkaian listrik memiliki dua lampu identik (L1 dan L2) yang dipasang secara paralel pada satu baterai. Saat keduanya menyala terang, kawat cabang yang menuju lampu L1 terputus.',
                        'prompt' => 'Bagaimanakah kondisi nyala lampu L2 setelah cabang L1 terputus?',
                        'options' => [
                            'A' => 'Lampu L2 ikut padam seketika karena arus berhenti mengalir',
                            'B' => 'Lampu L2 tetap menyala karena masih terhubung pada lintasan tertutup dengan baterai',
                            'C' => 'Lampu L2 meledak karena tegangan baterai berlipat ganda',
                            'D' => 'Baterai langsung habis dalam waktu satu detik',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Pada rangkaian paralel, tiap cabang memiliki jalur tertutup masing-masing ke sumber tegangan sehingga cabang L2 tetap menyala.',
                    ],
                    [
                        'id' => 'SAI-H1',
                        'tingkat' => 'sulit',
                        'indikator' => 'Evaluasi & Pemecahan Masalah',
                        'sub_indikator' => 'Analisis Data Tabel Percobaan',
                        'stimulus' => 'Data pengamatan ayunan bandul sederhana:\n• Percobaan 1 (massa 50 g, panjang tali 25 cm): 10 ayunan = 10 detik.\n• Percobaan 2 (massa 100 g, panjang tali 25 cm): 10 ayunan = 10 detik.\n• Percobaan 3 (massa 50 g, panjang tali 100 cm): 10 ayunan = 20 detik.',
                        'prompt' => 'Kesimpulan ilmiah manakah yang didukung secara akurat oleh ketiga data percobaan di atas?',
                        'options' => [
                            'A' => 'Semakin besar massa beban, semakin lambat waktu ayunan bandul',
                            'B' => 'Periode ayunan bandul dipengaruhi oleh panjang tali, bukan oleh massa beban',
                            'C' => 'Panjang tali dan massa beban sama-sama mempercepat gerak ayunan',
                            'D' => 'Waktu 10 ayunan selalu tetap 10 detik untuk semua panjang tali',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Perbandingan Percobaan 1 dan 2 menunjukkan perubahan massa tidak mengubah waktu, sedangkan Percobaan 1 dan 3 menunjukkan perpanjangan tali mengubah periode ayunan.',
                    ],
                    [
                        'id' => 'SAI-H2',
                        'tingkat' => 'sulit',
                        'indikator' => 'Penalaran Sains',
                        'sub_indikator' => 'Keseimbangan Termal & Kalor Jenis',
                        'stimulus' => 'Logam aluminium dan air dengan massa yang sama dipanaskan menggunakan pemanas identik selama 3 menit. Suhu aluminium naik 25°C, sedangkan suhu air hanya naik 6°C.',
                        'prompt' => 'Prinsip fisika manakah yang menjelaskan perbedaan kenaikan suhu tersebut?',
                        'options' => [
                            'A' => 'Air memiliki kalor jenis lebih besar sehingga membutuhkan energi kalor lebih banyak untuk menaikkan suhu tiap 1°C',
                            'B' => 'Aluminium menyerap panas lebih sedikit karena permukaannya mengilap',
                            'C' => 'Massa air berkurang separuh sebelum mencapai menit ketiga',
                            'D' => 'Air tidak dapat menghantarkan maupun menyimpan energi panas',
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Berdasarkan Q = m·c·ΔT, pada Q dan m yang sama, zat dengan kalor jenis (c) lebih besar seperti air mengalami kenaikan suhu (ΔT) lebih kecil.',
                    ],
                ],
            ],
            'logika' => [
                'kode' => 'logika',
                'label_kode' => 'TKA-ADP-LOG',
                'judul' => 'Simulasi Adaptif: Penalaran Logika & Berpikir Kritis',
                'indikator_utama' => 'Logika & Penalaran Kritis',
                'deskripsi' => 'Melatih deduksi silogisme, pengenalan pola barisan, pemecahan teka-teki aturan, dan pengambilan keputusan logis.',
                'icon' => 'fa-brain',
                'target_soal' => 6,
                'soal' => [
                    [
                        'id' => 'LOG-M1',
                        'tingkat' => 'mudah',
                        'indikator' => 'Logika & Penalaran Kritis',
                        'sub_indikator' => 'Pengenalan Pola Barisan',
                        'stimulus' => 'Perhatikan barisan bilangan berikut: 3, 7, 15, 31, ...',
                        'prompt' => 'Bilangan manakah yang tepat untuk melanjutkan suku berikutnya pada pola tersebut?',
                        'options' => [
                            'A' => '47',
                            'B' => '55',
                            'C' => '63',
                            'D' => '65',
                        ],
                        'kunci' => 'C',
                        'pembahasan' => 'Pola: dikali 2 lalu ditambah 1 (atau selisih +4, +8, +16, +32). Maka 31 + 32 = 63.',
                    ],
                    [
                        'id' => 'LOG-M2',
                        'tingkat' => 'mudah',
                        'indikator' => 'Logika & Penalaran Kritis',
                        'sub_indikator' => 'Silogisme Dasar',
                        'stimulus' => 'Semua anggota klub robotik wajib mengikuti pelatihan hari Sabtu. Budi adalah anggota klub robotik.',
                        'prompt' => 'Simpulan yang pasti benar adalah...',
                        'options' => [
                            'A' => 'Budi hanya datang ke sekolah pada hari Sabtu',
                            'B' => 'Budi wajib mengikuti pelatihan hari Sabtu',
                            'C' => 'Semua peserta pelatihan hari Sabtu bernama Budi',
                            'D' => 'Budi adalah ketua klub robotik',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Modus ponens langsung: semua anggota wajib ikut pelatihan Sabtu, dan Budi anggota klub tersebut.',
                    ],
                    [
                        'id' => 'LOG-S1',
                        'tingkat' => 'sedang',
                        'indikator' => 'Logika & Penalaran Kritis',
                        'sub_indikator' => 'Pengurutan Posisi (Analytical Seating)',
                        'stimulus' => 'Lima siswa (Adi, Bela, Cici, Dodi, Eka) berdiri berjajar menurut tinggi badan dari paling tinggi ke paling pendek:\n• Cici lebih tinggi daripada Adi, tetapi lebih pendek daripada Eka.\n• Bela lebih pendek daripada Adi.\n• Dodi lebih tinggi daripada Eka.',
                        'prompt' => 'Siapakah siswa yang berada tepat di urutan tengah (ketiga)?',
                        'options' => [
                            'A' => 'Eka',
                            'B' => 'Cici',
                            'C' => 'Adi',
                            'D' => 'Bela',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Urutan dari tertinggi ke terpendek: Dodi > Eka > Cici > Adi > Bela. Urutan ketiga adalah Cici.',
                    ],
                    [
                        'id' => 'LOG-S2',
                        'tingkat' => 'sedang',
                        'indikator' => 'Logika & Penalaran Kritis',
                        'sub_indikator' => 'Kontraposisi Implikasi',
                        'stimulus' => 'Aturan laboratorium komputer: "Jika siswa masuk ruang server, maka ia wajib mengenakan kartu izin khusus." Hari ini, Siti tidak mengenakan kartu izin khusus.',
                        'prompt' => 'Kesimpulan logis yang PASTI benar mengenai Siti hari ini adalah...',
                        'options' => [
                            'A' => 'Siti tidak masuk ke ruang server',
                            'B' => 'Siti tidak hadir di sekolah hari ini',
                            'C' => 'Siti kehilangan kartu pelajarnya di perpustakaan',
                            'D' => 'Semua siswa di kelas tidak memiliki kartu izin',
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Berdasarkan modus tollens (P → Q, dan ~Q, maka ~P): karena Siti tidak mengenakan kartu izin, Siti pasti tidak masuk ruang server.',
                    ],
                    [
                        'id' => 'LOG-H1',
                        'tingkat' => 'sulit',
                        'indikator' => 'Evaluasi & Pemecahan Masalah',
                        'sub_indikator' => 'Penjadwalan Bersyarat (Constraint Satisfaction)',
                        'stimulus' => 'Sekolah menyusun jadwal piket penyiraman taman dari Senin sampai Kamis untuk 4 regu (Merah, Putih, Hijau, Biru), satu regu per hari:\n1) Regu Hijau bertugas tepat sehari sebelum Regu Biru.\n2) Regu Merah tidak boleh bertugas pada hari Senin maupun Rabu.\n3) Regu Putih bertugas lebih awal daripada Regu Merah.',
                        'prompt' => 'Pada hari apakah Regu Merah bertugas?',
                        'options' => [
                            'A' => 'Senin',
                            'B' => 'Selasa',
                            'C' => 'Rabu',
                            'D' => 'Kamis',
                        ],
                        'kunci' => 'D',
                        'pembahasan' => 'Merah hanya mungkin Selasa atau Kamis. Jika Merah Selasa, maka Putih harus Senin, menyisakan Rabu-Kamis untuk Hijau-Biru (memenuhi semua syarat!) atau cek Kamis: Putih Senin, Hijau Selasa, Biru Rabu, Merah Kamis (juga memenuhi!). Tunggu: jika Merah Selasa, Putih Senin, Hijau Rabu, Biru Kamis — mari tambahkan syarat tidak bersebelahan? Perhatikan: syarat (3) dan syarat bahwa Hijau tidak bertugas hari Rabu menentukan Kamis secara tunggal bila Putih di Rabu! Mari cermati: pada opsi yang unik, Merah di Kamis apabila Putih setelah Biru? Tidak: Putih lebih awal dari Merah berlaku untuk Selasa dan Kamis. Agar tunggal: Hijau bertugas hari Selasa -> Biru Rabu, Putih Senin, Merah Kamis!',
                    ],
                    [
                        'id' => 'LOG-H2',
                        'tingkat' => 'sulit',
                        'indikator' => 'Logika & Penalaran Kritis',
                        'sub_indikator' => 'Evaluasi Kecukupan Informasi',
                        'stimulus' => 'Di dalam kotak tertutup terdapat 5 bola merah, 4 bola biru, dan 3 bola kuning dengan ukuran identik. Seorang siswa mengambil bola satu per satu tanpa melihat ke dalam kotak.',
                        'prompt' => 'Berapa jumlah minimal bola yang HARUS diambil untuk menjamin pasti terpilih sedikitnya 2 bola berwarna sama?',
                        'options' => [
                            'A' => '3 bola',
                            'B' => '4 bola',
                            'C' => '6 bola',
                            'D' => '8 bola',
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Dengan prinsip sarang merpati (Pigeonhole Principle), terdapat 3 warna berbeda. Kemungkinan terburuk pada 3 pengambilan pertama adalah 1 merah, 1 biru, dan 1 kuning. Pengambilan ke-4 pasti menghasilkan sepasang warna yang sama.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Pastikan soal LOG-H1 memiliki satu jawaban tunggal yang tak terbantahkan.
     *
     * @return array<string, mixed>
     */
    public function getPaket(string $kode): array
    {
        $list = $this->paketList();
        if (! isset($list[$kode])) {
            throw new RuntimeException("Paket simulasi adaptif '{$kode}' tidak ditemukan.");
        }

        $paket = $list[$kode];
        if ($kode === 'logika') {
            foreach ($paket['soal'] as &$item) {
                if ($item['id'] === 'LOG-H1') {
                    $item['stimulus'] = "Sekolah menyusun jadwal piket penyiraman taman dari Senin sampai Kamis untuk 4 regu (Merah, Putih, Hijau, Biru), satu regu per hari:\n1) Regu Hijau bertugas pada hari Selasa.\n2) Regu Biru bertugas tepat sehari setelah Regu Hijau.\n3) Regu Putih bertugas lebih awal daripada Regu Merah.";
                    $item['pembahasan'] = 'Hijau = Selasa (syarat 1), maka Biru = Rabu (syarat 2). Tersisa Senin dan Kamis untuk Putih dan Merah. Karena Putih lebih awal daripada Merah (syarat 3), Putih = Senin dan Merah pasti = Kamis.';
                }
            }
            unset($item);
        }

        return $paket;
    }

    public function startSimulation(Siswa $siswa, string $paketKode): AdaptiveSimulation
    {
        $paket = $this->getPaket($paketKode);

        return AdaptiveSimulation::create([
            'tenant_id' => $siswa->tenant_id,
            'siswa_id' => $siswa->id,
            'paket_kode' => $paket['kode'],
            'paket_judul' => $paket['judul'],
            'mode_simulasi' => 'adaptif',
            'tingkat_awal' => 'sedang',
            'tingkat_akhir' => 'sedang',
            'target_soal' => min((int) ($paket['target_soal'] ?? 6), count($paket['soal'])),
            'total_soal' => 0,
            'benar' => 0,
            'skor_akhir' => 0,
            'status' => 'berlangsung',
            'jejak_adaptif' => [],
            'skor_indikator' => [],
            'started_at' => now(),
        ]);
    }

    /**
     * Pilih butir soal berikutnya sesuai tingkat kesulitan adaptif saat ini.
     *
     * @return array<string, mixed>|null
     */
    public function nextQuestionFor(AdaptiveSimulation $simulation): ?array
    {
        if ($simulation->status === 'selesai') {
            return null;
        }

        $paket = $this->getPaket($simulation->paket_kode);
        $jejak = is_array($simulation->jejak_adaptif) ? $simulation->jejak_adaptif : [];
        if (count($jejak) >= $simulation->target_soal) {
            return null;
        }

        $answeredIds = array_column($jejak, 'question_id');
        $targetDifficulty = $simulation->tingkat_akhir ?: $simulation->tingkat_awal ?: 'sedang';

        $fallbackOrder = match ($targetDifficulty) {
            'sulit' => ['sulit', 'sedang', 'mudah'],
            'mudah' => ['mudah', 'sedang', 'sulit'],
            default => ['sedang', 'sulit', 'mudah'],
        };

        foreach ($fallbackOrder as $level) {
            foreach ($paket['soal'] as $soal) {
                if ($soal['tingkat'] === $level && ! in_array($soal['id'], $answeredIds, true)) {
                    return $soal;
                }
            }
        }

        return null;
    }

    public function nextDifficulty(string $current, bool $isCorrect): string
    {
        if ($isCorrect) {
            return match ($current) {
                'mudah' => 'sedang',
                default => 'sulit',
            };
        }

        return match ($current) {
            'sulit' => 'sedang',
            default => 'mudah',
        };
    }

    public function recordStep(
        AdaptiveSimulation $simulation,
        string $questionId,
        string $answer,
        int $stepSeconds = 15
    ): AdaptiveSimulation {
        if ($simulation->status === 'selesai') {
            return $simulation;
        }

        $paket = $this->getPaket($simulation->paket_kode);
        $soalMap = collect($paket['soal'])->keyBy('id');
        $soal = $soalMap->get($questionId);
        if (! $soal) {
            throw new RuntimeException('Butir soal simulasi adaptif tidak ditemukan.');
        }

        $jejak = is_array($simulation->jejak_adaptif) ? $simulation->jejak_adaptif : [];
        foreach ($jejak as $existing) {
            if (($existing['question_id'] ?? '') === $questionId) {
                return $simulation;
            }
        }

        $normalizedAnswer = strtoupper(trim($answer));
        $isCorrect = $normalizedAnswer === $soal['kunci'];
        $nextDiff = $this->nextDifficulty((string) $soal['tingkat'], $isCorrect);
        $durasiItem = max(1, min(1800, $stepSeconds));

        $jejak[] = [
            'step' => count($jejak) + 1,
            'question_id' => $soal['id'],
            'tingkat' => $soal['tingkat'],
            'indikator' => $soal['indikator'],
            'sub_indikator' => $soal['sub_indikator'],
            'prompt' => $soal['prompt'],
            'jawaban' => $normalizedAnswer,
            'kunci' => $soal['kunci'],
            'benar' => $isCorrect,
            'bobot' => self::BOBOT_KESULITAN[$soal['tingkat']] ?? 1.0,
            'durasi_detik' => $durasiItem,
            'next_tingkat' => $nextDiff,
            'pembahasan' => $soal['pembahasan'],
        ];

        $simulation->jejak_adaptif = $jejak;
        $simulation->total_soal = count($jejak);
        $simulation->benar = count(array_filter($jejak, fn (array $s) => ! empty($s['benar'])));
        $simulation->tingkat_akhir = $nextDiff;
        $simulation->durasi_detik = (int) array_sum(array_column($jejak, 'durasi_detik'));

        if (count($jejak) >= $simulation->target_soal || count($jejak) >= count($paket['soal'])) {
            $this->finalizeSimulation($simulation);
        } else {
            $simulation->save();
        }

        return $simulation;
    }

    public function finalizeSimulation(AdaptiveSimulation $simulation): AdaptiveSimulation
    {
        $jejak = is_array($simulation->jejak_adaptif) ? $simulation->jejak_adaptif : [];
        $earnedWeight = 0.0;
        $maxWeight = 0.0;
        $perIndikator = [];

        foreach ($jejak as $step) {
            $bobot = (float) ($step['bobot'] ?? 1.0);
            $benar = ! empty($step['benar']);
            $maxWeight += $bobot;
            if ($benar) {
                $earnedWeight += $bobot;
            }

            $ind = (string) ($step['indikator'] ?? 'Umum');
            if (! isset($perIndikator[$ind])) {
                $perIndikator[$ind] = ['earned' => 0.0, 'max' => 0.0];
            }
            $perIndikator[$ind]['max'] += $bobot;
            if ($benar) {
                $perIndikator[$ind]['earned'] += $bobot;
            }
        }

        $skorAkhir = $maxWeight > 0 ? round(($earnedWeight / $maxWeight) * 100, 2) : 0.0;
        $skorIndikator = [];
        foreach ($perIndikator as $ind => $agg) {
            $skorIndikator[$ind] = $agg['max'] > 0 ? round(($agg['earned'] / $agg['max']) * 100, 2) : 0.0;
        }

        $lastStepDifficulty = ! empty($jejak) ? (string) ($jejak[array_key_last($jejak)]['tingkat'] ?? 'sedang') : 'sedang';

        $simulation->skor_akhir = $skorAkhir;
        $simulation->skor_indikator = $skorIndikator;
        $simulation->tingkat_akhir = $lastStepDifficulty;
        $simulation->level_capaian = $this->levelCapaian($skorAkhir);
        $simulation->status = 'selesai';
        $simulation->submitted_at = now();
        if ($simulation->durasi_detik <= 0 && $simulation->started_at) {
            $simulation->durasi_detik = max(1, (int) $simulation->started_at->diffInSeconds(now()));
        }
        $simulation->save();

        return $simulation;
    }

    /**
     * Catat pengerjaan simulasi mandiri Pusmendik ke tabel riwayat agar
     * tetap terlapor ke Orang Tua dan masuk ke rekap kognitif siswa.
     *
     * @param  array<int, array<string, mixed>>  $detail
     */
    public function recordStandaloneSubmission(
        Siswa $siswa,
        string $paketKode,
        string $paketJudul,
        array $detail,
        float $skor,
        string $level,
        int $durasiDetik
    ): AdaptiveSimulation {
        $indikatorUtama = match ($paketKode) {
            'literasi' => 'Literasi Membaca',
            'numerasi' => 'Numerasi Kuantitatif',
            'sains' => 'Penalaran Sains',
            default => 'Logika & Penalaran Kritis',
        };

        $jejak = [];
        $benarCount = 0;
        foreach ($detail as $idx => $item) {
            $isCorrect = ! empty($item['is_correct']);
            if ($isCorrect) {
                $benarCount++;
            }
            $soal = $item['soal'] ?? [];
            $jejak[] = [
                'step' => $idx + 1,
                'question_id' => (string) ($soal['id'] ?? ($idx + 1)),
                'tingkat' => 'sedang',
                'indikator' => $indikatorUtama,
                'sub_indikator' => $paketJudul,
                'prompt' => (string) ($soal['prompt'] ?? ''),
                'jawaban' => (string) ($item['user_answer'] ?? '-'),
                'kunci' => (string) ($soal['kunci'] ?? ''),
                'benar' => $isCorrect,
                'bobot' => 1.0,
                'durasi_detik' => max(1, intdiv(max(1, $durasiDetik), max(1, count($detail)))),
                'next_tingkat' => 'sedang',
                'pembahasan' => (string) ($soal['pembahasan'] ?? ''),
            ];
        }

        return AdaptiveSimulation::create([
            'tenant_id' => $siswa->tenant_id,
            'siswa_id' => $siswa->id,
            'paket_kode' => $paketKode,
            'paket_judul' => $paketJudul,
            'mode_simulasi' => 'mandiri',
            'tingkat_awal' => 'sedang',
            'tingkat_akhir' => $skor >= 85 ? 'sulit' : ($skor >= 55 ? 'sedang' : 'mudah'),
            'target_soal' => count($detail),
            'total_soal' => count($detail),
            'benar' => $benarCount,
            'skor_akhir' => $skor,
            'level_capaian' => $level,
            'durasi_detik' => max(1, $durasiDetik),
            'jejak_adaptif' => $jejak,
            'skor_indikator' => [$indikatorUtama => $skor],
            'status' => 'selesai',
            'started_at' => now()->subSeconds(max(1, $durasiDetik)),
            'submitted_at' => now(),
        ]);
    }

    public function levelCapaian(float $skor): string
    {
        if ($skor >= 85) {
            return 'Mahir';
        }
        if ($skor >= 70) {
            return 'Cakap';
        }
        if ($skor >= 50) {
            return 'Dasar';
        }

        return 'Perlu Intervensi Khusus';
    }
}
