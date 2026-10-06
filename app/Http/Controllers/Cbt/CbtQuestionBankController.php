<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\QuestionBank;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CbtQuestionBankController extends Controller
{
    public function index()
    {
        $banks = QuestionBank::with(['mataPelajaran', 'semester', 'guru'])
                             ->where('guru_id', auth()->id())
                             ->latest()
                             ->paginate(20);
        return view('cbt.question-banks.index', compact('banks'));
    }

    public function create()
    {
        $mapel = MataPelajaran::where('aktif', true)->get();
        $semester = Semester::orderBy('tahun_ajaran', 'desc')->get();
        return view('cbt.question-banks.create', compact('mapel', 'semester'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'semester_id' => 'required|exists:semesters,id',
            'kode_bank' => 'required|string|max:50|unique:cbt_question_banks',
            'nama_bank' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tingkat_kesulitan' => 'required|in:mudah,sedang,sulit',
            'kategori_soal' => 'nullable|string|max:100',
        ]);

        $bank = QuestionBank::create([
            'uuid' => Str::uuid(),
            'guru_id' => auth()->id(),
            ...$validated,
            'jumlah_soal' => 0,
            'aktif' => true,
        ]);

        return redirect()->route('cbt.question-banks.show', $bank)
            ->with('success', 'Bank soal berhasil dibuat');
    }

    public function show(QuestionBank $bank)
    {
        $bank->load(['questions.options', 'mataPelajaran', 'semester']);
        return view('cbt.question-banks.show', compact('bank'));
    }

    public function edit(QuestionBank $bank)
    {
        $mapel = MataPelajaran::where('aktif', true)->get();
        $semester = Semester::orderBy('tahun_ajaran', 'desc')->get();
        return view('cbt.question-banks.edit', compact('bank', 'mapel', 'semester'));
    }

    public function update(Request $request, QuestionBank $bank)
    {

        $validated = $request->validate([
            'nama_bank' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tingkat_kesulitan' => 'required|in:mudah,sedang,sulit',
            'kategori_soal' => 'nullable|string|max:100',
            'aktif' => 'boolean',
        ]);

        $bank->update($validated);

        return redirect()->route('cbt.question-banks.show', $bank)
            ->with('success', 'Bank soal berhasil diperbarui');
    }

    public function destroy(QuestionBank $bank)
    {
        $bank->delete();
        return redirect()->route('cbt.question-banks.index')
            ->with('success', 'Bank soal berhasil dihapus');
    }

    // API: Get questions for session
    public function apiGetQuestions(Request $request, QuestionBank $bank)
    {
        $jumlah = $request->input('jumlah', $bank->jumlah_soal);
        $acak = $request->input('acak', true);

        $query = $bank->questions()->where('aktif', true);

        if ($acak) {
            $questions = $query->inRandomOrder()->limit($jumlah)->get();
        } else {
            $questions = $query->orderBy('urutan')->limit($jumlah)->get();
        }

        return response()->json([
            'questions' => $questions->load('options'),
            'total' => $questions->count(),
        ]);
    }
}
