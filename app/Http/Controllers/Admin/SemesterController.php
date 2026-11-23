<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SemesterController extends Controller
{
    public function index()
    {
        $semesters = Semester::orderByDesc('semester_id')->get();
        $activeId = setting('active_semester_id');

        return view('admin.semester.index', compact('semesters', 'activeId'));
    }

    public function store(Request $request)
    {
        $semesterRule = Rule::unique('semesters', 'semester_id')->where(fn ($q) => TenantContext::check()
            ? $q->where('tenant_id', TenantContext::id())
            : $q->whereNull('tenant_id'));

        $request->validate([
            'semester_id' => ['required', 'string', $semesterRule],
            'tahun_ajaran' => 'required|string|max:20',
            'nama_semester' => 'required|in:ganjil,genap',
        ]);

        Semester::create($request->only('semester_id', 'tahun_ajaran', 'nama_semester'));

        return redirect()->route('admin.semester.index')->with('success', 'Semester berhasil ditambahkan.');
    }

    public function destroy(Semester $semester)
    {
        $inUse = $semester->siswa()->withoutGlobalScopes()->exists()
            || $semester->ptk()->withoutGlobalScopes()->exists()
            || $semester->rombel()->withoutGlobalScopes()->exists();

        if ($inUse) {
            return redirect()->route('admin.semester.index')
                ->with('error', 'Tidak bisa menghapus semester yang memiliki data siswa, ptk, atau rombel terkait.');
        }
        $semester->delete();

        return redirect()->route('admin.semester.index')->with('success', 'Semester berhasil dihapus.');
    }

    public function activate(Semester $semester)
    {
        setting(['active_semester_id' => $semester->id]);

        return redirect()->route('admin.semester.index')->with('success', "Semester {$semester->label} diaktifkan.");
    }
}
