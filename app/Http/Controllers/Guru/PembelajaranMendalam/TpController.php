<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGuru;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\User;
use App\Services\PembelajaranMendalam\GeminiPembelajaranService;
use App\Services\PembelajaranMendalam\GuruScopeService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TpController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly GeminiPembelajaranService $ai,
        private readonly GuruScopeService $scope,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $guruScope = $this->scope->resolveForGuru($guru, $tenantId);
        $mapelList = $this->scope->filterMapelCollection($this->ctx->daftarMapel(), $guru, $tenantId);

        $mapelFilter = trim((string) $request->query('mapel', ''));
        $faseFilter = trim((string) $request->query('fase', ''));
        $cari = trim((string) $request->query('q', ''));

        if ($faseFilter !== '') {
            $this->scope->authorizeFase($guru, $faseFilter, $tenantId);
        }
        if ($mapelFilter !== '') {
            $this->scope->authorizeMapel($guru, $mapelFilter, $tenantId);
        }

        $query = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($guruScope['is_scoped'], fn ($q) => $q->whereIn('fase', $guruScope['fase_list']))
            ->when($guruScope['is_scoped'] && ! $guruScope['is_wali_kelas'] && ! empty($guruScope['mapel_list']), fn ($q) => $q->whereIn('nama_mapel', $guruScope['mapel_list']))
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
                $q->where('kode_tp', 'like', "%{$cari}%")
                    ->orWhere('elemen', 'like', "%{$cari}%")
                    ->orWhere('deskripsi', 'like', "%{$cari}%");
            });
        }

        $cpList = CapaianPembelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($guruScope['is_scoped'], fn ($q) => $q->whereIn('fase', $guruScope['fase_list']))
            ->when($guruScope['is_scoped'] && ! $guruScope['is_wali_kelas'] && ! empty($guruScope['mapel_list']), fn ($q) => $q->whereIn('nama_mapel', $guruScope['mapel_list']))
            ->orderBy('nama_mapel')
            ->orderBy('fase')
            ->get(['id', 'nama_mapel', 'fase', 'elemen']);

        return view('guru.pm.tp.index', [
            'tpList' => $query->paginate(20)->withQueryString(),
            'cpList' => $cpList,
            'mapelList' => $mapelList,
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
        $this->scope->authorizeNotLockedBySupervisi($guru, $tenantId);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'fase' => ['required', 'in:A,B,C,D,E,F'],
            'cp_id' => ['nullable', 'integer'],
            'elemen' => ['nullable', 'string', 'max:150'],
            'kode_tp' => ['nullable', 'string', 'max:40'],
            'deskripsi' => ['required', 'string'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $this->scope->authorizeFase($guru, $data['fase'], $tenantId);
        $this->scope->authorizeMapel($guru, $data['nama_mapel'], $tenantId);

        $mapelId = $this->ctx->daftarMapel()->firstWhere('nama', $data['nama_mapel'])?->id;

        TujuanPembelajaranPm::create([
            'tenant_id' => $tenantId,
            'guru_id' => $guru->id,
            'cp_id' => ! empty($data['cp_id']) ? (int) $data['cp_id'] : null,
            'mata_pelajaran_id' => $mapelId,
            'nama_mapel' => $data['nama_mapel'],
            'fase' => $data['fase'],
            'elemen' => ! empty($data['elemen']) ? $data['elemen'] : 'Umum',
            'kode_tp' => ! empty($data['kode_tp']) ? $data['kode_tp'] : null,
            'deskripsi' => $data['deskripsi'],
            'urutan' => (int) ($data['urutan'] ?? 1),
            'sumber' => 'manual',
        ]);

        return back()->with('success', 'Tujuan Pembelajaran (TP) berhasil ditambahkan ke bank TP.');
    }

    public function update(Request $request, TujuanPembelajaranPm $tp): RedirectResponse
    {
        Gate::authorize('update', $tp);
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'fase' => ['required', 'in:A,B,C,D,E,F'],
            'elemen' => ['nullable', 'string', 'max:150'],
            'kode_tp' => ['nullable', 'string', 'max:40'],
            'deskripsi' => ['required', 'string'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $this->scope->authorizeFase($guru, $data['fase'], $tenantId);
        $this->scope->authorizeMapel($guru, $data['nama_mapel'], $tenantId);

        $tp->update([
            'nama_mapel' => $data['nama_mapel'],
            'fase' => $data['fase'],
            'elemen' => ! empty($data['elemen']) ? $data['elemen'] : 'Umum',
            'kode_tp' => ! empty($data['kode_tp']) ? $data['kode_tp'] : null,
            'deskripsi' => $data['deskripsi'],
            'urutan' => (int) ($data['urutan'] ?? $tp->urutan),
        ]);

        return back()->with('success', 'Tujuan Pembelajaran berhasil diperbarui.');
    }

    public function destroy(TujuanPembelajaranPm $tp): RedirectResponse
    {
        Gate::authorize('delete', $tp);
        $tp->delete();

        return back()->with('success', 'Tujuan Pembelajaran berhasil dihapus.');
    }

    public function aiGenerate(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $this->scope->authorizeNotLockedBySupervisi($guru, $tenantId);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'fase' => ['required', 'in:A,B,C,D,E,F'],
            'elemen' => ['nullable', 'string', 'max:150'],
            'jumlah' => ['nullable', 'integer', 'min:1', 'max:15'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->scope->authorizeFase($guru, $data['fase'], $tenantId);
        $this->scope->authorizeMapel($guru, $data['nama_mapel'], $tenantId);

        $acuanCp = CapaianPembelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $data['nama_mapel'])
            ->where('fase', $data['fase'])
            ->get()
            ->map(fn (CapaianPembelajaran $cp) => "[{$cp->elemen}] {$cp->deskripsi}")
            ->implode("\n");

        try {
            $items = $this->ai->susunTp(
                $data['nama_mapel'],
                $data['fase'],
                $data['elemen'] ?? null,
                (int) ($data['jumlah'] ?? 6),
                $data['catatan'] ?? null,
                $acuanCp !== '' ? $acuanCp : null,
                $tenantId,
                $guru->id
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $mapelId = $this->ctx->daftarMapel()->firstWhere('nama', $data['nama_mapel'])?->id;
        $urutan = (int) TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('nama_mapel', $data['nama_mapel'])
            ->where('fase', $data['fase'])
            ->max('urutan');

        foreach ($items as $item) {
            $urutan++;
            TujuanPembelajaranPm::create([
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $mapelId,
                'nama_mapel' => $data['nama_mapel'],
                'fase' => $data['fase'],
                'elemen' => $item['elemen'],
                'kode_tp' => $item['kode_tp'],
                'deskripsi' => $item['deskripsi'],
                'urutan' => $urutan,
                'sumber' => 'ai',
            ]);
        }

        return redirect()
            ->route('guru.pm.tp.index', ['mapel' => $data['nama_mapel'], 'fase' => $data['fase']])
            ->with('success', count($items).' draf Tujuan Pembelajaran berhasil disusun dengan AI.');
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $guruScope = $this->scope->resolveForGuru($guru, $tenantId);

        if ($request->query('fase')) {
            $this->scope->authorizeFase($guru, (string) $request->query('fase'), $tenantId);
        }
        if ($request->query('mapel')) {
            $this->scope->authorizeMapel($guru, (string) $request->query('mapel'), $tenantId);
        }

        $rows = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($guruScope['is_scoped'], fn ($q) => $q->whereIn('fase', $guruScope['fase_list']))
            ->when($guruScope['is_scoped'] && ! $guruScope['is_wali_kelas'] && ! empty($guruScope['mapel_list']), fn ($q) => $q->whereIn('nama_mapel', $guruScope['mapel_list']))
            ->when($request->query('mapel'), fn ($q, $m) => $q->where('nama_mapel', $m))
            ->when($request->query('fase'), fn ($q, $f) => $q->where('fase', $f))
            ->orderBy('nama_mapel')
            ->orderBy('fase')
            ->orderBy('urutan')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tujuan Pembelajaran');
        $sheet->fromArray(['No', 'Mata Pelajaran', 'Fase', 'Elemen', 'Kode TP', 'Rumusan Tujuan Pembelajaran'], null, 'A1');

        $r = 2;
        foreach ($rows as $idx => $item) {
            $sheet->fromArray([
                $idx + 1,
                $item->nama_mapel,
                'Fase '.$item->fase,
                $item->elemen,
                $item->kode_tp,
                $item->deskripsi,
            ], null, "A{$r}");
            $r++;
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'tujuan-pembelajaran.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importExcel(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $this->scope->authorizeNotLockedBySupervisi($guru, $tenantId);
        $guruScope = $this->scope->resolveForGuru($guru, $tenantId);

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
                continue;
            }
            // Format kolom: [No, Mata Pelajaran, Fase, Elemen, Kode TP, Rumusan Tujuan Pembelajaran]
            $offset = is_numeric($row[0] ?? null) ? 1 : 0;
            $namaMapel = trim((string) ($row[$offset] ?? ''));
            $faseRaw = strtoupper(trim(str_ireplace('FASE', '', (string) ($row[$offset + 1] ?? 'A'))));
            $elemen = trim((string) ($row[$offset + 2] ?? '')) ?: 'Umum';
            $kodeTp = trim((string) ($row[$offset + 3] ?? '')) ?: null;
            $deskripsi = trim((string) ($row[$offset + 4] ?? ''));

            if ($namaMapel === '' || $deskripsi === '' || ! in_array($faseRaw, ['A', 'B', 'C', 'D', 'E', 'F'], true)) {
                continue;
            }
            if ($guruScope['is_scoped'] && ! in_array($faseRaw, $guruScope['fase_list'], true)) {
                continue;
            }
            if ($guruScope['is_scoped'] && ! $guruScope['is_wali_kelas'] && ! empty($guruScope['mapel_list'])) {
                $allowedMapel = collect($guruScope['mapel_list'])->contains(fn (string $m) => mb_strtolower(trim($m)) === mb_strtolower($namaMapel));
                if (! $allowedMapel) {
                    continue;
                }
            }

            TujuanPembelajaranPm::create([
                'tenant_id' => $tenantId,
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $mapelByName->get($namaMapel)?->id,
                'nama_mapel' => $namaMapel,
                'fase' => $faseRaw,
                'elemen' => $elemen,
                'kode_tp' => $kodeTp,
                'deskripsi' => $deskripsi,
                'urutan' => $imported + 1,
                'sumber' => 'import',
            ]);
            $imported++;
        }

        if ($imported === 0) {
            return back()->with('error', 'Tidak ada baris TP valid sesuai fase/mapel Anda yang ditemukan. Gunakan format kolom Export Excel.');
        }

        return back()->with('success', "{$imported} Tujuan Pembelajaran berhasil diimpor dari Excel.");
    }

    /**
     * Sinkronisasi 1-klik Bank TP Pembelajaran Mendalam -> TP e-Rapor (PengaturanGuru.pengaturan['tujuan_pembelajaran']).
     */
    public function sinkronErapor(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $guruScope = $this->scope->resolveForGuru($guru, $tenantId);
        $mapelFilter = trim((string) $request->input('mapel', ''));
        $faseFilter = trim((string) $request->input('fase', ''));

        if ($faseFilter !== '') {
            $this->scope->authorizeFase($guru, $faseFilter, $tenantId);
        }
        if ($mapelFilter !== '') {
            $this->scope->authorizeMapel($guru, $mapelFilter, $tenantId);
        }

        $tpRows = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(function ($q) use ($guru): void {
                $q->where('guru_id', $guru->id)->orWhereNull('guru_id');
            })
            ->when($guruScope['is_scoped'], fn ($q) => $q->whereIn('fase', $guruScope['fase_list']))
            ->when($guruScope['is_scoped'] && ! $guruScope['is_wali_kelas'] && ! empty($guruScope['mapel_list']), fn ($q) => $q->whereIn('nama_mapel', $guruScope['mapel_list']))
            ->when($mapelFilter !== '', fn ($q) => $q->where('nama_mapel', $mapelFilter))
            ->when($faseFilter !== '', fn ($q) => $q->where('fase', $faseFilter))
            ->orderBy('nama_mapel')
            ->orderBy('urutan')
            ->get();

        if ($tpRows->isEmpty()) {
            return back()->with('error', 'Belum ada Tujuan Pembelajaran milik Anda di Bank TP untuk disinkronkan.');
        }

        $pengaturan = PengaturanGuru::firstOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'kkm' => 75,
                'semester' => 'Ganjil',
                'tahun_pelajaran' => $this->ctx->tahunAjaranAktif(),
            ]
        );

        $cfg = is_array($pengaturan->pengaturan) ? $pengaturan->pengaturan : [];
        $existing = is_array($cfg['tujuan_pembelajaran'] ?? null) ? $cfg['tujuan_pembelajaran'] : [];

        $sudahAdaKeys = [];
        foreach ($existing as $ex) {
            if (is_array($ex)) {
                $k = mb_strtolower(trim((string) ($ex['mata_pelajaran'] ?? ''))).'|'.mb_strtolower(trim((string) ($ex['deskripsi'] ?? '')));
                $sudahAdaKeys[$k] = true;
            }
        }

        $added = 0;
        $skipped = 0;
        foreach ($tpRows as $i => $tp) {
            $desk = mb_substr(trim($tp->deskripsi), 0, 300);
            $key = mb_strtolower(trim($tp->nama_mapel)).'|'.mb_strtolower($desk);
            if (isset($sudahAdaKeys[$key])) {
                $skipped++;
                continue;
            }

            $faseLabel = in_array($tp->fase, ['A', 'B', 'C'], true) ? ('Fase '.$tp->fase) : 'Fase A';
            $existing[] = [
                'id' => (string) Str::uuid(),
                'kode' => $tp->kode_tp ?: ('TP.'.($i + 1)),
                'mata_pelajaran' => $tp->nama_mapel,
                'fase' => $faseLabel,
                'semester' => (int) $tp->semester_target === 2 ? 'Genap' : 'Ganjil',
                'deskripsi' => $desk,
                'created_at' => now()->toDateTimeString(),
            ];
            $sudahAdaKeys[$key] = true;
            $added++;
        }

        $cfg['tujuan_pembelajaran'] = array_values($existing);
        $pengaturan->update(['pengaturan' => $cfg, 'pembaruan' => now()]);

        return back()->with('success', "Sinkronisasi ke TP e-Rapor selesai: {$added} TP baru ditambahkan ({$skipped} sudah ada sebelumnya).");
    }

    public function cetakPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $guruScope = $this->scope->resolveForGuru($guru, $tenantId);

        if ($request->query('fase')) {
            $this->scope->authorizeFase($guru, (string) $request->query('fase'), $tenantId);
        }
        if ($request->query('mapel')) {
            $this->scope->authorizeMapel($guru, (string) $request->query('mapel'), $tenantId);
        }

        $rows = TujuanPembelajaranPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($guruScope['is_scoped'], fn ($q) => $q->whereIn('fase', $guruScope['fase_list']))
            ->when($guruScope['is_scoped'] && ! $guruScope['is_wali_kelas'] && ! empty($guruScope['mapel_list']), fn ($q) => $q->whereIn('nama_mapel', $guruScope['mapel_list']))
            ->when($request->query('mapel'), fn ($q, $m) => $q->where('nama_mapel', $m))
            ->when($request->query('fase'), fn ($q, $f) => $q->where('fase', $f))
            ->orderBy('nama_mapel')
            ->orderBy('fase')
            ->orderBy('urutan')
            ->get();

        $pdf = Pdf::loadView('guru.pm.pdf.tp', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'tpList' => $rows,
            'mapelFilter' => $request->query('mapel'),
            'faseFilter' => $request->query('fase'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('tujuan-pembelajaran.pdf');
    }

    private function authorizeTenant(TujuanPembelajaranPm $tp): void
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        if ($tenantId !== null && (int) $tp->tenant_id !== $tenantId) {
            abort(403);
        }
    }
}
