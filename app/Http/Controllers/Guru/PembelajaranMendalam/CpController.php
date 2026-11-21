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
