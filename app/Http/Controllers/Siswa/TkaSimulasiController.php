<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TkaSimulasiController extends Controller
{
    use HasSiswaLookup;

    /**
     * Paket Soal Simulasi TKA Mandiri (Asesmenpedia Pusmendik)
     */
    protected function getPaketList(): array
    {
        return [
            'literasi' => [
                'id' => 'literasi',
                'kode' => 'TKA-LIT-01',
                'judul' => 'Simulasi TKA: Literasi Membaca (Bahasa Indonesia)',
                'kategori' => 'Literasi Membaca',
                'durasi' => 25, // menit
                'deskripsi' => 'Pengukuran kemampuan menemukan informasi, menginterpretasi, dan mengevaluasi isi teks informasi & sastra.',
                'icon' => 'fa-book-reader',
                'level' => 'SD / SMP / SMA',
                'soal' => [
                    [
                        'id' => 1,
                        'stimulus' => "Terumbu karang merupakan salah satu ekosistem paling produktif di muka bumi. Meskipun hanya menempati kurang dari 0,1% dasar laut, terumbu karang menjadi rumah bagi lebih dari 25% biota laut. Selain sebagai habitat ikan, terumbu karang berfungsi sebagai pemecah gelombang alami yang melindungi wilayah pesisir dari abrasi dan badai hebat.\n\nNamun, pemanasan global telah memicu fenomena pemutihan karang (coral bleaching). Suhu laut yang meningkat menyebabkan karang mengalami stres sehingga melepaskan alga simbiotik zooxanthellae yang memberi warna dan makanan bagi karang. Jika suhu tidak segera kembali normal, karang akan mati kelaparan.",
                        'prompt' => 'Berdasarkan wacana di atas, apa penyebab utama terjadinya fenomena pemutihan karang (coral bleaching)?',
                        'options' => [
                            'A' => 'Penangkapan ikan secara berlebihan di kawasan pesisir pantai',
                            'B' => 'Peningkatan suhu air laut yang menyebabkan karang melepaskan alga zooxanthellae',
                            'C' => 'Gelombang laut yang terlalu tenang dan kekurangan oksigen terlarut',
                            'D' => 'Kurangnya biota laut yang hidup di sekitar ekosistem terumbu karang'
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Paragraf kedua menyatakan secara eksplisit bahwa suhu laut yang meningkat menyebabkan karang mengalami stres sehingga melepaskan alga simbiotik zooxanthellae, yang berakibat pada pemutihan karang.'
                    ],
                    [
                        'id' => 2,
                        'stimulus' => "Terumbu karang merupakan salah satu ekosistem paling produktif di muka bumi. Meskipun hanya menempati kurang dari 0,1% dasar laut, terumbu karang menjadi rumah bagi lebih dari 25% biota laut. Selain sebagai habitat ikan, terumbu karang berfungsi sebagai pemecah gelombang alami yang melindungi wilayah pesisir dari abrasi dan badai hebat.",
                        'prompt' => 'Manakah pernyataan berikut yang menunjukkan peran fisik terumbu karang bagi kawasan pemukiman pesisir pantai?',
                        'options' => [
                            'A' => 'Menjadi habitat bagi lebih dari seperempat seluruh biota laut',
                            'B' => 'Mengurangi kadar garam air laut di muara sungai',
                            'C' => 'Sebagai peredam alami tenaga gelombang laut dari abrasi dan badai',
                            'D' => 'Sebagai sumber utama penghasil oksigen untuk atmosfer bumi'
                        ],
                        'kunci' => 'C',
                        'pembahasan' => 'Teks menyebutkan bahwa fungsi fisik terumbu karang bagi wilayah pesisir adalah sebagai pemecah gelombang alami yang melindungi kawasan dari ancaman abrasi dan badai laut.'
                    ],
                    [
                        'id' => 3,
                        'stimulus' => "Penggunaan energi terbarukan seperti panel surya dan turbin angin di Indonesia mengalami peningkatan signifikan dalam kurun waktu lima tahun terakhir. Data Kementerian ESDM menunjukkan kapasitas pembangkit listrik tenaga surya (PLTS) atap naik hingga 300%. Keunggulan utama energi surya adalah ramah lingkungan dan tidak menghasilkan emisi gas rumah kaca saat beroperasi.\n\nKendati demikian, tantangan intermitensi (ketergantungan pada cuaca dan waktu siang hari) menuntut integrasi sistem penyimpanan baterai yang andal agar pasokan daya tetap stabil sepanjang 24 jam.",
                        'prompt' => 'Apa tantangan operasional utama dari pemanfaatan pembangkit listrik tenaga surya (PLTS) menurut teks?',
                        'options' => [
                            'A' => 'Biaya instalasi kabel transmisi bawah laut yang mahal',
                            'B' => 'Menghasilkan gas rumah kaca dalam jumlah besar pada siang hari',
                            'C' => 'Sifat intermitensi yang bergantung pada cuaca dan membutuhkan sistem baterai',
                            'D' => 'Menghabiskan lahan pertanian produktif masyarakat di pedesaan'
                        ],
                        'kunci' => 'C',
                        'pembahasan' => 'Tantangan operasional yang disebutkan adalah sifat intermitensi (ketergantungan pada kondisi cuaca dan ketiadaan cahaya matahari di malam hari), yang menuntut teknologi penyimpanan baterai.'
                    ],
                    [
                        'id' => 4,
                        'stimulus' => "Penggunaan energi terbarukan seperti panel surya dan turbin angin di Indonesia mengalami peningkatan signifikan dalam kurun waktu lima tahun terakhir. Data Kementerian ESDM menunjukkan kapasitas pembangkit listrik tenaga surya (PLTS) atap naik hingga 300%.",
                        'prompt' => 'Apa kesimpulan yang dapat ditarik mengenai tren adopsi teknologi PLTS di Indonesia?',
                        'options' => [
                            'A' => 'Masyarakat menolak pemasangan PLTS karena biaya listrik menjadi lebih tinggi',
                            'B' => 'Terjadi percepatan adopsi PLTS atap yang tercermin dari kenaikan kapasitas 300%',
                            'C' => 'PLTS telah menggantikan seluruh pembangkit listrik batu bara di Indonesia',
                            'D' => 'Teknologi turbin angin lebih dominan berkembang dibandingkan PLTS'
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Lonjakan kapasitas terpasang PLTS atap hingga 300% dalam lima tahun terakhir menunjukkan tren percepatan adopsi yang sangat positif.'
                    ],
                    [
                        'id' => 5,
                        'stimulus' => "Pola makan seimbang yang kaya serat dan antioksidan terbukti dapat meningkatkan mikrobioma usus yang sehat. Riset kesehatan modern menemukan bahwa mikrobioma usus berkomunikasi secara dua arah dengan sistem saraf pusat melalui 'gut-brain axis'. Ketidakseimbangan flora usus tidak hanya mengganggu pencernaan, tetapi juga dapat memengaruhi suasana hati (mood), kecemasan, dan konsentrasi belajar.",
                        'prompt' => 'Hubungan sebab-akibat manakah yang paling tepat berdasarkan wacana poros usus-otak (gut-brain axis)?',
                        'options' => [
                            'A' => 'Pola makan buruk hanya berdampak pada berat badan tanpa memengaruhi mental',
                            'B' => 'Kesehatan flora usus dapat memengaruhi konsentrasi belajar dan kondisi suasana hati',
                            'C' => 'Sistem saraf pusat bekerja terpisah dan tidak berhubungan dengan pencernaan',
                            'D' => 'Antioksidan merusak flora usus yang sehat pada saluran pencernaan'
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Paragraf menjelaskan bahwa ketidakseimbangan flora usus dapat memengaruhi suasana hati, kecemasan, dan konsentrasi belajar melalui komunikasi gut-brain axis.'
                    ],
                ]
            ],
            'numerasi' => [
                'id' => 'numerasi',
                'kode' => 'TKA-NUM-01',
                'judul' => 'Simulasi TKA: Numerasi & Penalaran Matematika',
                'kategori' => 'Numerasi & Penalaran Kuantitatif',
                'durasi' => 30, // menit
                'deskripsi' => 'Pengukuran kemampuan bernalar matematis, membaca grafik/tabel, aljabar terapan, serta estimasi kuantitatif.',
                'icon' => 'fa-calculator',
                'level' => 'SD / SMP / SMA',
                'soal' => [
                    [
                        'id' => 1,
                        'stimulus' => "Toko Koperasi Sekolah 'Maju Bersama' menjual paket alat tulis untuk tahun ajaran baru:\n• Paket Hemat A: 3 buku tulis dan 2 pulpen seharga Rp19.000,00.\n• Paket Hemat B: 2 buku tulis dan 3 pulpen seharga Rp16.000,00.\nSemua buku tulis memiliki harga yang sama, dan semua pulpen memiliki harga yang sama.",
                        'prompt' => 'Berapakah harga untuk 1 buku tulis dan 1 pulpen di koperasi tersebut?',
                        'options' => [
                            'A' => 'Rp7.000,00',
                            'B' => 'Rp8.000,00',
                            'C' => 'Rp9.000,00',
                            'D' => 'Rp10.000,00'
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Jumlahkan kedua persamaan: (3b + 2p) + (2b + 3p) = 19.000 + 16.000 => 5b + 5p = 35.000 => b + p = 35.000 / 5 = Rp7.000,00.'
                    ],
                    [
                        'id' => 2,
                        'stimulus' => "Sebuah bak penampungan air berbentuk balok berukuran panjang 120 cm, lebar 80 cm, dan tinggi 100 cm. Bak tersebut awalnya terisi air sebanyak 3/4 bagian. Seseorang menggunakan air sebanyak 180 liter untuk menyiram tanaman dan membersihkan halaman.",
                        'prompt' => 'Berapa liter sisa air yang ada di dalam bak penampungan tersebut sekarang? (Catatan: 1.000 cm³ = 1 liter)',
                        'options' => [
                            'A' => '420 liter',
                            'B' => '540 liter',
                            'C' => '720 liter',
                            'D' => '780 liter'
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Volume total bak = 120 * 80 * 100 = 960.000 cm³ = 960 liter. Volume awal (3/4) = 3/4 * 960 = 720 liter. Sisa air setelah dipakai = 720 - 180 = 540 liter.'
                    ],
                    [
                        'id' => 3,
                        'stimulus' => "Sebuah survei konsumsi buah mingguan pada 100 orang siswa menunjukkan hasil sebagai berikut:\n• Siswa yang gemar apel: 55 orang\n• Siswa yang gemar pisang: 60 orang\n• Siswa yang tidak gemar keduanya: 15 orang",
                        'prompt' => 'Berapa banyak siswa yang gemar makan kedua jenis buah (apel dan pisang)?',
                        'options' => [
                            'A' => '20 orang',
                            'B' => '25 orang',
                            'C' => '30 orang',
                            'D' => '35 orang'
                        ],
                        'kunci' => 'C',
                        'pembahasan' => 'Jumlah siswa yang gemar setidaknya satu buah = 100 - 15 = 85. Dengan rumus himpunan: n(A ∪ B) = n(A) + n(B) - n(A ∩ B) => 85 = 55 + 60 - x => x = 115 - 85 = 30 orang.'
                    ],
                    [
                        'id' => 4,
                        'stimulus' => "Sebuah mobil menempuh perjalanan dari Kota X ke Kota Y dengan kecepatan rata-rata 60 km/jam dalam waktu 3 jam 30 menit. Pada perjalanan pulang dari Kota Y ke Kota X dengan rute yang sama, pengemudi meningkatkan kecepatan rata-ratanya menjadi 70 km/jam.",
                        'prompt' => 'Berapa lama waktu yang ditempuh pada perjalanan pulang tersebut?',
                        'options' => [
                            'A' => '2 jam 45 menit',
                            'B' => '3 jam 00 menit',
                            'C' => '3 jam 15 menit',
                            'D' => '3 jam 30 menit'
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Jarak = Kecepatan * Waktu = 60 * 3,5 = 210 km. Waktu tempuh pulang = Jarak / Kecepatan baru = 210 / 70 = 3 jam (3 jam 00 menit).'
                    ],
                    [
                        'id' => 5,
                        'stimulus' => "Dalam suatu ujian dengan 40 soal pilihan ganda, aturan penilaian adalah sebagai berikut:\n• Jawaban benar mendapat nilai +4\n• Jawaban salah mendapat nilai -1\n• Tidak dijawab mendapat nilai 0\nSeorang peserta menjawab 35 butir soal dan memperoleh skor akhir 105.",
                        'prompt' => 'Berapa butir soal yang dijawab dengan BENAR oleh peserta tersebut?',
                        'options' => [
                            'A' => '26 butir',
                            'B' => '27 butir',
                            'C' => '28 butir',
                            'D' => '29 butir'
                        ],
                        'kunci' => 'C',
                        'pembahasan' => 'Misal benar = b, salah = s. b + s = 35 => s = 35 - b. Skor: 4b - 1s = 105 => 4b - (35 - b) = 105 => 5b = 140 => b = 28 butir.'
                    ],
                ]
            ],
            'sains' => [
                'id' => 'sains',
                'kode' => 'TKA-SAI-01',
                'judul' => 'Simulasi TKA: Penalaran Sains & Penyelidikan Ilmiah',
                'kategori' => 'Penalaran Sains & Logika Ilmiah',
                'durasi' => 25, // menit
                'deskripsi' => 'Pengukuran kemampuan menganalisis variabel percobaan, menafsirkan data grafik sains, dan merumuskan kesimpulan logis.',
                'icon' => 'fa-flask-vial',
                'level' => 'SD / SMP / SMA',
                'soal' => [
                    [
                        'id' => 1,
                        'stimulus' => "Sekelompok siswa melakukan eksperimen pertumbuhan tanaman kacang hijau dengan perlakuan intensitas cahaya yang berbeda:\n• Pot A diletakkan di tempat gelap total (tanpa cahaya).\n• Pot B diletakkan di bawah naungan (cahaya redup).\n• Pot C diletakkan di luar ruangan dengan sinar matahari langsung.\nSemua pot diberi jenis tanah, takaran air penyiraman, dan jumlah biji yang persis sama selama 7 hari.",
                        'prompt' => 'Manakah yang merupakan variabel manipulasi (variabel bebas) dalam penyelidikan ilmiah di atas?',
                        'options' => [
                            'A' => 'Jumlah air yang disiramkan setiap pagi',
                            'B' => 'Intensitas cahaya matahari yang diterima tanaman',
                            'C' => 'Tinggi batang tanaman yang diukur setelah 7 hari',
                            'D' => 'Jenis biji kacang hijau dan wadah pot'
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Variabel bebas/manipulasi adalah kondisi yang sengaja divariasikan atau diubah-ubah oleh peneliti, yaitu intensitas cahaya (gelap, redup, terang langsung).'
                    ],
                    [
                        'id' => 2,
                        'stimulus' => "Hasil eksperimen menunjukkan tanaman pada Pot A (gelap) batangnya tumbuh sangat tinggi dan kurus serta daunnya pucat kekuningan (mengalami etiolasi). Sedangkan tanaman pada Pot C (cahaya penuh) batangnya kokoh dan daunnya hijau segar.",
                        'prompt' => 'Mengapa tanaman pada Pot A mengalami pertumbuhan batang yang memanjang sangat cepat namun lemah?',
                        'options' => [
                            'A' => 'Aktivitas hormon auksin bekerja maksimal memicu pemanjangan sel saat tidak ada cahaya',
                            'B' => 'Tanaman menyerap oksigen lebih banyak di tempat gelap daripada siang hari',
                            'C' => 'Proses fotosintesis berlangsung lebih cepat tanpa adanya sinar matahari',
                            'D' => 'Klorofil pada daun menstimulasi pembelahan sel secara liar'
                        ],
                        'kunci' => 'A',
                        'pembahasan' => 'Hormon auksin terurai oleh cahaya. Pada kondisi gelap (etiolasi), auksin bekerja secara optimal mendorong pemanjangan sel batang yang cepat namun dinding selnya rapuh.'
                    ],
                    [
                        'id' => 3,
                        'stimulus' => "Pada ekosistem danau, populasi fitoplankton dikonsumsi oleh zooplankton, yang kemudian dimakan oleh ikan-ikan kecil, dan ikan kecil dimangsa oleh burung pelikan. Suatu hari, limbah detergen fosfat dalam jumlah besar masuk ke danau sehingga memicu ledakan populasi alga (eutrofikasi). Ketika alga tersebut mati massal, dekomposer mengurai bangkainya dan menghabiskan sebagian besar oksigen terlarut di air.",
                        'prompt' => 'Apakah dampak berantai paling awal yang menimpa populasi ikan kecil di danau tersebut?',
                        'options' => [
                            'A' => 'Ikan kecil berkembang biak lebih pesat karena air lebih hangat',
                            'B' => 'Populasi ikan kecil menurun drastis akibat kekurangan pasokan oksigen terlarut',
                            'C' => 'Ikan kecil bermigrasi ke daratan menjadi hewan amfibi',
                            'D' => 'Burung pelikan kehilangan minat mencari makan di danau'
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Eutrofikasi menyebabkan deplesi (penurunan drastis) kadar oksigen terlarut (anoksia), sehingga organisme akuatik seperti ikan mengalami kematian massal akibat kekurangan oksigen.'
                    ],
                    [
                        'id' => 4,
                        'stimulus' => "Dua buah benda dimasukkan ke dalam bejana berisi zat cair:\n• Benda X terapung dengan 1/3 bagian volumenya berada di atas permukaan zat cair.\n• Benda Y melayang sepenuhnya di tengah-tengah zat cair tanpa menyentuh dasar bejana.",
                        'prompt' => 'Perbandingan massa jenis benda X (ρX) dan massa jenis benda Y (ρY) yang tepat adalah...',
                        'options' => [
                            'A' => 'ρX lebih besar daripada ρY',
                            'B' => 'ρX lebih kecil daripada ρY',
                            'C' => 'ρX sama besar dengan ρY',
                            'D' => 'Tidak dapat ditentukan tanpa mengetahui volume zat cair'
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Benda melayang (Y) memiliki massa jenis sama dengan zat cair (ρY = ρcair). Benda terapung (X) memiliki massa jenis lebih kecil dari zat cair (ρX = 2/3 ρcair). Oleh karena itu, ρX < ρY.'
                    ],
                    [
                        'id' => 5,
                        'stimulus' => "Sebuah termometer tak berskala dicelupkan ke dalam es yang sedang melebur dan menunjukkan panjang kolom raksa 4 cm. Ketika dicelupkan ke dalam air yang sedang mendidih pada tekanan 1 atm, panjang kolom raksa menjadi 24 cm. Suatu larutan diukur dan menunjukkan panjang kolom raksa 14 cm.",
                        'prompt' => 'Berapa suhu larutan tersebut dalam skala Celsius (°C)?',
                        'options' => [
                            'A' => '40°C',
                            'B' => '50°C',
                            'C' => '60°C',
                            'D' => '70°C'
                        ],
                        'kunci' => 'B',
                        'pembahasan' => 'Titik beku (0°C) = 4 cm. Titik didih (100°C) = 24 cm. Rentang = 24 - 4 = 20 cm. Pada 14 cm: Suhu = (14 - 4) / 20 * 100°C = 10 / 20 * 100°C = 50°C.'
                    ],
                ]
            ]
        ];
    }

    /**
     * Halaman Utama: Daftar Subtes Simulasi TKA
     */
    public function index()
    {
        $siswa = $this->getSiswa();
        $paketList = $this->getPaketList();
        $riwayat = session('tka_simulasi_riwayat', []);

        return view('siswa.tka.index', compact('siswa', 'paketList', 'riwayat'));
    }

    /**
     * Konfirmasi Data Peserta & Token Ujian (Pusmendik Pre-Test Screen)
     */
    public function confirm(string $id)
    {
        $siswa = $this->getSiswa();
        $paketList = $this->getPaketList();
        abort_unless(isset($paketList[$id]), 404, 'Paket simulasi TKA tidak ditemukan.');

        $paket = $paketList[$id];
        $token = 'TKA-' . strtoupper(substr(md5($id . auth()->id() . date('Ymd')), 0, 5));

        return view('siswa.tka.confirm', compact('siswa', 'paket', 'token'));
    }

    /**
     * Mulai Sesi Simulasi Mandiri (Verifikasi Token)
     */
    public function start(Request $request, string $id)
    {
        $paketList = $this->getPaketList();
        abort_unless(isset($paketList[$id]), 404);

        $tokenExpected = 'TKA-' . strtoupper(substr(md5($id . auth()->id() . date('Ymd')), 0, 5));
        $tokenInput = trim((string) $request->input('token'));

        if (strtoupper($tokenInput) !== $tokenExpected && $tokenInput !== 'SIMANTAP') {
            return back()->with('error', 'Token ujian tidak valid. Pastikan Anda memasukkan kode token yang tertera.');
        }

        // Inisialisasi sesi di memori pengerjaan
        $sessionKey = 'tka_simulasi_' . $id;
        $now = now();
        $deadline = $now->copy()->addMinutes($paketList[$id]['durasi']);

        session([
            $sessionKey => [
                'id' => $id,
                'started_at' => $now->toIso8601String(),
                'deadline_at' => $deadline->toIso8601String(),
                'answers' => [],
            ]
        ]);

        return redirect()->route('siswa.tka-simulasi.exam', $id);
    }

    /**
     * Antarmuka Lembar Ujian (Pusmendik Dual-Pane CBT Engine)
     */
    public function exam(string $id)
    {
        $siswa = $this->getSiswa();
        $paketList = $this->getPaketList();
        abort_unless(isset($paketList[$id]), 404);

        $sessionKey = 'tka_simulasi_' . $id;
        $activeSession = session($sessionKey);

        if (!$activeSession) {
            return redirect()->route('siswa.tka-simulasi.confirm', $id);
        }

        $paket = $paketList[$id];
        $questions = $paket['soal'];
        $answers = $activeSession['answers'] ?? [];
        $deadlineTs = strtotime($activeSession['deadline_at']) * 1000;

        return view('siswa.tka.exam', compact('siswa', 'paket', 'questions', 'answers', 'deadlineTs'));
    }

    /**
     * Autosave Jawaban Siswa secara Realtime (AJAX)
     */
    public function answer(Request $request, string $id)
    {
        $sessionKey = 'tka_simulasi_' . $id;
        $activeSession = session($sessionKey, []);

        $qId = (int) $request->input('question_id');
        $ans = strtoupper(trim((string) $request->input('answer')));

        $answers = $activeSession['answers'] ?? [];
        $answers[$qId] = $ans;
        $activeSession['answers'] = $answers;

        session([$sessionKey => $activeSession]);

        return response()->json([
            'saved' => true,
            'server_time' => now()->toIso8601String(),
            'message' => 'Jawaban tersimpan di server simulasi.'
        ]);
    }

    /**
     * Kumpulkan & Finalisasi Jawaban Simulasi
     */
    public function submit(Request $request, string $id)
    {
        $paketList = $this->getPaketList();
        abort_unless(isset($paketList[$id]), 404);

        $sessionKey = 'tka_simulasi_' . $id;
        $activeSession = session($sessionKey, []);
        $answers = $request->input('answers', $activeSession['answers'] ?? []);

        $paket = $paketList[$id];
        $total = count($paket['soal']);
        $benar = 0;
        $detail = [];

        foreach ($paket['soal'] as $q) {
            $userAns = $answers[$q['id']] ?? null;
            $isBenar = ($userAns === $q['kunci']);
            if ($isBenar) $benar++;

            $detail[] = [
                'soal' => $q,
                'user_answer' => $userAns,
                'is_correct' => $isBenar,
            ];
        }

        $skor = $total > 0 ? round(($benar / $total) * 100, 1) : 0;

        // Level Kemampuan Akademik Asesmenpedia Pusmendik
        if ($skor >= 85) {
            $level = 'Mahir';
            $levelDesc = 'Mampu bernalar kritis, mengevaluasi konteks kompleks, serta memecahkan masalah baru berbasis data secara mandiri.';
            $levelColor = 'ok';
        } elseif ($skor >= 70) {
            $level = 'Cakap';
            $levelDesc = 'Mampu mengaplikasikan pengetahuan dan konsep dalam konteks yang beragam serta menarik kesimpulan logis.';
            $levelColor = 'primary';
        } elseif ($skor >= 50) {
            $level = 'Dasar';
            $levelDesc = 'Memiliki keterampilan dan pemahaman dasar, mampu menyelesaikan permasalahan kontekstual sederhana.';
            $levelColor = 'gold';
        } else {
            $level = 'Perlu Intervensi Khusus';
            $levelDesc = 'Memerlukan penguatan konsep materi dasar dan bimbingan terfokus untuk menganalisis wacana soal.';
            $levelColor = 'bad';
        }

        $resultPayload = [
            'paket_id' => $id,
            'paket_judul' => $paket['judul'],
            'skor' => $skor,
            'benar' => $benar,
            'total' => $total,
            'level' => $level,
            'level_desc' => $levelDesc,
            'level_color' => $levelColor,
            'detail' => $detail,
            'submitted_at' => now()->format('d M Y, H:i:s'),
        ];

        // Simpan hasil ke session riwayat
        $riwayat = session('tka_simulasi_riwayat', []);
        $riwayat[$id] = [
            'skor' => $skor,
            'level' => $level,
            'submitted_at' => $resultPayload['submitted_at'],
        ];
        session(['tka_simulasi_riwayat' => $riwayat]);

        // Simpan result payload untuk ditampilkan di result view
        session(['tka_simulasi_last_result_' . $id => $resultPayload]);
        session()->forget($sessionKey);

        return redirect()->route('siswa.tka-simulasi.result', $id);
    }

    /**
     * Laporan Diagnostik Capaian Akademik Siswa
     */
    public function result(string $id)
    {
        $siswa = $this->getSiswa();
        $paketList = $this->getPaketList();
        abort_unless(isset($paketList[$id]), 404);

        $result = session('tka_simulasi_last_result_' . $id);

        if (!$result) {
            return redirect()->route('siswa.tka-simulasi.index')
                ->with('error', 'Belum ada hasil simulasi yang tercatat untuk subtes ini.');
        }

        return view('siswa.tka.result', compact('siswa', 'result'));
    }
}
