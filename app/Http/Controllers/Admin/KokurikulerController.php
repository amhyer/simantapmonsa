<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KegiatanKokurikuler;
use App\Models\KelompokKokurikuler;
use App\Models\Siswa;
use App\Models\TemaKokurikuler;
use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KokurikulerController extends Controller
{
    public function tema()
    {
        $tema = TemaKokurikuler::withCount('kegiatan')->orderBy('nama')->get();

        return view('admin.kokurikuler.tema', compact('tema'));
    }

    public function storeTema(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string|max:500',
        ]);

        TemaKokurikuler::create(array_merge($validated, ['aktif' => true]));

        return redirect()->route('admin.kokurikuler.tema')
            ->with('success', 'Tema berhasil ditambahkan.');
    }

    public function destroyTema(TemaKokurikuler $tema)
    {
        $tema->delete();

        return redirect()->route('admin.kokurikuler.tema')
            ->with('success', 'Tema beserta kegiatan di dalamnya berhasil dihapus.');
    }

    public function kegiatan()
    {
        $kegiatan = KegiatanKokurikuler::with(['tema', 'kelompok'])->orderBy('nama')->get();
        $tema = TemaKokurikuler::where('aktif', true)->orderBy('nama')->get();

        return view('admin.kokurikuler.kegiatan', compact('kegiatan', 'tema'));
    }

    public function storeKegiatan(Request $request)
    {
        $tenantId = TenantContext::id() ?? $request->user()?->tenant_id;
        $validated = $request->validate([
            'tema_id' => [
                'required',
                Rule::exists('tema_kokurikuler', 'id')->where(function ($query) use ($tenantId): void {
                    $tenantId !== null
                        ? $query->where('tenant_id', $tenantId)
                        : $query->whereNull('tenant_id');
                }),
            ],
            'nama' => 'required|string|max:255',
            'kelas' => 'nullable|string|max:50',
            'koordinator' => 'nullable|string|max:255',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);

        TemaKokurikuler::findOrFail($validated['tema_id']);
        KegiatanKokurikuler::create(array_merge($validated, ['aktif' => true]));

        return redirect()->route('admin.kokurikuler.kegiatan')
            ->with('success', 'Kegiatan berhasil ditambahkan.');
    }

    public function destroyKegiatan(KegiatanKokurikuler $kegiatan)
    {
        $kegiatan->delete();

        return redirect()->route('admin.kokurikuler.kegiatan')
            ->with('success', 'Kegiatan beserta kelompok di dalamnya berhasil dihapus.');
    }

    public function kelompok()
    {
        $kelompok = KelompokKokurikuler::with(['kegiatan.tema'])->withCount('anggota')->orderBy('nama')->get();
        $kegiatan = KegiatanKokurikuler::where('aktif', true)->orderBy('nama')->get();

        return view('admin.kokurikuler.kelompok', compact('kelompok', 'kegiatan'));
    }

    public function showKelompok(KelompokKokurikuler $kelompok)
    {
        $kelompok->load(['kegiatan.tema', 'anggota']);
        $siswa = Siswa::where('aktif', true)->orderBy('nama_peserta_didik')->get(['id', 'nama_peserta_didik', 'nis', 'kelas']);
        $anggotaIds = $kelompok->anggota->pluck('id')->toArray();

        return view('admin.kokurikuler.kelompok-show', compact('kelompok', 'siswa', 'anggotaIds'));
    }

    public function storeKelompok(Request $request)
    {
        $tenantId = TenantContext::id() ?? $request->user()?->tenant_id;
        $validated = $request->validate([
            'kegiatan_id' => [
                'required',
                Rule::exists('kegiatan_kokurikuler', 'id')->where(function ($query) use ($tenantId): void {
                    $tenantId !== null
                        ? $query->where('tenant_id', $tenantId)
                        : $query->whereNull('tenant_id');
                }),
            ],
            'nama' => 'required|string|max:255',
            'keterangan' => 'nullable|string|max:500',
        ]);

        KegiatanKokurikuler::findOrFail($validated['kegiatan_id']);
        KelompokKokurikuler::create($validated);

        return redirect()->route('admin.kokurikuler.kelompok')
            ->with('success', 'Kelompok berhasil ditambahkan.');
    }

    public function destroyKelompok(KelompokKokurikuler $kelompok)
    {
        $kelompok->delete();

        return redirect()->route('admin.kokurikuler.kelompok')
            ->with('success', 'Kelompok berhasil dihapus.');
    }

    public function updateAnggota(Request $request, KelompokKokurikuler $kelompok)
    {
        $validated = $request->validate([
            'anggota' => 'nullable|array',
            'anggota.*' => 'integer',
        ]);

        // Batasi sinkronisasi hanya pada siswa milik sekolah aktif agar tidak
        // menautkan atau mencabut anggota sekolah lain.
        $allowedSiswaIds = Siswa::whereIn('id', $validated['anggota'] ?? [])->pluck('id')->all();
        $currentSchoolMemberIds = $kelompok->anggota()->pluck('siswa.id')->all();
        if (! empty($currentSchoolMemberIds)) {
            $kelompok->anggota()->detach($currentSchoolMemberIds);
        }
        if (! empty($allowedSiswaIds)) {
            $kelompok->anggota()->syncWithoutDetaching($allowedSiswaIds);
        }

        return redirect()->route('admin.kokurikuler.kelompok.show', $kelompok)
            ->with('success', 'Anggota kelompok berhasil diperbarui.');
    }
}
