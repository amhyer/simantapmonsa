<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\KokurikulerPm;
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KokurikulerPmController extends Controller
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
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());
        $tingkatFilter = $request->query('tingkat') ? (int) $request->query('tingkat') : null;

        $list = KokurikulerPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->when($tingkatFilter !== null, fn ($q) => $q->where('tingkat', $tingkatFilter))
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('guru.pm.kokurikuler.index', [
            'tahunAjaran' => $tahunAjaran,
            'tingkatFilter' => $tingkatFilter,
            'list' => $list,
            'bentukLabels' => KokurikulerPm::BENTUK_LABELS,
            'dimensiList' => KokurikulerPm::DIMENSI_PROFIL_LULUSAN,
            'aiQuota' => $this->ai->quotaStatus($tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'tema_judul' => ['required', 'string', 'max:255'],
            'bentuk_kegiatan' => ['required', 'in:kolaboratif_lintas_disiplin,tujuh_kebiasaan,lainnya'],
            'dimensi_profil' => ['nullable', 'array'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tujuan' => ['nullable', 'string'],
            'rangkaian_kegiatan' => ['nullable', 'string'],
            'mitra' => ['nullable', 'string', 'max:255'],
            'refleksi_hasil' => ['nullable', 'string'],
        ]);

        KokurikulerPm::create([
            'tenant_id' => $tenantId,
            'guru_id' => $guru->id,
            'tahun_ajaran' => $data['tahun_ajaran'],
            'tingkat' => (int) $data['tingkat'],
            'tema_judul' => $data['tema_judul'],
            'bentuk_kegiatan' => $data['bentuk_kegiatan'],
            'dimensi_profil' => array_values(array_filter(is_array($data['dimensi_profil'] ?? null) ? $data['dimensi_profil'] : [])),
            'tanggal_mulai' => ! empty($data['tanggal_mulai']) ? $data['tanggal_mulai'] : null,
            'tanggal_selesai' => ! empty($data['tanggal_selesai']) ? $data['tanggal_selesai'] : null,
            'tujuan' => $data['tujuan'] ?? null,
            'rangkaian_kegiatan' => $data['rangkaian_kegiatan'] ?? null,
            'mitra' => $data['mitra'] ?? null,
            'refleksi_hasil' => $data['refleksi_hasil'] ?? null,
            'sumber' => 'manual',
        ]);

        return back()->with('success', 'Rencana kegiatan Kokurikuler berhasil disimpan.');
    }

    public function destroy(KokurikulerPm $kokurikuler): RedirectResponse
    {
        Gate::authorize('delete', $kokurikuler);
        $kokurikuler->delete();

        return back()->with('success', 'Kegiatan Kokurikuler berhasil dihapus.');
    }

    public function aiGenerate(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'tema_ide' => ['required', 'string', 'max:255'],
            'bentuk_kegiatan' => ['required', 'in:kolaboratif_lintas_disiplin,tujuh_kebiasaan,lainnya'],
        ]);

        $bentukLabel = KokurikulerPm::BENTUK_LABELS[$data['bentuk_kegiatan']] ?? $data['bentuk_kegiatan'];

        try {
            $aiRes = $this->ai->susunKokurikuler(
                (int) $data['tingkat'],
                $data['tema_ide'],
                $bentukLabel,
                $tenantId,
                $guru->id
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        KokurikulerPm::create([
            'tenant_id' => $tenantId,
            'guru_id' => $guru->id,
            'tahun_ajaran' => $data['tahun_ajaran'],
            'tingkat' => (int) $data['tingkat'],
            'tema_judul' => (string) ($aiRes['tema_judul'] ?? $data['tema_ide']),
            'bentuk_kegiatan' => $data['bentuk_kegiatan'],
            'dimensi_profil' => is_array($aiRes['dimensi_profil'] ?? null) ? $aiRes['dimensi_profil'] : ['Kolaborasi', 'Kreativitas'],
            'tanggal_mulai' => now()->toDateString(),
            'tujuan' => (string) ($aiRes['tujuan'] ?? ''),
            'rangkaian_kegiatan' => (string) ($aiRes['rangkaian_kegiatan'] ?? ''),
            'mitra' => (string) ($aiRes['mitra'] ?? ''),
            'refleksi_hasil' => (string) ($aiRes['refleksi_hasil'] ?? ''),
            'sumber' => 'ai',
        ]);

        return back()->with('success', 'Rancangan kegiatan Kokurikuler berhasil disusun dengan AI.');
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());

        $rows = KokurikulerPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('tingkat')
            ->orderBy('tanggal_mulai')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kokurikuler');
        $sheet->fromArray(['No', 'Kelas', 'Tema / Judul', 'Bentuk Kegiatan', 'Dimensi Profil Lulusan', 'Tanggal Mulai', 'Tujuan', 'Mitra'], null, 'A1');

        $r = 2;
        foreach ($rows as $i => $item) {
            $sheet->fromArray([
                $i + 1,
                'Kelas '.$item->tingkat,
                $item->tema_judul,
                $item->bentukLabel(),
                implode(', ', is_array($item->dimensi_profil) ? $item->dimensi_profil : []),
                $item->tanggal_mulai?->format('Y-m-d') ?? '-',
                $item->tujuan ?? '',
                $item->mitra ?? '',
            ], null, "A{$r}");
            $r++;
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'rencana-kokurikuler.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function cetakPdf(Request $request): Response
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = (string) $request->query('tahun_ajaran', $this->ctx->tahunAjaranAktif());

        $rows = KokurikulerPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('tingkat')
            ->orderBy('tanggal_mulai')
            ->get();

        $pdf = Pdf::loadView('guru.pm.pdf.kokurikuler', [
            'sekolah' => $this->ctx->sekolah($tenantId),
            'tahunAjaran' => $tahunAjaran,
            'rows' => $rows,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('rencana-kokurikuler.pdf');
    }
}
