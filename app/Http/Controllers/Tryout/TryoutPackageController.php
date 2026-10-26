<?php

namespace App\Http\Controllers\Tryout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tryout\StoreTryoutPackageRequest;
use App\Http\Requests\Tryout\UpdateTryoutPackageRequest;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Subject;
use App\Models\MataPelajaran;
use Illuminate\Support\Str;

class TryoutPackageController extends Controller
{
    public function index()
    {
        $packages = Package::with(['category', 'subjects'])
                          ->whereHas('category')
                          ->latest()
                          ->paginate(20);
        
        $categories = Category::where('aktif', true)->get();
        
        return view('tryout.packages.index', compact('packages', 'categories'));
    }

    public function create()
    {
        $categories = Category::where('aktif', true)->get();
        $mataPelajarans = MataPelajaran::where('aktif', true)->orderBy('kelompok')->orderBy('urutan')->get();
        
        return view('tryout.packages.create', compact('categories', 'mataPelajarans'));
    }

    public function store(StoreTryoutPackageRequest $request)
    {
        $validated = $request->validated();

        $package = Package::create([
            'uuid' => Str::uuid(),
            'category_id' => $validated['category_id'],
            'kode_paket' => $validated['kode_paket'],
            'nama_paket' => $validated['nama_paket'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'jenjang' => $validated['jenjang'],
            'tahun_paket' => $validated['tahun_paket'],
            'durasi_total_menit' => $validated['durasi_total_menit'],
            'passing_grade' => $validated['passing_grade'],
            'standar_nilai' => $validated['standar_nilai'] ?? null,
            'jumlah_mapel' => count($validated['subjects']),
            'aktif' => true,
        ]);

        // Create subjects
        foreach ($validated['subjects'] as $index => $subjectData) {
            Subject::create([
                'uuid' => Str::uuid(),
                'package_id' => $package->id,
                'mata_pelajaran_id' => $subjectData['mata_pelajaran_id'],
                'urutan' => $index + 1,
                'bobot' => $subjectData['bobot'],
                'jumlah_soal' => $subjectData['jumlah_soal'],
                'durasi_menit' => $subjectData['durasi_menit'],
                'passing_grade_mapel' => $subjectData['passing_grade_mapel'],
            ]);
        }

        return redirect()->route('tryout.packages.show', $package)
            ->with('success', 'Paket tryout berhasil dibuat');
    }

    public function show(Package $package)
    {
        $package->load(['category', 'subjects.mataPelajaran', 'subjects.questions']);
        
        return view('tryout.packages.show', compact('package'));
    }

    public function edit(Package $package)
    {
        $categories = Category::where('aktif', true)->get();
        $mataPelajarans = MataPelajaran::where('aktif', true)->orderBy('kelompok')->orderBy('urutan')->get();
        $package->load(['subjects']);
        
        return view('tryout.packages.edit', compact('package', 'categories', 'mataPelajarans'));
    }

    public function update(UpdateTryoutPackageRequest $request, Package $package)
    {
        $validated = $request->validated();

        $package->update($validated);

        return redirect()->route('tryout.packages.show', $package)
            ->with('success', 'Paket tryout berhasil diperbarui');
    }

    public function destroy(Package $package)
    {
        $package->delete();

        return redirect()->route('tryout.packages.index')
            ->with('success', 'Paket tryout berhasil dihapus');
    }
}
