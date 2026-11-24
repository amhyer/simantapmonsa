<?php

namespace App\Http\Controllers\Tka;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Tka\AssessmentItem;
use App\Models\Tka\Dimension;
use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        $tenantId = TenantContext::id() ?? $request->user()?->tenant_id;
        $tenantScope = fn ($q) => $tenantId !== null
            ? $q->where('tenant_id', $tenantId)
            : $q->whereNull('tenant_id');

        $kodeRule = Rule::unique('tka_dimensions_enhanced', 'kode_dimensi')->where($tenantScope);
        $semesterRule = Rule::exists('semesters', 'id')->where($tenantScope);

        $validated = $request->validate([
            'semester_id' => ['required', 'integer', $semesterRule],
            'kode_dimensi' => ['required', 'string', 'max:50', $kodeRule],
            'nama_dimensi' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'required|in:religius,nasionalis,mandiri,gotong_royong,integritas,kreatif,berdaya_saing',
            'bobot' => 'required|numeric|min:0|max:10',
            'rubrik_penilaian' => 'required|array',
            'threshold_siap' => 'required|numeric|min:1|max:4',
        ]);

        $semester = Semester::findOrFail($validated['semester_id']);

        $dimension = Dimension::create([
            'uuid' => Str::uuid(),
            'semester_id' => $semester->id,
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
        $tenantId = TenantContext::id() ?? $request->user()?->tenant_id;
        $tenantScope = fn ($q) => $tenantId !== null
            ? $q->where('tenant_id', $tenantId)
            : $q->whereNull('tenant_id');

        $kodeRule = Rule::unique('tka_dimensions_enhanced', 'kode_dimensi')
            ->ignore($dimension->id)
            ->where($tenantScope);
        $semesterRule = Rule::exists('semesters', 'id')->where($tenantScope);

        $validated = $request->validate([
            'semester_id' => ['required', 'integer', $semesterRule],
            'kode_dimensi' => ['required', 'string', 'max:50', $kodeRule],
            'nama_dimensi' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'required|in:religius,nasionalis,mandiri,gotong_royong,integritas,kreatif,berdaya_saing',
            'bobot' => 'required|numeric|min:0|max:10',
            'rubrik_penilaian' => 'required|array',
            'threshold_siap' => 'required|numeric|min:1|max:4',
            'aktif' => 'boolean',
        ]);

        $semester = Semester::findOrFail($validated['semester_id']);
        $validated['semester_id'] = $semester->id;

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
        // Nested route: reject an item ID that belongs to another dimension.
        $item = $dimension->assessmentItems()->findOrFail($item->id);
        $urutan = $item->urutan;
        $item->delete();

        // Reorder remaining items
        $dimension->assessmentItems()
            ->where('urutan', '>', $urutan)
            ->decrement('urutan');

        return back()->with('success', 'Item penilaian berhasil dihapus');
    }

    public function getItems(Dimension $dimension)
    {
        $items = $dimension->assessmentItems()->orderBy('urutan')->get();

        return response()->json($items);
    }
}
