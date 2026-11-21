<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Elearning\KelasDigital;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\User;
use App\Services\AktivitasService;
use App\Services\Elearning\ElearningFileService;
use App\Services\Perangkat\PerangkatAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerangkatController extends Controller
{
    public function __construct(
        private readonly PerangkatAccessService $access,
        private readonly ElearningFileService $files,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->access->currentTenantId($guru);
        $jenisFilter = (string) $request->query('jenis', '');
        $cari = trim((string) $request->query('q', ''));

        $query = PerangkatPembelajaran::query()
            ->with(['rombel:id,nama_rombel', 'kelasDigital:id,uuid,nama_kelas'])
            ->where('guru_id', $guru->id)
            ->orderByDesc('updated_at');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($jenisFilter !== '' && array_key_exists($jenisFilter, PerangkatPembelajaran::JENIS_LABELS)) {
            $query->where('jenis', $jenisFilter);
        }

        if ($cari !== '') {
            $query->where(function ($q) use ($cari): void {
                $q->where('judul', 'like', "%{$cari}%")
                    ->orWhere('mata_pelajaran', 'like', "%{$cari}%")
                    ->orWhere('kelas', 'like', "%{$cari}%");
            });
        }

        $perangkatList = $query->paginate(15)->withQueryString();

        // Hitung jumlah per jenis milik guru untuk tab filter cepat
        $countsQuery = PerangkatPembelajaran::query()->where('guru_id', $guru->id);
        if ($tenantId !== null) {
            $countsQuery->where('tenant_id', $tenantId);
        }
        $countsByJenis = $countsQuery
            ->selectRaw('jenis, COUNT(*) as total')
            ->groupBy('jenis')
            ->pluck('total', 'jenis')
            ->all();

        $adalahWaliKelas = $guru->isWaliKelas();
        $perangkatPengajarKelas = $adalahWaliKelas
            ? $this->access->queryUntukWaliKelas($guru)->orderByDesc('updated_at')->limit(30)->get()
            : collect();

        $jumlahJurnal = JurnalMengajar::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        return view('guru.perangkat.index', [
            'perangkatList' => $perangkatList,
            'jenisLabels' => PerangkatPembelajaran::JENIS_LABELS,
            'jenisRingkas' => PerangkatPembelajaran::JENIS_RINGKAS,
            'countsByJenis' => $countsByJenis,
            'jenisFilter' => $jenisFilter,
            'cari' => $cari,
            'adalahWaliKelas' => $adalahWaliKelas,
            'perangkatPengajarKelas' => $perangkatPengajarKelas,
            'jumlahJurnal' => $jumlahJurnal,
        ]);
    }

    public function create(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $jenis = (string) $request->query('jenis', PerangkatPembelajaran::JENIS_CP);
        if (! array_key_exists($jenis, PerangkatPembelajaran::JENIS_LABELS)) {
            $jenis = PerangkatPembelajaran::JENIS_CP;
        }

        return view('guru.perangkat.create', [
            'jenis' => $jenis,
            'jenisLabels' => PerangkatPembelajaran::JENIS_LABELS,
            'templates' => $this->allTemplates(),
            'rombelList' => $this->rombelOptions($guru),
            'kelasDigitalList' => $this->kelasDigitalOptions($guru),
            'semesterAktif' => $this->semesterAktif($guru),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->access->currentTenantId($guru);
        $validated = $this->validatePerangkat($request);

        $rombel = $this->resolveRombel($validated['rombel_id'] ?? null, $tenantId);
        $kelasDigital = $this->resolveKelasDigital($validated['kelas_digital_id'] ?? null, $guru, $tenantId);
        $semester = $this->semesterAktif($guru);

        $lampiran = null;
        if ($request->hasFile('lampiran')) {
            $lampiran = $this->files->store($request->file('lampiran'), 'perangkat', $tenantId, 'lampiran');
        }

        $konten = $this->extractKonten($request, $validated['jenis']);

        $perangkat = PerangkatPembelajaran::create([
            'tenant_id' => $tenantId,
            'guru_id' => $guru->id,
            'rombel_id' => $rombel?->id,
            'semester_id' => $semester?->id,
            'kelas_digital_id' => $kelasDigital?->id,
            'jenis' => $validated['jenis'],
            'judul' => $validated['judul'],
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'fase' => $validated['fase'] ?? null,
            'kelas' => ($validated['kelas'] ?? null) ?: ($rombel?->nama_rombel ?? 'Umum'),
            'tahun_ajaran' => ($validated['tahun_ajaran'] ?? null) ?: ($semester?->tahun_ajaran ?? date('Y').'/'.(date('Y') + 1)),
            'semester' => ($validated['semester'] ?? null) ?: ($semester?->nama_semester ? ucfirst((string) $semester->nama_semester) : 'Ganjil'),
            'alokasi_waktu' => $validated['alokasi_waktu'] ?? null,
            'ringkasan' => $validated['ringkasan'] ?? null,
            'konten' => $konten,
            'lampiran_path' => $lampiran['path'] ?? null,
            'lampiran_nama' => $lampiran['original_name'] ?? null,
            'lampiran_mime' => $lampiran['mime'] ?? null,
            'lampiran_ukuran' => $lampiran['size'] ?? null,
            'status' => $validated['status'] ?? 'final',
            'dibagikan_ke_walas' => $request->boolean('dibagikan_ke_walas', true),
        ]);

        AktivitasService::tambah(
            'Buat Perangkat Ajar: '.$perangkat->jenisRingkas(),
            $perangkat->judul.' ('.$perangkat->mata_pelajaran.' — '.$perangkat->kelas.')',
            'perangkat_pembelajaran',
            $perangkat->id
        );

        return redirect()
            ->route('guru.perangkat.show', $perangkat)
            ->with('success', $perangkat->jenisLabel().' berhasil disimpan.');
    }

    public function show(PerangkatPembelajaran $perangkat): View
    {
        /** @var User $user */
        $user = Auth::user();
        $this->access->authorizeViewPerangkat($user, $perangkat);

        $perangkat->load(['guru:id,nama_lengkap', 'rombel:id,nama_rombel', 'kelasDigital:id,uuid,nama_kelas']);
        $isOwner = (int) $perangkat->guru_id === (int) $user->id;

        return view('guru.perangkat.show', [
            'perangkat' => $perangkat,
            'isOwner' => $isOwner,
            'kop' => $this->access->kopSekolah($perangkat->tenant_id),
        ]);
    }

    public function edit(PerangkatPembelajaran $perangkat): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $this->access->authorizeOwner($guru, $perangkat);

        return view('guru.perangkat.edit', [
            'perangkat' => $perangkat,
            'jenisLabels' => PerangkatPembelajaran::JENIS_LABELS,
            'templates' => $this->allTemplates(),
            'rombelList' => $this->rombelOptions($guru),
            'kelasDigitalList' => $this->kelasDigitalOptions($guru),
        ]);
    }

    public function update(Request $request, PerangkatPembelajaran $perangkat): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $this->access->authorizeOwner($guru, $perangkat);
        $tenantId = $this->access->currentTenantId($guru);
        $validated = $this->validatePerangkat($request);

        $rombel = $this->resolveRombel($validated['rombel_id'] ?? null, $tenantId);
        $kelasDigital = $this->resolveKelasDigital($validated['kelas_digital_id'] ?? null, $guru, $tenantId);

        $updates = [
            'rombel_id' => $rombel?->id,
            'kelas_digital_id' => $kelasDigital?->id,
            'jenis' => $validated['jenis'],
            'judul' => $validated['judul'],
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'fase' => $validated['fase'] ?? null,
            'kelas' => ($validated['kelas'] ?? null) ?: ($rombel?->nama_rombel ?? $perangkat->kelas),
            'tahun_ajaran' => ($validated['tahun_ajaran'] ?? null) ?: $perangkat->tahun_ajaran,
            'semester' => ($validated['semester'] ?? null) ?: $perangkat->semester,
            'alokasi_waktu' => $validated['alokasi_waktu'] ?? null,
            'ringkasan' => $validated['ringkasan'] ?? null,
            'konten' => $this->extractKonten($request, $validated['jenis']),
            'status' => $validated['status'] ?? $perangkat->status,
            'dibagikan_ke_walas' => $request->boolean('dibagikan_ke_walas'),
        ];

        if ($request->hasFile('lampiran')) {
            $oldPath = $perangkat->lampiran_path;
            $lampiran = $this->files->store($request->file('lampiran'), 'perangkat', $tenantId, 'lampiran');
            $updates['lampiran_path'] = $lampiran['path'];
            $updates['lampiran_nama'] = $lampiran['original_name'];
            $updates['lampiran_mime'] = $lampiran['mime'];
            $updates['lampiran_ukuran'] = $lampiran['size'];
            $this->files->deleteQuietly($oldPath);
        }

        $perangkat->update($updates);

        AktivitasService::tambah(
            'Ubah Perangkat Ajar: '.$perangkat->jenisRingkas(),
            $perangkat->judul,
            'perangkat_pembelajaran',
            $perangkat->id
        );

        return redirect()
            ->route('guru.perangkat.show', $perangkat)
            ->with('success', 'Perubahan perangkat ajar berhasil disimpan.');
    }

    public function destroy(PerangkatPembelajaran $perangkat): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $this->access->authorizeOwner($guru, $perangkat);

        $judul = $perangkat->judul;
        $jenis = $perangkat->jenisRingkas();
        $perangkat->delete();

        AktivitasService::tambah(
            'Hapus Perangkat Ajar: '.$jenis,
            $judul,
            'perangkat_pembelajaran',
            $perangkat->id
        );

        return redirect()
            ->route('guru.perangkat.index')
            ->with('success', 'Perangkat ajar berhasil dihapus.');
    }

    public function cetakPdf(PerangkatPembelajaran $perangkat): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $this->access->authorizeViewPerangkat($user, $perangkat);

        $perangkat->load(['guru:id,nama_lengkap', 'rombel:id,nama_rombel']);
        $kop = $this->access->kopSekolah($perangkat->tenant_id);

        $pdf = Pdf::loadView('guru.perangkat.pdf', [
            'perangkat' => $perangkat,
            'kop' => $kop,
            'dicetakPada' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        $slug = Str::slug($perangkat->jenis.'-'.$perangkat->judul) ?: 'perangkat-ajar';

        return $pdf->stream($slug.'.pdf');
    }

    public function downloadLampiran(PerangkatPembelajaran $perangkat): StreamedResponse|BinaryFileResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->access->authorizeViewPerangkat($user, $perangkat);

        return $this->files->download(
            $perangkat->lampiran_path,
            $perangkat->lampiran_nama
        );
    }

    // ─── JURNAL MENGAJAR HARIAN ──────────────────────────────────────────

    public function jurnalIndex(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->access->currentTenantId($guru);
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');
        $kelasFilter = trim((string) $request->query('kelas', ''));

        $query = JurnalMengajar::query()
            ->with(['rombel:id,nama_rombel', 'perangkat:id,uuid,judul,jenis'])
            ->where('guru_id', $guru->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        if ($dari) {
            $query->whereDate('tanggal', '>=', $dari);
        }
        if ($sampai) {
            $query->whereDate('tanggal', '<=', $sampai);
        }
        if ($kelasFilter !== '') {
            $query->where('kelas', 'like', "%{$kelasFilter}%");
        }

        $jurnalList = $query->paginate(20)->withQueryString();

        $perangkatOptions = PerangkatPembelajaran::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'jenis', 'judul', 'kelas', 'mata_pelajaran']);

        $tpOptions = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_mapel')
            ->orderBy('urutan')
            ->limit(150)
            ->get(['id', 'nama_mapel', 'kode_tp', 'deskripsi']);

        return view('guru.perangkat.jurnal', [
            'jurnalList' => $jurnalList,
            'rombelList' => $this->rombelOptions($guru),
            'perangkatOptions' => $perangkatOptions,
            'tpOptions' => $tpOptions,
            'modelList' => KokurikulerPm::MODEL_PEMBELAJARAN,
            'kelasDigitalList' => $this->kelasDigitalOptions($guru),
            'dari' => $dari,
            'sampai' => $sampai,
            'kelasFilter' => $kelasFilter,
        ]);
    }

    public function jurnalStore(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->access->currentTenantId($guru);

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'jam_ke' => ['nullable', 'string', 'max:30'],
            'rombel_id' => ['nullable', 'integer'],
            'kelas' => ['nullable', 'string', 'max:60'],
            'mata_pelajaran' => ['required', 'string', 'max:120'],
            'pertemuan_ke' => ['nullable', 'integer', 'min:1', 'max:200'],
            'perangkat_id' => ['nullable', 'integer'],
            'tp_id' => ['nullable', 'integer'],
            'kelas_digital_id' => ['nullable', 'integer'],
            'topik' => ['required', 'string', 'max:220'],
            'praktik_pedagogis' => ['nullable', 'string', 'max:120'],
            'prinsip_pm' => ['nullable', 'array'],
            'pengalaman_belajar' => ['nullable', 'array'],
            'kegiatan' => ['required', 'string', 'max:4000'],
            'asesmen' => ['nullable', 'string', 'max:2000'],
            'jumlah_hadir' => ['nullable', 'integer', 'min:0', 'max:500'],
            'jumlah_tidak_hadir' => ['nullable', 'integer', 'min:0', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:2000'],
        ]);

        $rombel = $this->resolveRombel($validated['rombel_id'] ?? null, $tenantId);
        $kelasDigital = $this->resolveKelasDigital($validated['kelas_digital_id'] ?? null, $guru, $tenantId);

        $perangkatId = null;
        if (! empty($validated['perangkat_id'])) {
            $perangkat = PerangkatPembelajaran::query()
                ->where('id', $validated['perangkat_id'])
                ->where('guru_id', $guru->id)
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->first();
            $perangkatId = $perangkat?->id;
        }

        $tpId = null;
        if (! empty($validated['tp_id'])) {
            $tp = TujuanPembelajaranPm::query()
                ->where('id', (int) $validated['tp_id'])
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->first();
            $tpId = $tp?->id;
        }

        $jurnal = JurnalMengajar::create([
            'tenant_id' => $tenantId,
            'guru_id' => $guru->id,
            'rombel_id' => $rombel?->id,
            'perangkat_id' => $perangkatId,
            'tp_id' => $tpId,
            'kelas_digital_id' => $kelasDigital?->id,
            'tanggal' => $validated['tanggal'],
            'jam_ke' => $validated['jam_ke'] ?? null,
            'kelas' => ($validated['kelas'] ?? null) ?: ($rombel?->nama_rombel ?? 'Umum'),
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'pertemuan_ke' => $validated['pertemuan_ke'] ?? null,
            'topik' => $validated['topik'],
            'praktik_pedagogis' => ! empty($validated['praktik_pedagogis']) ? $validated['praktik_pedagogis'] : null,
            'prinsip_pm' => array_values(array_filter(is_array($validated['prinsip_pm'] ?? null) ? $validated['prinsip_pm'] : [])),
            'pengalaman_belajar' => array_values(array_filter(is_array($validated['pengalaman_belajar'] ?? null) ? $validated['pengalaman_belajar'] : [])),
            'kegiatan' => $validated['kegiatan'],
            'asesmen' => ! empty($validated['asesmen']) ? $validated['asesmen'] : null,
            'jumlah_hadir' => $validated['jumlah_hadir'] ?? null,
            'jumlah_tidak_hadir' => $validated['jumlah_tidak_hadir'] ?? null,
            'catatan' => $validated['catatan'] ?? null,
            'tindak_lanjut' => $validated['tindak_lanjut'] ?? null,
        ]);

        AktivitasService::tambah(
            'Catat Jurnal Mengajar',
            $jurnal->tanggal->format('Y-m-d').' — '.$jurnal->mata_pelajaran.' ('.$jurnal->kelas.')',
            'jurnal_mengajar',
            $jurnal->id
        );

        return redirect()
            ->route('guru.perangkat.jurnal.index')
            ->with('success', 'Jurnal mengajar berhasil dicatat.');
    }

    public function jurnalDestroy(JurnalMengajar $jurnal): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $this->access->authorizeOwner($guru, $jurnal);
        $jurnal->delete();

        return redirect()
            ->route('guru.perangkat.jurnal.index')
            ->with('success', 'Entri jurnal mengajar dihapus.');
    }

    public function jurnalCetakPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->access->currentTenantId($guru);
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $query = JurnalMengajar::query()
            ->with('perangkat:id,jenis,judul')
            ->where('guru_id', $guru->id)
            ->orderBy('tanggal')
            ->orderBy('id');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        if ($dari) {
            $query->whereDate('tanggal', '>=', $dari);
        }
        if ($sampai) {
            $query->whereDate('tanggal', '<=', $sampai);
        }

        $entries = $query->get();
        $kop = $this->access->kopSekolah($tenantId);

        $pdf = Pdf::loadView('guru.perangkat.jurnal-pdf', [
            'guru' => $guru,
            'entries' => $entries,
            'kop' => $kop,
            'dari' => $dari,
            'sampai' => $sampai,
            'dicetakPada' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('rekap-jurnal-mengajar.pdf');
    }

    // ─── HELPERS INTERNAL ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function validatePerangkat(Request $request): array
    {
        $jenisValid = implode(',', array_keys(PerangkatPembelajaran::JENIS_LABELS));
        $faseValid = implode(',', PerangkatPembelajaran::FASE_VALID);

        return $request->validate([
            'jenis' => ['required', 'string', 'in:'.$jenisValid],
            'judul' => ['required', 'string', 'max:220'],
            'mata_pelajaran' => ['required', 'string', 'max:120'],
            'fase' => ['nullable', 'string', 'in:'.$faseValid],
            'rombel_id' => ['nullable', 'integer'],
            'kelas_digital_id' => ['nullable', 'integer'],
            'kelas' => ['nullable', 'string', 'max:60'],
            'tahun_ajaran' => ['nullable', 'string', 'max:30'],
            'semester' => ['nullable', 'string', 'in:Ganjil,Genap,Tahunan'],
            'alokasi_waktu' => ['nullable', 'string', 'max:80'],
            'ringkasan' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:draf,final'],
            'dibagikan_ke_walas' => ['nullable', 'boolean'],
            'lampiran' => ['nullable', 'file', 'max:10240'],
        ]);
    }

    /**
     * Ekstrak dan sanitasi struktur konten JSON sesuai jenis perangkat.
     *
     * @return array<string, mixed>
     */
    private function extractKonten(Request $request, string $jenis): array
    {
        $raw = $request->input('konten', []);
        if (! is_array($raw)) {
            $raw = [];
        }

        // Jika textarea baris-per-baris dipakai untuk daftar (TP/ATP/Prota/Promes)
        return match ($jenis) {
            PerangkatPembelajaran::JENIS_CP => [
                'elemen' => trim((string) ($raw['elemen'] ?? '')),
                'capaian_pembelajaran' => trim((string) ($raw['capaian_pembelajaran'] ?? '')),
                'kompetensi_inti' => trim((string) ($raw['kompetensi_inti'] ?? '')),
                'konten_materi' => trim((string) ($raw['konten_materi'] ?? '')),
                'catatan_fase' => trim((string) ($raw['catatan_fase'] ?? '')),
            ],
            PerangkatPembelajaran::JENIS_TP => [
                'elemen' => trim((string) ($raw['elemen'] ?? '')),
                'capaian_rujukan' => trim((string) ($raw['capaian_rujukan'] ?? '')),
                'daftar_tp' => $this->normalizeRows($raw['daftar_tp'] ?? [], ['kode', 'deskripsi', 'materi', 'jp']),
                'indikator_ketercapaian' => trim((string) ($raw['indikator_ketercapaian'] ?? '')),
            ],
            PerangkatPembelajaran::JENIS_ATP => [
                'elemen' => trim((string) ($raw['elemen'] ?? '')),
                'prasyarat' => trim((string) ($raw['prasyarat'] ?? '')),
                'alur_item' => $this->normalizeRows($raw['alur_item'] ?? [], ['urutan', 'kode_tp', 'tujuan_pembelajaran', 'materi_pokok', 'alokasi_jp', 'semester', 'dimensi_profil']),
            ],
            PerangkatPembelajaran::JENIS_PROTA => [
                'daftar_unit' => $this->normalizeRows($raw['daftar_unit'] ?? [], ['semester', 'nomor_unit', 'materi_topik', 'alokasi_jp', 'keterangan']),
                'catatan_tahunan' => trim((string) ($raw['catatan_tahunan'] ?? '')),
            ],
            PerangkatPembelajaran::JENIS_PROMES => [
                'bulan_efektif' => trim((string) ($raw['bulan_efektif'] ?? '')),
                'daftar_minggu' => $this->normalizeRows($raw['daftar_minggu'] ?? [], ['bulan', 'minggu_ke', 'materi_topik', 'alokasi_jp', 'keterangan']),
                'catatan_kalender' => trim((string) ($raw['catatan_kalender'] ?? '')),
            ],
            PerangkatPembelajaran::JENIS_MODUL_AJAR => [
                'tujuan_pembelajaran' => trim((string) ($raw['tujuan_pembelajaran'] ?? '')),
                'pm' => [
                    'berkesadaran' => ! empty($raw['pm']['berkesadaran']),
                    'bermakna' => ! empty($raw['pm']['bermakna']),
                    'menggembirakan' => ! empty($raw['pm']['menggembirakan']),
                ],
                'dimensi' => array_values(array_filter(
                    is_array($raw['dimensi'] ?? null) ? $raw['dimensi'] : [],
                    fn ($v) => is_string($v) && trim($v) !== ''
                )),
                'pengalaman' => [
                    'memahami' => trim((string) ($raw['pengalaman']['memahami'] ?? '')),
                    'mengaplikasi' => trim((string) ($raw['pengalaman']['mengaplikasi'] ?? '')),
                    'merefleksi' => trim((string) ($raw['pengalaman']['merefleksi'] ?? '')),
                ],
                'pendahuluan' => trim((string) ($raw['pendahuluan'] ?? '')),
                'kegiatan_inti' => trim((string) ($raw['kegiatan_inti'] ?? '')),
                'penutup' => trim((string) ($raw['penutup'] ?? '')),
                'kesiapan_murid' => trim((string) ($raw['kesiapan_murid'] ?? '')),
                'karakteristik_materi' => trim((string) ($raw['karakteristik_materi'] ?? '')),
                'model_pembelajaran' => trim((string) ($raw['model_pembelajaran'] ?? '')),
                'asesmen' => trim((string) ($raw['asesmen'] ?? '')),
                'lkpd' => trim((string) ($raw['lkpd'] ?? '')),
                'rubrik' => trim((string) ($raw['rubrik'] ?? '')),
                'media_sumber' => trim((string) ($raw['media_sumber'] ?? '')),
                'refleksi_guru' => trim((string) ($raw['refleksi_guru'] ?? '')),
            ],
            PerangkatPembelajaran::JENIS_KKM => [
                'kompleksitas' => is_numeric($raw['kompleksitas'] ?? null) ? (int) $raw['kompleksitas'] : 75,
                'daya_dukung' => is_numeric($raw['daya_dukung'] ?? null) ? (int) $raw['daya_dukung'] : 75,
                'intake' => is_numeric($raw['intake'] ?? null) ? (int) $raw['intake'] : 75,
                'nilai_kkm' => is_numeric($raw['nilai_kkm'] ?? null) ? (int) $raw['nilai_kkm'] : 75,
                'interval_perlu_bimbingan' => trim((string) ($raw['interval_perlu_bimbingan'] ?? '')),
                'interval_cukup' => trim((string) ($raw['interval_cukup'] ?? '')),
                'interval_baik' => trim((string) ($raw['interval_baik'] ?? '')),
                'interval_sangat_baik' => trim((string) ($raw['interval_sangat_baik'] ?? '')),
                'catatan_penilaian' => trim((string) ($raw['catatan_penilaian'] ?? '')),
            ],
            default => [],
        };
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array<string, string>>
     */
    private function normalizeRows(mixed $rows, array $keys): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = [];
            $hasValue = false;
            foreach ($keys as $k) {
                $val = trim((string) ($row[$k] ?? ''));
                $item[$k] = $val;
                if ($val !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $clean[] = $item;
            }
        }

        return $clean;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function allTemplates(): array
    {
        $out = [];
        foreach (array_keys(PerangkatPembelajaran::JENIS_LABELS) as $jenis) {
            $out[$jenis] = $this->access->templateForJenis($jenis);
        }

        return $out;
    }

    private function rombelOptions(User $guru)
    {
        $tenantId = $this->access->currentTenantId($guru);

        return Rombel::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_rombel')
            ->get(['id', 'nama_rombel', 'tingkat']);
    }

    private function kelasDigitalOptions(User $guru)
    {
        $tenantId = $this->access->currentTenantId($guru);

        return KelasDigital::query()
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_kelas')
            ->get(['id', 'nama_kelas', 'mata_pelajaran', 'kelas']);
    }

    private function semesterAktif(User $guru): ?Semester
    {
        return Semester::query()->orderByDesc('semester_id')->first();
    }

    private function resolveRombel(?int $rombelId, ?int $tenantId): ?Rombel
    {
        if (! $rombelId) {
            return null;
        }

        return Rombel::query()
            ->where('id', $rombelId)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    private function resolveKelasDigital(?int $kelasDigitalId, User $guru, ?int $tenantId): ?KelasDigital
    {
        if (! $kelasDigitalId) {
            return null;
        }

        return KelasDigital::query()
            ->where('id', $kelasDigitalId)
            ->where('guru_id', $guru->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }
}
