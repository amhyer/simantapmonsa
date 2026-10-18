<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtQuestionBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CbtQuestionBankController extends Controller
{
    private function own(CbtQuestionBank $bank): void
    {
        abort_unless((int) $bank->guru_id === (int) auth()->id(), 403);
    }

    public function index()
    {
        $banks = CbtQuestionBank::where('guru_id', auth()->id())->withCount('questions')->latest()->paginate(20);
        return view('cbt.banks.index', compact('banks'));
    }

    public function create()
    {
        return view('cbt.banks.form', ['bank' => new CbtQuestionBank]);
    }

    private function data(Request $request): array
    {
        return $request->validate(['title' => 'required|string|max:200', 'subject' => 'required|string|max:100']);
    }

    public function store(Request $request)
    {
        $bank = CbtQuestionBank::create($this->data($request) + ['guru_id' => auth()->id()]);
        return redirect()->route('cbt.question-banks.show', $bank)->with('success', 'Bank soal dibuat.');
    }

    public function show(CbtQuestionBank $bank)
    {
        $this->own($bank);
        $questions = $bank->questions()->orderBy('id')->paginate(20);
        return view('cbt.banks.show', compact('bank', 'questions'));
    }

    public function edit(CbtQuestionBank $bank)
    {
        $this->own($bank);
        return view('cbt.banks.form', compact('bank'));
    }

    public function update(Request $request, CbtQuestionBank $bank)
    {
        $this->own($bank);
        $data = $this->data($request);
        DB::transaction(function () use ($bank, $data) {
            $locked = CbtQuestionBank::whereKey($bank->id)->lockForUpdate()->firstOrFail();
            $locked->update($data);
        });
        return redirect()->route('cbt.question-banks.show', $bank)->with('success', 'Bank soal diperbarui.');
    }

    public function destroy(CbtQuestionBank $bank)
    {
        $this->own($bank);
        DB::transaction(function () use ($bank) {
            $locked = CbtQuestionBank::whereKey($bank->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->sessions()->exists(), 409, 'Bank telah dipakai sesi; tidak boleh dihapus.');
            $locked->delete();
        });
        return redirect()->route('cbt.question-banks.index')->with('success', 'Bank soal dihapus.');
    }

    public function apiGetQuestions(CbtQuestionBank $bank)
    {
        $this->own($bank);
        // Endpoint khusus guru pemilik. Kunci tetap hidden pada model.
        return response()->json($bank->questions()->orderBy('id')->paginate(50));
    }
}
