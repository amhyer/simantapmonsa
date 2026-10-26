<?php

namespace App\Http\Controllers\Admin\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cbt\CbtBankRequest;
use App\Models\CbtQuestionBank;
use App\Models\User;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manajemen bank soal CBT (tampilan admin, lintas guru).
 *
 * Disesuaikan dengan skema baru 2026_10_18 (cbt_question_banks: guru_id,
 * title, subject). Tidak ada kolom uuid/aktif/semester pada skema ini.
 */
class CbtBankController extends Controller
{
    public function index(Request $request)
    {
        $query = CbtQuestionBank::with('guru')->withCount(['questions', 'sessions']);

        if ($request->filled('guru_id')) {
            $query->where('guru_id', $request->integer('guru_id'));
        }
        if ($request->filled('q')) {
            $q = trim($request->string('q'));
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('subject', 'like', "%{$q}%"));
        }

        $banks = $query->orderByDesc('id')->paginate(15)->withQueryString();
        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);

        return view('admin.cbt.banks.index', compact('banks', 'gurus'));
    }

    public function create()
    {
        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);

        return view('admin.cbt.banks.create', compact('gurus'));
    }

    public function store(CbtBankRequest $request)
    {
        $validated = $request->validated();

        $bank = CbtQuestionBank::create($validated);
        $this->sinkronEngine($bank);

        return redirect()->route('admin.cbt.banks.index')->with('success', 'Bank soal dibuat.');
    }

    public function show(CbtQuestionBank $bank)
    {
        $bank->load(['guru', 'sessions' => fn ($q) => $q->latest('id')]);
        // correct_answer disembunyikan model; tampilkan untuk tinjauan admin.
        $questions = $bank->questions()->orderBy('id')->get()->each->makeVisible('correct_answer');

        return view('admin.cbt.banks.show', compact('bank', 'questions'));
    }

    public function edit(CbtQuestionBank $bank)
    {
        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);

        return view('admin.cbt.banks.edit', compact('bank', 'gurus'));
    }

    public function update(CbtBankRequest $request, CbtQuestionBank $bank)
    {
        $validated = $request->validated();

        $bank->update($validated);
        $this->sinkronEngine($bank->fresh());

        return redirect()->route('admin.cbt.banks.index')->with('success', 'Bank soal diperbarui.');
    }

    public function destroy(CbtQuestionBank $bank)
    {
        DB::transaction(function () use ($bank) {
            $locked = CbtQuestionBank::whereKey($bank->id)->lockForUpdate()->firstOrFail();
            // FK bank->sesi restrictOnDelete; blokir agar tidak 500.
            abort_if($locked->sessions()->exists(), 409, 'Bank telah dipakai sesi; tidak boleh dihapus.');
            $locked->delete();
        });

        if (config('simantap.exam_engine_cbt', true)) {
            app(LegacyExamMapper::class)->lupakanBankCbt($bank->id);
        }

        return redirect()->route('admin.cbt.banks.index')->with('success', 'Bank soal dihapus.');
    }

    private function sinkronEngine(CbtQuestionBank $bank): void
    {
        if (config('simantap.exam_engine_cbt', true)) {
            app(LegacyExamMapper::class)->bankCbt($bank);
        }
    }

    public function statistics()
    {
        return response()->json([
            'total_banks' => CbtQuestionBank::count(),
            'total_questions' => \App\Models\CbtQuestion::count(),
            'banks_with_sessions' => CbtQuestionBank::whereHas('sessions')->count(),
        ]);
    }
}
