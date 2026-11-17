<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\User;
use App\Services\Perangkat\PerangkatAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Supervisi read-only Perangkat Ajar (CP, TP, ATP, Prota, Promes, Modul Ajar, KKM)
 * dan Jurnal Mengajar oleh Kepala Sekolah — 100% terisolasi per tenant_id.
 */
class PerangkatSupervisiController extends Controller
{
    public function __construct(private readonly PerangkatAccessService $access) {}

    public function index(Request $request): View
    {
        /** @var User $kepsek */
        $kepsek = Auth::user();
        $tenantId = $this->access->currentTenantId($kepsek);
        $jenisFilter = (string) $request->query('jenis', '');
        $guruFilter = (int) $request->query('guru_id', 0);

        $query = PerangkatPembelajaran::query()
            ->with(['guru:id,nama_lengkap', 'rombel:id,nama_rombel'])
            ->orderByDesc('updated_at');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        if ($jenisFilter !== '' && array_key_exists($jenisFilter, PerangkatPembelajaran::JENIS_LABELS)) {
            $query->where('jenis', $jenisFilter);
        }
        if ($guruFilter > 0) {
            $query->where('guru_id', $guruFilter);
        }

        $perangkatList = $query->paginate(20)->withQueryString();

        $guruList = User::query()
            ->where('peran', 'guru')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);

        $ringkasanJenis = PerangkatPembelajaran::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('jenis, COUNT(*) as total')
            ->groupBy('jenis')
            ->pluck('total', 'jenis')
            ->all();

        $jurnalTerbaru = JurnalMengajar::query()
            ->with('guru:id,nama_lengkap')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($guruFilter > 0, fn ($q) => $q->where('guru_id', $guruFilter))
            ->orderByDesc('tanggal')
            ->limit(15)
            ->get();

        return view('kepsek.perangkat.index', [
            'perangkatList' => $perangkatList,
            'guruList' => $guruList,
            'jenisLabels' => PerangkatPembelajaran::JENIS_LABELS,
            'jenisRingkas' => PerangkatPembelajaran::JENIS_RINGKAS,
            'ringkasanJenis' => $ringkasanJenis,
            'jenisFilter' => $jenisFilter,
            'guruFilter' => $guruFilter,
            'jurnalTerbaru' => $jurnalTerbaru,
        ]);
    }

    public function cetakPdf(PerangkatPembelajaran $perangkat): Response
    {
        /** @var User $kepsek */
        $kepsek = Auth::user();
        $this->access->authorizeViewPerangkat($kepsek, $perangkat);

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
}
