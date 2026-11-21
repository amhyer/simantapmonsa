<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\AlokasiWaktu;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\KktpInterval;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\ProtaItem;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\Rombel;
use App\Models\User;
use App\Services\PembelajaranMendalam\GeminiPembelajaranService;
use App\Services\PembelajaranMendalam\GeneratorRencanaService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class GeneratorBatchController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly GeminiPembelajaranService $ai,
        private readonly GeneratorRencanaService $planner,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());

        return view('guru.pm.generate.index', $this->indexViewData($guru, $tenantId, $tahunAjaran));
    }

    /**
     * Pratinjau jadwal rencana pertemuan (tanpa menulis ke database) sebelum generate batch.
     */
    public function pratinjau(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'kelas' => ['nullable', 'string', 'max:40'],
            'semester' => ['required', 'in:1,2'],
            'mapel' => ['required', 'array', 'min:1'],
            'mapel.*' => ['required', 'string', 'max:120'],
            'dari_tanggal' => ['required', 'date'],
            'sampai_tanggal' => ['required', 'date', 'after_or_equal:dari_tanggal'],
            'mode' => ['required', 'in:cepat,ai'],
            'buat_modul' => ['nullable', 'boolean'],
            'buat_jurnal' => ['nullable', 'boolean'],
            'sertakan_lkpd' => ['nullable', 'boolean'],
        ]);

        $rencana = $this->planner->susunRencana(
            $tenantId,
            $guru->id,
            $data['tahun_ajaran'],
            (int) $data['tingkat'],
            (int) $data['semester'],
            $data['kelas'] ?? null,
            $data['mapel'],
            $data['dari_tanggal'],
            $data['sampai_tanggal'],
            $data['mode']
        );

        return view('guru.pm.generate.index', array_merge(
            $this->indexViewData($guru, $tenantId, $data['tahun_ajaran']),
            [
                'rencanaPreview' => $rencana,
                'inputPreview' => $data,
            ]
        ));
    }

    /**
     * Buat Modul Ajar (RPM) & Jurnal Mengajar Sekaligus berdasarkan Jadwal Pelajaran, Hari Libur & Prosem/Prota.
     */
    public function generateBatch(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'kelas' => ['nullable', 'string', 'max:40'],
            'semester' => ['required', 'in:1,2'],
            'mapel' => ['required', 'array', 'min:1'],
            'mapel.*' => ['required', 'string', 'max:120'],
            'dari_tanggal' => ['required', 'date'],
            'sampai_tanggal' => ['required', 'date', 'after_or_equal:dari_tanggal'],
            'mode' => ['required', 'in:cepat,ai'],
            'buat_modul' => ['nullable', 'boolean'],
            'buat_jurnal' => ['nullable', 'boolean'],
            'sertakan_lkpd' => ['nullable', 'boolean'],
        ]);

        $buatModul = (bool) ($data['buat_modul'] ?? false);
        $buatJurnal = (bool) ($data['buat_jurnal'] ?? false);
        if (! $buatModul && ! $buatJurnal) {
            return back()->with('error', 'Pilih minimal satu keluaran: Buat Modul Ajar atau Buat Jurnal Mengajar.');
        }

        $tingkat = (int) $data['tingkat'];
        $semester = (int) $data['semester'];
        $fase = TujuanPembelajaranPm::faseUntukKelas($tingkat);
        $kelasLabel = trim((string) ($data['kelas'] ?? '')) ?: (string) $tingkat;

        $rencana = $this->planner->susunRencana(
            $tenantId,
            $guru->id,
            $data['tahun_ajaran'],
            $tingkat,
            $semester,
            $data['kelas'] ?? null,
            $data['mapel'],
            $data['dari_tanggal'],
            $data['sampai_tanggal'],
            $data['mode']
        );

        if (empty($rencana['items'])) {
            return back()->with('error', 'Tidak ada hari efektif (sesuai jadwal / non-libur) pada rentang tanggal yang dipilih.');
        }

        $cpCache = [];
        $totalModul = 0;
        $totalJurnal = 0;

        foreach ($rencana['items'] as $item) {
            $namaMapel = $item['mapel'];
            if (! array_key_exists($namaMapel, $cpCache)) {
                $cpCache[$namaMapel] = CapaianPembelajaran::query()
                    ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->where('nama_mapel', $namaMapel)
                    ->where('fase', $fase)
                    ->pluck('deskripsi')
                    ->implode(' ');
            }

            $perangkatId = null;

            if ($buatModul) {
                $konten = $this->bangunKontenRpm(
                    $data['mode'],
                    $namaMapel,
                    $tingkat,
                    $item['materi'],
                    $item['tp_teks'] ?: $cpCache[$namaMapel],
                    (bool) ($data['sertakan_lkpd'] ?? false),
                    $tenantId,
                    $guru->id
                );

                $perangkat = PerangkatPembelajaran::create([
                    'tenant_id' => $tenantId,
                    'guru_id' => $guru->id,
                    'jenis' => PerangkatPembelajaran::JENIS_MODUL_AJAR,
                    'judul' => "RPM {$namaMapel} — {$item['materi']}",
                    'mata_pelajaran' => $namaMapel,
                    'fase' => $fase,
                    'kelas' => $kelasLabel,
                    'tahun_ajaran' => $data['tahun_ajaran'],
                    'semester' => $semester === 1 ? 'ganjil' : 'genap',
                    'alokasi_waktu' => "{$item['jp']} JP",
                    'ringkasan' => "Tanggal: {$item['tanggal_id']} ({$item['hari']}) · Mode: ".strtoupper($data['mode']),
                    'konten' => $konten,
                    'status' => 'final',
                ]);
                $perangkatId = $perangkat->id;
                $totalModul++;
            }

            if ($buatJurnal) {
                JurnalMengajar::create([
                    'tenant_id' => $tenantId,
                    'guru_id' => $guru->id,
                    'perangkat_id' => $perangkatId,
                    'tp_id' => $item['tp_id'],
                    'tanggal' => $item['tanggal'],
                    'jam_ke' => $item['jam_ke'],
                    'kelas' => $kelasLabel,
                    'mata_pelajaran' => $namaMapel,
                    'pertemuan_ke' => $item['pertemuan_ke'],
                    'topik' => $item['materi'],
                    'praktik_pedagogis' => 'Pembelajaran Berdiferensiasi',
                    'prinsip_pm' => ['Berkesadaran (Mindful)', 'Bermakna (Meaningful)', 'Menggembirakan (Joyful)'],
                    'pengalaman_belajar' => ['Memahami', 'Mengaplikasi', 'Merefleksi'],
                    'kegiatan' => "Pelaksanaan pembelajaran mendalam topik {$item['materi']} sesuai RPM.",
                    'asesmen' => 'Observasi aktivitas murid & asesmen formatif singkat.',
                    'catatan' => $item['sumber_jadwal'] === 'jadwal_pelajaran'
                        ? 'Dibuat otomatis sesuai Jadwal Pelajaran.'
                        : 'Dibuat otomatis melalui Generator Batch Pembelajaran Mendalam.',
                    'tindak_lanjut' => 'Penguatan konsep dan refleksi pada pertemuan berikutnya.',
                ]);
                $totalJurnal++;
            }
        }

        return redirect()
            ->route('guru.pm.generate.index')
            ->with('success', "Berhasil membuat {$totalModul} Modul Ajar (RPM) dan {$totalJurnal} Jurnal Mengajar sekaligus!");
    }

    /**
     * Cetak 1 Bundel Administrasi Guru 1 Semester (Cover + Alokasi + CP + TP/ATP + KKTP + Prota + Prosem + RPM/Jurnal + TTD & Stempel).
     */
    public function cetakBundelSemesterPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $tingkat = max(1, min(6, (int) $request->query('tingkat', 1)));
        $semester = in_array((int) $request->query('semester', 1), [1, 2], true) ? (int) $request->query('semester', 1) : 1;
        $mapel = trim((string) $request->query('mapel', ''));
        $fase = TujuanPembelajaranPm::faseUntukKelas($tingkat);

        $alokasi = AlokasiWaktu::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->orderBy('nama_mapel')->get();

        $cpList = CapaianPembelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('fase', $fase)
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->orderBy('nama_mapel')->orderBy('urutan')->get();

        $tpList = TujuanPembelajaranPm::query()
            ->with('kktpItem')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('fase', $fase)
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->orderBy('nama_mapel')->orderBy('urutan')->get();

        $kktpInterval = KktpInterval::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tingkat', $tingkat)
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->first() ?? new KktpInterval(['nilai_kktp' => 75, 'batas_bawah' => 60, 'batas_atas' => 88]);

        $protaList = ProtaItem::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->orderBy('nama_mapel')->orderBy('semester')->orderBy('urutan')->get();

        $prosemList = ProsemItem::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('tingkat', $tingkat)
            ->where('semester', $semester)
            ->when($mapel !== '', fn ($q) => $q->where('nama_mapel', $mapel))
            ->orderBy('nama_mapel')->orderBy('bulan')->orderBy('minggu_ke')->get();

        $rpmList = PerangkatPembelajaran::query()
            ->where('guru_id', $guru->id)
            ->where('jenis', PerangkatPembelajaran::JENIS_MODUL_AJAR)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($mapel !== '', fn ($q) => $q->where('mata_pelajaran', $mapel))
            ->orderByDesc('updated_at')->limit(40)->get();

        $jurnalList = JurnalMengajar::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($mapel !== '', fn ($q) => $q->where('mata_pelajaran', $mapel))
            ->orderByDesc('tanggal')->limit(40)->get();

        $pdf = Pdf::loadView('guru.pm.pdf.bundel-semester', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'guru' => $guru,
            'tahunAjaran' => $tahunAjaran,
            'tingkat' => $tingkat,
            'fase' => $fase,
            'semester' => $semester,
            'mapel' => $mapel ?: 'Semua Mata Pelajaran',
            'alokasi' => $alokasi,
            'cpList' => $cpList,
            'tpList' => $tpList,
            'rentangPredikat' => $kktpInterval->rentangPredikat(),
            'protaList' => $protaList,
            'prosemList' => $prosemList,
            'rpmList' => $rpmList,
            'jurnalList' => $jurnalList,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("bundel-administrasi-kelas-{$tingkat}-sem-{$semester}.pdf");
    }

    /**
     * Buat 1 Modul Ajar / RPM lengkap dengan AI Gemini dan simpan langsung ke Perangkat Ajar.
     */
    public function generateRpmAi(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'kelas' => ['nullable', 'string', 'max:40'],
            'materi' => ['required', 'string', 'max:255'],
            'model_pembelajaran' => ['nullable', 'string', 'max:100'],
            'alokasi_waktu' => ['nullable', 'string', 'max:40'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'sertakan_lkpd' => ['nullable', 'boolean'],
        ]);

        $tingkat = (int) $data['tingkat'];
        $fase = TujuanPembelajaranPm::faseUntukKelas($tingkat);

        try {
            $konten = $this->bangunKontenRpm(
                'ai',
                $data['nama_mapel'],
                $tingkat,
                $data['materi'],
                null,
                (bool) ($data['sertakan_lkpd'] ?? false),
                $tenantId,
                $guru->id,
                $data['model_pembelajaran'] ?? null,
                $data['catatan'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $perangkat = PerangkatPembelajaran::create([
            'tenant_id' => $tenantId,
            'guru_id' => $guru->id,
            'jenis' => PerangkatPembelajaran::JENIS_MODUL_AJAR,
            'judul' => "RPM {$data['nama_mapel']} — {$data['materi']}",
            'mata_pelajaran' => $data['nama_mapel'],
            'fase' => $fase,
            'kelas' => ! empty($data['kelas']) ? $data['kelas'] : (string) $tingkat,
            'tahun_ajaran' => $this->ctx->tahunAjaranAktif(),
            'semester' => 'ganjil',
            'alokasi_waktu' => ! empty($data['alokasi_waktu']) ? $data['alokasi_waktu'] : '2 x 35 menit',
            'ringkasan' => 'Disusun dengan AI Gemini (Pembelajaran Mendalam)',
            'konten' => $konten,
            'status' => 'final',
        ]);

        return redirect()
            ->route('guru.perangkat.show', $perangkat)
            ->with('success', 'Modul Ajar / RPM berhasil disusun dengan AI dan siap dicetak atau diedit.');
    }

    /**
     * Isi 1 Jurnal Mengajar Harian dengan AI Gemini dan simpan langsung ke Jurnal Mengajar.
     */
    public function generateJurnalAi(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'jam_ke' => ['nullable', 'string', 'max:20'],
            'kelas' => ['required', 'string', 'max:40'],
            'nama_mapel' => ['required', 'string', 'max:120'],
            'topik' => ['required', 'string', 'max:255'],
            'kejadian_kelas' => ['nullable', 'string', 'max:600'],
        ]);

        try {
            $aiRes = $this->ai->susunJurnalMengajar(
                $data['nama_mapel'],
                $data['kelas'],
                $data['topik'],
                $data['kejadian_kelas'] ?? null,
                $tenantId,
                $guru->id
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        JurnalMengajar::create([
            'tenant_id' => $tenantId,
            'guru_id' => $guru->id,
            'tanggal' => $data['tanggal'],
            'jam_ke' => ! empty($data['jam_ke']) ? $data['jam_ke'] : '1-2',
            'kelas' => $data['kelas'],
            'mata_pelajaran' => $data['nama_mapel'],
            'topik' => $data['topik'],
            'praktik_pedagogis' => (string) ($aiRes['praktik_pedagogis'] ?? 'Pembelajaran Berdiferensiasi'),
            'prinsip_pm' => is_array($aiRes['prinsip_pm'] ?? null) ? $aiRes['prinsip_pm'] : ['Berkesadaran (Mindful)', 'Bermakna (Meaningful)', 'Menggembirakan (Joyful)'],
            'pengalaman_belajar' => is_array($aiRes['pengalaman_belajar'] ?? null) ? $aiRes['pengalaman_belajar'] : ['Memahami', 'Mengaplikasi', 'Merefleksi'],
            'kegiatan' => (string) ($aiRes['kegiatan'] ?? "Pembelajaran topik {$data['topik']}"),
            'asesmen' => (string) ($aiRes['asesmen'] ?? 'Asesmen formatif'),
            'catatan' => (string) ($aiRes['catatan'] ?? ''),
            'tindak_lanjut' => (string) ($aiRes['tindak_lanjut'] ?? ''),
        ]);

        return redirect()
            ->route('guru.perangkat.jurnal.index')
            ->with('success', 'Jurnal Mengajar Pembelajaran Mendalam berhasil disusun oleh AI.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexViewData(User $guru, ?int $tenantId, string $tahunAjaran): array
    {
        $rombelList = Rombel::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_rombel')
            ->get(['id', 'nama_rombel', 'tingkat']);

        return [
            'tahunAjaran' => $tahunAjaran,
            'mapelList' => $this->ctx->daftarMapel(),
            'rombelList' => $rombelList,
            'jadwalMap' => $this->planner->jadwalGuruPerMapel($tenantId, $guru->id),
            'modelList' => KokurikulerPm::MODEL_PEMBELAJARAN,
            'aiQuota' => $this->ai->quotaStatus($tenantId),
            'defaultDari' => now()->startOfWeek()->toDateString(),
            'defaultSampai' => now()->addWeeks(2)->endOfWeek()->toDateString(),
            'rencanaPreview' => null,
            'inputPreview' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bangunKontenRpm(
        string $mode,
        string $mapel,
        int $tingkat,
        string $materi,
        ?string $tpTeks,
        bool $sertakanLkpd,
        ?int $tenantId,
        int $userId,
        ?string $modelPembelajaran = null,
        ?string $catatan = null
    ): array {
        if ($mode === 'ai') {
            $aiRes = $this->ai->susunModulAjarRpm(
                $mapel,
                $tingkat,
                $materi,
                $tpTeks,
                $modelPembelajaran,
                $sertakanLkpd,
                $catatan,
                $tenantId,
                $userId
            );

            return [
                'tujuan_pembelajaran' => (string) ($aiRes['tujuan_pembelajaran'] ?? ($tpTeks ?: "Murid memahami dan mengaplikasikan materi {$materi}.")),
                'pm' => ['berkesadaran' => true, 'bermakna' => true, 'menggembirakan' => true],
                'dimensi' => is_array($aiRes['dimensi'] ?? null) ? $aiRes['dimensi'] : ['Penalaran Kritis', 'Kolaborasi'],
                'pengalaman' => [
                    'memahami' => (string) data_get($aiRes, 'pengalaman.memahami', ''),
                    'mengaplikasi' => (string) data_get($aiRes, 'pengalaman.mengaplikasi', ''),
                    'merefleksi' => (string) data_get($aiRes, 'pengalaman.merefleksi', ''),
                ],
                'pendahuluan' => (string) ($aiRes['pendahuluan'] ?? ''),
                'kegiatan_inti' => (string) ($aiRes['kegiatan_inti'] ?? ''),
                'penutup' => (string) ($aiRes['penutup'] ?? ''),
                'asesmen' => trim((string) ($aiRes['asesmen'] ?? '').($sertakanLkpd && ! empty($aiRes['rubrik']) ? "\n\nRubrik:\n".$aiRes['rubrik'] : '')),
                'media_sumber' => trim((string) ($aiRes['media_sumber'] ?? '').($sertakanLkpd && ! empty($aiRes['lkpd']) ? "\n\nLKPD:\n".$aiRes['lkpd'] : '')),
                'refleksi_guru' => (string) ($aiRes['refleksi_guru'] ?? ''),
            ];
        }

        return [
            'tujuan_pembelajaran' => $tpTeks ?: "Murid mampu memahami, mengaplikasikan, dan merefleksikan konsep {$materi} secara kontekstual.",
            'pm' => ['berkesadaran' => true, 'bermakna' => true, 'menggembirakan' => true],
            'dimensi' => ['Penalaran Kritis', 'Kolaborasi', 'Kemandirian'],
            'pengalaman' => [
                'memahami' => "Murid menggali konsep inti {$materi} melalui pengamatan contoh nyata dan diskusi terbimbing.",
                'mengaplikasi' => "Murid memecahkan masalah kontekstual terkait {$materi} secara berkelompok dan mempresentasikan hasilnya.",
                'merefleksi' => "Murid menuliskan pemahaman baru, kesulitan, dan manfaat mempelajari {$materi} dalam kehidupan sehari-hari.",
            ],
            'pendahuluan' => "1. Salam, doa, cek kehadiran, dan apersepsi kontekstual terkait {$materi}.\n2. Menyampaikan tujuan pembelajaran dan pertanyaan pemantik.",
            'kegiatan_inti' => "1. Eksplorasi konsep {$materi} secara berdiferensiasi (visual, auditori, kinestetik).\n2. Diskusi kelompok dan pengerjaan lembar aktivitas.\n3. Presentasi hasil dan umpan balik konstruktif.",
            'penutup' => "1. Refleksi bersama (hal yang dipahami & yang ingin diperdalam).\n2. Penguatan konsep, asesmen formatif singkat, dan doa penutup.",
            'asesmen' => "Asesmen Awal: Tanya jawab pemantik.\nAsesmen Formatif: Observasi diskusi & LKPD.\nAsesmen Sumatif: Uji pemahaman akhir topik.",
            'media_sumber' => "Buku Paket SIBI {$mapel} Kelas {$tingkat}, papan tulis, dan alat peraga kontekstual.",
            'refleksi_guru' => 'Mencatat ketercapaian TP dan rencana pengayaan/remedial pertemuan berikutnya.',
        ];
    }
}
