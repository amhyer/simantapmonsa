<?php

namespace App\Http\Controllers\Tryout;

use App\Http\Controllers\Controller;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Subject;
use App\Models\MataPelajaran;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:tryout_categories,id',
            'kode_paket' => 'required|string|max:50|unique:tryout_packages,kode_paket',
            'nama_paket' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'jenjang' => 'required|in:SD,SMP,SMA,SMK,UMUM',
            'tahun_paket' => 'required|integer|min:2020|max:2030',
            'durasi_total_menit' => 'required|integer|min:1',
            'passing_grade' => 'required|numeric|min:0|max:100',
            'standar_nilai' => 'nullable|array',
            'subjects' => 'required|array|min:1',
            'subjects.*.mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'subjects.*.bobot' => 'required|numeric|min:0|max:10',
            'subjects.*.jumlah_soal' => 'required|integer|min:1',
            'subjects.*.durasi_menit' => 'required|integer|min:1',
            'subjects.*.passing_grade_mapel' => 'required|numeric|min:0|max:100',
        ]);

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

        $this->sinkronEngine($package->fresh());

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

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:tryout_categories,id',
            'kode_paket' => 'required|string|max:50|unique:tryout_packages,kode_paket,' . $package->id,
            'nama_paket' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'jenjang' => 'required|in:SD,SMP,SMA,SMK,UMUM',
            'tahun_paket' => 'required|integer|min:2020|max:2030',
            'durasi_total_menit' => 'required|integer|min:1',
            'passing_grade' => 'required|numeric|min:0|max:100',
            'standar_nilai' => 'nullable|array',
            'aktif' => 'boolean',
        ]);

        $package->update($validated);
        $this->sinkronEngine($package->fresh());

        return redirect()->route('tryout.packages.show', $package)
            ->with('success', 'Paket tryout berhasil diperbarui');
    }

    public function destroy(Package $package)
    {
        $packageId = $package->id;
        $package->delete();

        if (config('simantap.exam_engine_tryout', true)) {
            app(LegacyExamMapper::class)->lupakanPaketTryout($packageId);
        }

        return redirect()->route('tryout.packages.index')
            ->with('success', 'Paket tryout berhasil dihapus');
    }

    private function sinkronEngine(Package $package): void
    {
        if (config('simantap.exam_engine_tryout', true)) {
            app(LegacyExamMapper::class)->paketTryout($package);
        }
    }
}
