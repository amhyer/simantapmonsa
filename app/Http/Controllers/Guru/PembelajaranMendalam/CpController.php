<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\User;
use App\Services\PembelajaranMendalam\GeminiPembelajaranService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CpController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly GeminiPembelajaranService $ai,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $mapelFilter = trim((string) $request->query('mapel', ''));
        $faseFilter = trim((string) $request->query('fase', ''));
        $cari = trim((string) $request->query('q', ''));

        $query = CapaianPembelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_mapel')
            ->orderBy('fase')
            ->orderBy('urutan');

        if ($mapelFilter !== '') {
            $query->where('nama_mapel', $mapelFilter);
        }
        if ($faseFilter !== '') {
            $query->where('fase', $faseFilter);
        }
        if ($cari !== '') {
            $query->where(function ($q) use ($cari): void {
                $q->where('elemen', 'like', "%{$cari}%")
                    ->orWhere('deskripsi', 'like', "%{$cari}%")
                    ->orWhere('nama_mapel', 'like', "%{$cari}%");
            });
        }

        return view('guru.pm.cp.index', [
            'cpList' => $query->paginate(20)->withQueryString(),
            'mapelList' => $this->ctx->daftarMapel(),
            'faseLabels' => CapaianPembelajaran::FASE_LABELS,
            'mapelFilter' => $mapelFilter,
            'faseFilter' => $faseFilter,
            'cari' => $cari,
            'aiQuota' => $this->ai->quotaStatus($tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'fase' => ['required', 'in:A,B,C,D,E,F'],
            'elemen' => ['nullable', 'string', 'max:150'],
            'deskripsi' => ['required', 'string'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $mapelId = $this->ctx->daftarMapel()->firstWhere('nama', $data['nama_mapel'])?->id;

        CapaianPembelajaran::create([
            'tenant_id' => $tenantId,
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapelId,
            'nama_mapel' => $data['nama_mapel'],
            'fase' => $data['fase'],
            'elemen' => ! empty($data['elemen']) ? $data['elemen'] : 'Umum',
            'deskripsi' => $data['deskripsi'],
            'urutan' => (int) ($data['urutan'] ?? 1),
            'sumber' => 'manual',
        ]);

        return back()->with('success', 'Capaian Pembelajaran (CP) berhasil ditambahkan.');
    }

    public function update(Request $request, CapaianPembelajaran $cp): RedirectResponse
    {
        Gate::authorize('update', $cp);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'fase' => ['required', 'in:A,B,C,D,E,F'],
            'elemen' => ['nullable', 'string', 'max:150'],
            'deskripsi' => ['required', 'string'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $cp->update([
            'nama_mapel' => $data['nama_mapel'],
            'fase' => $data['fase'],
            'elemen' => ! empty($data['elemen']) ? $data['elemen'] : 'Umum',
            'deskripsi' => $data['deskripsi'],
            'urutan' => (int) ($data['urutan'] ?? $cp->urutan),
        ]);

        return back()->with('success', 'Capaian Pembelajaran berhasil diperbarui.');
    }

    public function destroy(CapaianPembelajaran $cp): RedirectResponse
    {
        Gate::authorize('delete', $cp);
        $cp->delete();

        return back()->with('success', 'Capaian Pembelajaran berhasil dihapus.');
    }

    public function aiGenerate(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'fase' => ['required', 'in:A,B,C,D,E,F'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $items = $this->ai->susunCp($data['nama_mapel'], $data['fase'], $data['catatan'] ?? null, $tenantId, $guru->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $mapelId = $this->ctx->daftarMapel()->firstWhere('nama', $data['nama_mapel'])?->id;
        $urutan = (int) CapaianPembelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $data['nama_mapel'])
            ->where('fase', $data['fase'])
            ->max('urutan');

        foreach ($items as $item) {
            $urutan++;
            CapaianPembelajaran::create([
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $mapelId,
                'nama_mapel' => $data['nama_mapel'],
                'fase' => $data['fase'],
                'elemen' => $item['elemen'],
                'deskripsi' => $item['deskripsi'],
                'urutan' => $urutan,
                'sumber' => 'ai',
            ]);
        }

        return redirect()
            ->route('guru.pm.cp.index', ['mapel' => $data['nama_mapel'], 'fase' => $data['fase']])
            ->with('success', count($items).' draf Capaian Pembelajaran berhasil disusun dengan AI.');
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $rows = CapaianPembelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($request->query('mapel'), fn ($q, $m) => $q->where('nama_mapel', $m))
            ->when($request->query('fase'), fn ($q, $f) => $q->where('fase', $f))
            ->orderBy('nama_mapel')
            ->orderBy('fase')
            ->orderBy('urutan')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Capaian Pembelajaran');
        $sheet->fromArray(['No', 'Mata Pelajaran', 'Fase', 'Elemen', 'Capaian Pembelajaran'], null, 'A1');

        $r = 2;
        foreach ($rows as $idx => $item) {
            $sheet->fromArray([$idx + 1, $item->nama_mapel, 'Fase '.$item->fase, $item->elemen, $item->deskripsi], null, "A{$r}");
            $r++;
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'capaian-pembelajaran.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importExcel(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $request->validate([
            'file_excel' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file_excel')->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membaca file Excel: '.$e->getMessage());
        }

        $mapelByName = $this->ctx->daftarMapel()->keyBy('nama');
        $imported = 0;

        foreach ($rows as $idx => $row) {
            if ($idx === 0) {
                continue; // lewati header
            }
            // Format kolom: [No, Mata Pelajaran, Fase, Elemen, Capaian Pembelajaran] atau [Mata Pelajaran, Fase, Elemen, Deskripsi]
            $offset = is_numeric($row[0] ?? null) ? 1 : 0;
            $namaMapel = trim((string) ($row[$offset] ?? ''));
            $faseRaw = strtoupper(trim(str_ireplace('FASE', '', (string) ($row[$offset + 1] ?? 'A'))));
            $elemen = trim((string) ($row[$offset + 2] ?? '')) ?: 'Umum';
            $deskripsi = trim((string) ($row[$offset + 3] ?? ''));

            if ($namaMapel === '' || $deskripsi === '' || ! in_array($faseRaw, ['A', 'B', 'C', 'D', 'E', 'F'], true)) {
                continue;
            }

            CapaianPembelajaran::create([
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $mapelByName->get($namaMapel)?->id,
                'nama_mapel' => $namaMapel,
                'fase' => $faseRaw,
                'elemen' => $elemen,
                'deskripsi' => $deskripsi,
                'urutan' => $imported + 1,
                'sumber' => 'import',
            ]);
            $imported++;
        }

        if ($imported === 0) {
            return back()->with('error', 'Tidak ada baris CP valid yang ditemukan. Gunakan format kolom Export Excel.');
        }

        return back()->with('success', "{$imported} Capaian Pembelajaran berhasil diimpor dari Excel.");
    }

    /**
     * Muat CP standar Kurikulum Merdeka SD (acuan BSKAP No. 046/H/KR/2025) secara idempoten.
     */
    public function muatStandarBskap(): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $daftarMapel = $this->ctx->daftarMapel();

        $standar = [
            ['mapel' => 'Matematika', 'fase' => 'A', 'elemen' => 'Bilangan', 'deskripsi' => 'Murid menunjukkan pemahaman dan memiliki intuisi bilangan (number sense) pada bilangan cacah sampai 100, serta melakukan operasi penjumlahan dan pengurangan menggunakan benda konkret.'],
            ['mapel' => 'Matematika', 'fase' => 'A', 'elemen' => 'Pengukuran & Geometri', 'deskripsi' => 'Murid membandingkan panjang, berat, dan durasi waktu secara langsung serta mengenal berbagai bangun datar dan bangun ruang sederhana di lingkungan sekitar.'],
            ['mapel' => 'Matematika', 'fase' => 'B', 'elemen' => 'Bilangan', 'deskripsi' => 'Murid menunjukkan pemahaman dan intuisi bilangan cacah sampai 10.000, melakukan operasi hitung penjumlahan, pengurangan, perkalian, pembagian, serta mengenal pecahan senilai.'],
            ['mapel' => 'Matematika', 'fase' => 'C', 'elemen' => 'Bilangan & Aljabar', 'deskripsi' => 'Murid menunjukkan pemahaman bilangan cacah sampai 1.000.000, bilangan desimal, pecahan, rasio, serta menyelesaikan masalah kontekstual menggunakan penalaran proporsional.'],
            ['mapel' => 'Bahasa Indonesia', 'fase' => 'A', 'elemen' => 'Menyimak & Membaca', 'deskripsi' => 'Murid mampu bersikap menjadi pendengar yang penuh perhatian, memahami pesan lisan, serta membaca kata-kata sederhana yang dikenalnya sehari-hari dengan fasih.'],
            ['mapel' => 'Bahasa Indonesia', 'fase' => 'B', 'elemen' => 'Membaca & Memirsa', 'deskripsi' => 'Murid mampu memahami pesan dan informasi tentang kehidupan sehari-hari, teks narasi, dan puisi anak dalam bentuk cetak atau elektronik.'],
            ['mapel' => 'Bahasa Indonesia', 'fase' => 'C', 'elemen' => 'Menulis & Berbicara', 'deskripsi' => 'Murid mampu menyampaikan informasi secara lisan maupun tulis secara logis, kritis, dan kreatif berdasarkan fakta serta pengalaman dalam teks eksplanasi dan persuasi.'],
            ['mapel' => 'Pendidikan Pancasila', 'fase' => 'A', 'elemen' => 'Pancasila', 'deskripsi' => 'Murid mengenal bendera negara, lagu kebangsaan, simbol dan sila-sila Pancasila, serta menerapkan nilai-nilai Pancasila di lingkungan keluarga dan sekolah.'],
            ['mapel' => 'Pendidikan Pancasila', 'fase' => 'B', 'elemen' => 'UUD NRI Tahun 1945', 'deskripsi' => 'Murid mengidentifikasi aturan di sekolah dan lingkungan tempat tinggal serta melaksanakan hak dan kewajiban sebagai anggota keluarga dan warga sekolah.'],
            ['mapel' => 'Pendidikan Pancasila', 'fase' => 'C', 'elemen' => 'Bhinneka Tunggal Ika', 'deskripsi' => 'Murid menyajikan hasil identifikasi sikap menghormati, menjaga, dan melestarikan keberagaman budaya dalam bingkai Bhinneka Tunggal Ika di lingkungan sekitar.'],
            ['mapel' => 'Ilmu Pengetahuan Alam dan Sosial (IPAS)', 'fase' => 'B', 'elemen' => 'Pemahaman IPAS', 'deskripsi' => 'Murid menganalisis hubungan antara bentuk serta fungsi bagian tubuh manusia, siklus hidup makhluk hidup, perubahan wujud zat, serta keragaman sosial budaya di daerahnya.'],
            ['mapel' => 'Ilmu Pengetahuan Alam dan Sosial (IPAS)', 'fase' => 'C', 'elemen' => 'Pemahaman IPAS', 'deskripsi' => 'Murid melakukan simulasi menggunakan gambar/bagan tentang sistem organ tubuh manusia, ekosistem, tata surya, serta menganalisis sejarah dan kegiatan ekonomi masyarakat Indonesia.'],
        ];

        $added = 0;
        foreach ($standar as $idx => $item) {
            $matched = $daftarMapel->first(function ($m) use ($item): bool {
                $a = mb_strtolower($m->nama);
                $b = mb_strtolower($item['mapel']);

                return $a === $b || str_contains($a, $b) || str_contains($b, $a);
            });

            $namaMapel = $matched ? $matched->nama : $item['mapel'];
            $exists = CapaianPembelajaran::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('nama_mapel', $namaMapel)
                ->where('fase', $item['fase'])
                ->where('elemen', $item['elemen'])
                ->exists();

            if ($exists) {
                continue;
            }

            CapaianPembelajaran::create([
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $matched?->id,
                'nama_mapel' => $namaMapel,
                'fase' => $item['fase'],
                'elemen' => $item['elemen'],
                'deskripsi' => $item['deskripsi'],
                'urutan' => $idx + 1,
                'sumber' => 'bskap_2025',
            ]);
            $added++;
        }

        return back()->with('success', $added > 0
            ? "{$added} Capaian Pembelajaran standar BSKAP 2025 berhasil dimuat."
            : 'Seluruh CP standar BSKAP 2025 sudah tersedia di bank CP Anda.');
    }

    public function cetakPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $rows = CapaianPembelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($request->query('mapel'), fn ($q, $m) => $q->where('nama_mapel', $m))
            ->when($request->query('fase'), fn ($q, $f) => $q->where('fase', $f))
            ->orderBy('nama_mapel')
            ->orderBy('fase')
            ->orderBy('urutan')
            ->get();

        $pdf = Pdf::loadView('guru.pm.pdf.cp', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'cpList' => $rows,
            'mapelFilter' => $request->query('mapel'),
            'faseFilter' => $request->query('fase'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('capaian-pembelajaran.pdf');
    }

    private function authorizeTenant(CapaianPembelajaran $cp): void
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        if ($tenantId !== null && (int) $cp->tenant_id !== $tenantId) {
            abort(403);
        }
    }
}
