<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CbtQuestionController extends Controller
{
    private function data(Request $request): array
    {
        return $request->validate([
            'prompt' => 'required|string|max:10000',
            'options' => 'required|array:A,B,C,D|size:4',
            'options.A' => 'required|string|max:2000', 'options.B' => 'required|string|max:2000',
            'options.C' => 'required|string|max:2000', 'options.D' => 'required|string|max:2000',
            'correct_answer' => 'required|in:A,B,C,D',
        ]);
    }

    private function mutate(CbtQuestionBank $bank, callable $callback): void
    {
        abort_unless((int) $bank->guru_id === (int) auth()->id(), 403);
        DB::transaction(function () use ($bank, $callback) {
            $locked = CbtQuestionBank::whereKey($bank->id)->lockForUpdate()->firstOrFail();
            $callback($locked);
        }, 3);
    }

    public function store(Request $request, CbtQuestionBank $bank)
    {
        abort_unless((int) $bank->guru_id === (int) auth()->id(), 403);
        $data = $this->data($request);
        $this->mutate($bank, function ($locked) use ($data) {
            abort_if($locked->questions()->count() >= 200, 422, 'Maksimal 200 soal per bank.');
            $locked->questions()->create($data);
        });
        return redirect()->route('cbt.question-banks.show', $bank)->with('success', 'Soal ditambahkan.');
    }

    public function update(Request $request, CbtQuestion $question)
    {
        $bank = $question->bank;
        abort_unless((int) $bank->guru_id === (int) auth()->id(), 403);
        $data = $this->data($request);
        $this->mutate($bank, fn () => $question->update($data));
        return redirect()->route('cbt.question-banks.show', $bank)->with('success', 'Soal diperbarui.');
    }

    public function destroy(CbtQuestion $question)
    {
        $bank = $question->bank;
        $this->mutate($bank, fn () => $question->delete());
        return redirect()->route('cbt.question-banks.show', $bank)->with('success', 'Soal dihapus.');
    }
}
