<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGuru;
use App\Models\User;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use App\Services\PembelajaranMendalam\TandaTanganPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TandaTanganController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly TandaTanganPdfService $ttdService,
    ) {}

    public function index(): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $sekolah = $this->ctx->sekolah($tenantId);

        $pengGuruRow = PengaturanGuru::query()->where('guru_id', $guru->id)->first();
        $pengGuru = is_array($pengGuruRow?->pengaturan) ? $pengGuruRow->pengaturan : [];

        return view('guru.pm.ttd.index', [
            'guru' => $guru,
            'sekolah' => $sekolah,
            'pengGuru' => $pengGuru,
            'preview' => $this->ttdService->resolveForPdf($sekolah, $guru),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();

        $validated = $request->validate([
            'ttd_guru' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'nip_guru' => ['nullable', 'string', 'max:40'],
            'jabatan_guru' => ['nullable', 'string', 'max:100'],
        ], [
            'ttd_guru.image' => 'Tanda tangan harus berupa file gambar.',
            'ttd_guru.mimes' => 'Format tanda tangan harus PNG, JPG, atau JPEG (disarankan PNG transparan).',
            'ttd_guru.max' => 'Ukuran file tanda tangan maksimal 2MB.',
        ]);

        $row = PengaturanGuru::firstOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'kkm' => 75,
                'pembaruan' => now(),
            ]
        );

        $pengaturan = is_array($row->pengaturan) ? $row->pengaturan : [];

        if ($request->hasFile('ttd_guru')) {
            if (! empty($pengaturan['ttd_guru'])) {
                foreach (['local', 'public'] as $disk) {
                    if (Storage::disk($disk)->exists($pengaturan['ttd_guru'])) {
                        Storage::disk($disk)->delete($pengaturan['ttd_guru']);
                    }
                }
            }
            $pengaturan['ttd_guru'] = $request->file('ttd_guru')->store('private/identitas/ttd-guru', 'local');
        }

        $pengaturan['nip_guru'] = trim((string) ($validated['nip_guru'] ?? ''));
        $pengaturan['jabatan_guru'] = trim((string) ($validated['jabatan_guru'] ?? ''));

        $row->update([
            'nama_guru' => $guru->nama_lengkap,
            'pengaturan' => $pengaturan,
            'pembaruan' => now(),
        ]);

        return redirect()
            ->route('guru.pm.ttd.index')
            ->with('success', 'Tanda tangan digital & identitas penandatangan berhasil disimpan.');
    }

    public function hapus(): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $row = PengaturanGuru::query()->where('guru_id', $guru->id)->first();

        if ($row && is_array($row->pengaturan) && ! empty($row->pengaturan['ttd_guru'])) {
            $pengaturan = $row->pengaturan;
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($pengaturan['ttd_guru'])) {
                    Storage::disk($disk)->delete($pengaturan['ttd_guru']);
                }
            }
            unset($pengaturan['ttd_guru']);
            $row->update(['pengaturan' => $pengaturan, 'pembaruan' => now()]);
        }

        return redirect()
            ->route('guru.pm.ttd.index')
            ->with('success', 'Gambar tanda tangan digital telah dihapus.');
    }
}
