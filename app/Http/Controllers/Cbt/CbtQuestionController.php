<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\StoreCbtQuestionRequest;
use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Support\Facades\DB;

class CbtQuestionController extends Controller
{
    private function data(StoreCbtQuestionRequest $request): array
    {
        return $request->validated();
    }

    private function mutate(CbtQuestionBank $bank, callable $callback): void
    {
        DB::transaction(function () use ($bank, $callback) {
            $locked = CbtQuestionBank::whereKey($bank->id)->lockForUpdate()->firstOrFail();
            $callback($locked);
        }, 3);
    }

    public function store(StoreCbtQuestionRequest $request, CbtQuestionBank $bank)
    {
        $this->authorize('update', $bank);
        $data = $this->data($request);
        $this->mutate($bank, function ($locked) use ($data) {
            abort_if($locked->questions()->count() >= 200, 422, 'Maksimal 200 soal per bank.');
            $locked->questions()->create($data);
        });
        $this->sinkronEngine($bank->fresh());
        return redirect()->route('cbt.question-banks.show', $bank)->with('success', 'Soal ditambahkan.');
    }

    public function update(StoreCbtQuestionRequest $request, CbtQuestion $question)
    {
        $this->authorize('update', $question);
        $bank = $question->bank;
        $data = $this->data($request);
        $this->mutate($bank, fn () => $question->update($data));
        $this->sinkronEngine($bank->fresh());
        return redirect()->route('cbt.question-banks.show', $bank)->with('success', 'Soal diperbarui.');
    }

    public function destroy(CbtQuestion $question)
    {
        $this->authorize('delete', $question);
        $bank = $question->bank;
        $questionId = $question->id;
        $this->mutate($bank, fn () => $question->delete());
        if (config('simantap.exam_engine_cbt', true)) {
            app(LegacyExamMapper::class)->lupakanSoalCbt($questionId);
        }
        return redirect()->route('cbt.question-banks.show', $bank)->with('success', 'Soal dihapus.');
    }

    private function sinkronEngine(CbtQuestionBank $bank): void
    {
        if (config('simantap.exam_engine_cbt', true)) {
            app(LegacyExamMapper::class)->bankCbt($bank);
        }
    }
}
