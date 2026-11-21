<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\HariLibur;
use App\Models\PembelajaranMendalam\KalenderPendidikan;
use App\Models\User;
use App\Services\PembelajaranMendalam\KalenderPendidikanService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class KalenderController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly KalenderPendidikanService $kalender,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = $this->ctx->getContext($request)['tahun_ajaran'];

        $this->kalender->ensureDefaults($tenantId, $tahunAjaran, $guru->id);

        $sem1 = $this->kalender->ringkasanSemester($tenantId, $tahunAjaran, 1, 1);
        $sem2 = $this->kalender->ringkasanSemester($tenantId, $tahunAjaran, 2, 1);

        $hariLibur = HariLibur::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('tanggal_mulai')
            ->get();

        $canEdit = $guru->peran === 'admin';

        return view('guru.pm.kalender.index', [
            'tahunAjaran' => $tahunAjaran,
            'sem1' => $sem1,
            'sem2' => $sem2,
            'hariLibur' => $hariLibur,
            'jenisLiburLabels' => HariLibur::JENIS_LABELS,
            'canEdit' => $canEdit,
            'routePrefix' => $canEdit ? 'admin.pm' : 'guru.pm',
            'layout' => $canEdit ? 'layouts.admin-with-tabs' : 'layouts.app',
        ]);
    }

    public function simpanKalender(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        if ($guru->peran !== 'admin') {
            abort(403, 'Hanya Admin/Operator sekolah yang dapat mengubah Kalender Pendidikan.');
        }
        $tenantId = $this->ctx->tenantId($guru);
        $items = $request->input('kalender', []);

        if (! is_array($items)) {
            return back()->with('error', 'Data kalender pendidikan tidak valid.');
        }

        foreach ($items as $id => $row) {
            if (! is_array($row)) {
                continue;
            }
            $kal = KalenderPendidikan::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('id', (int) $id)
                ->first();

            if (! $kal) {
                continue;
            }

            $jmlMinggu = max(1, min(6, (int) ($row['jumlah_minggu'] ?? $kal->jumlah_minggu)));
            $mingguLiburRaw = trim((string) ($row['minggu_libur'] ?? ''));
            $mingguLibur = [];
            if ($mingguLiburRaw !== '') {
                foreach (explode(',', $mingguLiburRaw) as $part) {
                    $num = (int) trim($part);
                    if ($num >= 1 && $num <= $jmlMinggu) {
                        $mingguLibur[] = $num;
                    }
                }
                $mingguLibur = array_values(array_unique($mingguLibur));
                sort($mingguLibur);
            }

            $tidakEfektif = isset($row['minggu_tidak_efektif']) && $row['minggu_tidak_efektif'] !== ''
                ? max(0, min($jmlMinggu, (int) $row['minggu_tidak_efektif']))
                : count($mingguLibur);

            $kal->update([
                'jumlah_minggu' => $jmlMinggu,
                'minggu_tidak_efektif' => $tidakEfektif,
                'hari_efektif' => max(0, min(31, (int) ($row['hari_efektif'] ?? $kal->hari_efektif))),
                'minggu_libur' => $mingguLibur,
                'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
                'updated_by' => $guru->id,
            ]);
        }

        return back()->with('success', 'Kalender Pendidikan berhasil diperbarui.');
    }

    public function isiDefault(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        if ($guru->peran !== 'admin') {
            abort(403, 'Hanya Admin/Operator sekolah yang dapat mengubah Kalender Pendidikan.');
        }
        $tenantId = $this->ctx->tenantId($guru);
        $tahunAjaran = trim((string) $request->input('tahun_ajaran', $this->ctx->tahunAjaranAktif()));

        $this->kalender->isiDefault($tenantId, $tahunAjaran, $guru->id);

        return redirect()
            ->route('admin.pm.kalender.index', ['tahun_ajaran' => $tahunAjaran])
            ->with('success', 'Kalender Pendidikan standar berhasil dimuat ulang.');
    }

    public function storeLibur(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        if ($guru->peran !== 'admin') {
            abort(403, 'Hanya Admin/Operator sekolah yang dapat menambah Hari Libur.');
        }
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'nama' => ['required', 'string', 'max:160'],
            'jenis' => ['required', 'in:libur_nasional,libur_semester,kegiatan_sekolah,cuti_bersama'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        HariLibur::create([
            'tenant_id' => $tenantId,
            'tahun_ajaran' => $data['tahun_ajaran'],
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => ! empty($data['tanggal_selesai']) ? $data['tanggal_selesai'] : $data['tanggal_mulai'],
            'nama' => $data['nama'],
            'jenis' => $data['jenis'],
            'keterangan' => ! empty($data['keterangan']) ? $data['keterangan'] : null,
            'created_by' => $guru->id,
        ]);

        return back()->with('success', 'Hari libur / kegiatan sekolah berhasil ditambahkan.');
    }

    public function destroyLibur(HariLibur $libur): RedirectResponse
    {
        if (Auth::user()?->peran !== 'admin') {
            abort(403, 'Hanya Admin/Operator sekolah yang dapat menghapus Hari Libur.');
        }
        Gate::authorize('delete', $libur);
        $libur->delete();

        return back()->with('success', 'Hari libur berhasil dihapus.');
    }
}
