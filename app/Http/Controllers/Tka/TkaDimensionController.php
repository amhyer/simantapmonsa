<?php

namespace App\Http\Controllers\Tka;

use App\Http\Controllers\Controller;
use App\Models\Tka\Dimension;
use App\Models\Tka\AssessmentItem;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TkaDimensionController extends Controller
{
    public function index()
    {
        $dimensions = Dimension::with(['semester', 'assessmentItems'])
                              ->withCount('assessmentItems')
                              ->orderBy('kategori')
                              ->orderBy('nama_dimensi')
                              ->paginate(20);
        
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        
        return view('tka.dimensions.index', compact('dimensions', 'semesters'));
    }

    public function create()
    {
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        
        return view('tka.dimensions.create', compact('semesters'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'kode_dimensi' => 'required|string|max:50|unique:tka_dimensions_enhanced,kode_dimensi',
            'nama_dimensi' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'required|in:religius,nasionalis,mandiri,gotong_royong,integritas,kreatif,berdaya_saing',
            'bobot' => 'required|numeric|min:0|max:10',
            'rubrik_penilaian' => 'required|array',
            'threshold_siap' => 'required|numeric|min:1|max:4',
        ]);

        $dimension = Dimension::create([
            'uuid' => Str::uuid(),
            'semester_id' => $validated['semester_id'],
            'kode_dimensi' => $validated['kode_dimensi'],
            'nama_dimensi' => $validated['nama_dimensi'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'kategori' => $validated['kategori'],
            'bobot' => $validated['bobot'],
            'rubrik_penilaian' => $validated['rubrik_penilaian'],
            'threshold_siap' => $validated['threshold_siap'],
            'aktif' => true,
        ]);

        return redirect()->route('tka.dimensions.show', $dimension)
            ->with('success', 'Dimensi TKA berhasil dibuat');
    }

    public function show(Dimension $dimension)
    {
        $dimension->load(['semester', 'assessmentItems']);
        
        return view('tka.dimensions.show', compact('dimension'));
    }

    public function edit(Dimension $dimension)
    {
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')->get();
        $dimension->load(['assessmentItems']);
        
        return view('tka.dimensions.edit', compact('dimension', 'semesters'));
    }

    public function update(Request $request, Dimension $dimension)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'kode_dimensi' => 'required|string|max:50|unique:tka_dimensions_enhanced,kode_dimensi,' . $dimension->id,
            'nama_dimensi' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'required|in:religius,nasionalis,mandiri,gotong_royong,integritas,kreatif,berdaya_saing',
            'bobot' => 'required|numeric|min:0|max:10',
            'rubrik_penilaian' => 'required|array',
            'threshold_siap' => 'required|numeric|min:1|max:4',
            'aktif' => 'boolean',
        ]);

        $dimension->update($validated);

        return redirect()->route('tka.dimensions.show', $dimension)
            ->with('success', 'Dimensi TKA berhasil diperbarui');
    }

    public function destroy(Dimension $dimension)
    {
        $dimension->delete();

        return redirect()->route('tka.dimensions.index')
            ->with('success', 'Dimensi TKA berhasil dihapus');
    }

    public function addItem(Request $request, Dimension $dimension)
    {
        $validated = $request->validate([
            'indikator' => 'required|string|max:500',
            'pertanyaan_penilaian' => 'required|string',
            'tipe_jawaban' => 'required|in:skala_1_4,skala_1_5,ya_tidak,checklist',
            'bobot_item' => 'required|numeric|min:0|max:10',
        ]);

        $item = AssessmentItem::create([
            'uuid' => Str::uuid(),
            'dimension_id' => $dimension->id,
            'urutan' => $dimension->assessmentItems()->count() + 1,
            'indikator' => $validated['indikator'],
            'pertanyaan_penilaian' => $validated['pertanyaan_penilaian'],
            'tipe_jawaban' => $validated['tipe_jawaban'],
            'bobot_item' => $validated['bobot_item'],
        ]);

        return back()->with('success', 'Item penilaian berhasil ditambahkan');
    }

    public function destroyItem(Dimension $dimension, AssessmentItem $item)
    {
        $item->delete();

        // Reorder remaining items
        $dimension->assessmentItems()
                 ->where('urutan', '>', $item->urutan)
                 ->decrement('urutan');

        return back()->with('success', 'Item penilaian berhasil dihapus');
    }

    public function getItems(Dimension $dimension)
    {
        $items = $dimension->assessmentItems()->orderBy('urutan')->get();
        
        return response()->json($items);
    }
}
