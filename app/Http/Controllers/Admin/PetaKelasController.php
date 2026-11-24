<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGuru;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PetaKelasController extends Controller
{
    private function tenantScope(): \Closure
    {
        $tenantId = TenantContext::id() ?? auth()->user()?->tenant_id;

        return fn ($q) => $tenantId !== null ? $q->where('tenant_id', $tenantId) : $q->whereNull('tenant_id');
    }
    public function daftar(Request $request)
    {
        $jenis = $request->get('jenis');
        $jenisList = ['Reguler', 'Pilihan', 'Ekskul', 'Lainnya'];

        $query = Rombel::with(['guru', 'semester'])->withCount('siswa')->orderBy('nama_rombel');
        if ($jenis) {
            $query->where('jenis_rombel', $jenis);
        }
        $rombel = $query->get();

        return view('admin.peta-kelas.daftar', compact('rombel', 'jenis', 'jenisList'));
    }

    public function detail(Rombel $rombel)
    {
        $rombel->load(['guru', 'semester', 'siswa' => fn ($q) => $q->orderBy('nama_peserta_didik')]);

        return view('admin.peta-kelas.detail', compact('rombel'));
    }

    public function index()
    {
        $guru = User::forSchool()->where('peran', 'guru')->orderBy('nama_lengkap')->get();
        $siswa = Siswa::with('guru')->orderBy('nama_peserta_didik')->get();
        $pengaturanGuru = PengaturanGuru::forSchool()->get();

        $siswaPerGuru = $siswa->groupBy('guru_id');
        $tidakDitugaskan = $siswa->filter(fn ($s) => empty($s->guru_id));

        $guruStats = $guru->map(function ($g) use ($siswaPerGuru, $pengaturanGuru) {
            $count = $siswaPerGuru->get($g->id, collect())->count();
            $kelasList = $pengaturanGuru->where('guru_id', $g->id)->pluck('kelas')->unique()->values();

            return [
                'guru' => $g,
                'jumlah_siswa' => $count,
                'kelas_list' => $kelasList,
            ];
        });

        return view('admin.peta-kelas.index', compact('guru', 'siswa', 'pengaturanGuru', 'guruStats', 'siswaPerGuru', 'tidakDitugaskan'));
    }

    public function store(Request $request)
    {
        $tenantScope = $this->tenantScope();
        $validated = $request->validate([
            'guru_id' => ['required', Rule::exists('users', 'id')->where($tenantScope)],
            'kelas' => 'required|string|max:255',
            'mata_pelajaran' => 'nullable|string|max:255',
        ]);

        $guru = User::forSchool()->where('peran', 'guru')->findOrFail($validated['guru_id']);

        $pengaturan = PengaturanGuru::updateOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'kelas' => $validated['kelas'],
                'mata_pelajaran' => $validated['mata_pelajaran'] ?? null,
                'kkm' => getKKM(),
                'semester' => 'Ganjil',
                'tahun_pelajaran' => date('Y').'/'.(date('Y') + 1),
                'pembaruan' => now(),
            ]
        );

        $message = $pengaturan->wasRecentlyCreated
            ? 'Kelas berhasil ditambahkan ke guru.'
            : 'Pengaturan guru berhasil diperbarui.';

        return redirect()->route('admin.peta-kelas.index')
            ->with('success', $message);
    }

    public function destroy($id)
    {
        $pengaturan = PengaturanGuru::forSchool()->findOrFail($id);

        // PengaturanGuru tidak punya relasi `siswa`; hitung langsung dari guru_id.
        if (Siswa::where('guru_id', $pengaturan->guru_id)->exists()) {
            return redirect()->route('admin.peta-kelas.index')
                ->with('error', 'Tidak bisa menghapus pengaturan kelas yang masih memiliki siswa terkait.');
        }

        $pengaturan->delete();

        return redirect()->route('admin.peta-kelas.index')
            ->with('success', 'Pengaturan kelas berhasil dihapus.');
    }

    public function pindahSiswa(Request $request): JsonResponse
    {
        $tenantScope = $this->tenantScope();
        $validated = $request->validate([
            'siswa_id' => ['required', Rule::exists('siswa', 'id')->where($tenantScope)],
            'guru_id' => ['required', Rule::exists('users', 'id')->where($tenantScope)],
            'kelas' => 'required|string',
        ]);

        $siswa = Siswa::findOrFail($validated['siswa_id']);
        $guru = User::forSchool()->where('peran', 'guru')->findOrFail($validated['guru_id']);

        $siswa->update([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'kelas' => $validated['kelas'],
        ]);

        PengaturanGuru::firstOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'kelas' => $validated['kelas'],
                'kkm' => getKKM(),
                'semester' => 'Ganjil',
                'tahun_pelajaran' => date('Y').'/'.(date('Y') + 1),
                'pembaruan' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "{$siswa->nama_peserta_didik} dipindahkan ke {$guru->nama_lengkap} ({$validated['kelas']})",
        ]);
    }

    public function keluarkanSiswa(Request $request): JsonResponse
    {
        $tenantScope = $this->tenantScope();
        $validated = $request->validate([
            'siswa_id' => ['required', Rule::exists('siswa', 'id')->where($tenantScope)],
        ]);

        $siswa = Siswa::findOrFail($validated['siswa_id']);
        $nama = $siswa->nama_peserta_didik;

        $siswa->update([
            'guru_id' => null,
            'nama_guru' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$nama} dikeluarkan dari kelas",
        ]);
    }

    public function getKelasOptions(Request $request): JsonResponse
    {
        $tenantScope = $this->tenantScope();
        $validated = $request->validate([
            'guru_id' => ['required', Rule::exists('users', 'id')->where($tenantScope)],
        ]);

        $kelasList = PengaturanGuru::forSchool()
            ->where('guru_id', $validated['guru_id'])
            ->pluck('kelas')
            ->unique()
            ->values();

        return response()->json(['kelas' => $kelasList]);
    }

    public function toggleKunciRapor(Rombel $rombel): RedirectResponse
    {
        $rombel->update(['kunci_input_rapor' => ! $rombel->kunci_input_rapor]);
        $status = $rombel->kunci_input_rapor ? 'dikunci' : 'dibuka kembali';

        return back()->with('success', "Input rapor untuk rombel {$rombel->nama_rombel} berhasil {$status}.");
    }

    public function toggleKunciSemua(): RedirectResponse
    {
        $terkunciSekarang = Rombel::apakahTerkunciGlobal();
        $targetKunci = ! $terkunciSekarang;
        $tenantId = TenantContext::id() ?? auth()->user()?->tenant_id;
        $defaultNama = ($tenantId !== null ? Tenant::whereKey($tenantId)->value('nama_sekolah') : null)
            ?: config('app.name', 'SIMANTAP');

        $sekolah = SekolahSettings::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'guru_id' => auth()->id(),
                'nama_sekolah' => $defaultNama,
            ]
        );
        $pengaturan = is_array($sekolah->pengaturan) ? $sekolah->pengaturan : [];
        $pengaturan['kunci_input_rapor_global'] = $targetKunci;
        $sekolah->update(['pengaturan' => $pengaturan]);

        Rombel::query()->update(['kunci_input_rapor' => $targetKunci]);
        Cache::forget($tenantId !== null ? 'admin-dashboard:tenant:v3:'.$tenantId : 'admin-dashboard:single:v3');

        $status = $targetKunci ? 'dikunci (ditutup)' : 'dibuka kembali';

        return back()->with('success', "Akses input nilai rapor seluruh kelas berhasil {$status}.");
    }
}
