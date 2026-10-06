<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\Question;
use App\Models\Cbt\QuestionBank;
use App\Models\Cbt\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CbtQuestionController extends Controller
{
    public function store(Request $request, QuestionBank $bank)
    {
        $validated = $request->validate([
            'tipe_soal' => 'required|in:pilihan_ganda,isian_singkat,benar_salah,menjodohkan,essay',
            'pertanyaan' => 'required|string',
            'media_type' => 'required|in:text,image,audio,video',
            'media_url' => 'nullable|string|max:500',
            'poin' => 'required|numeric|min:0',
            'waktu_jawab_detik' => 'nullable|integer|min:0',
            'pembahasan' => 'nullable|string',
            'opsi' => 'required_if:tipe_soal,pilihan_ganda|array',
            'opsi.*.huruf' => 'required_if:tipe_soal,pilihan_ganda|in:A,B,C,D,E',
            'opsi.*.isi' => 'required_if:tipe_soal,pilihan_ganda|string',
            'opsi.*.benar' => 'required_if:tipe_soal,pilihan_ganda|boolean',
        ]);

        $question = Question::create([
            'uuid' => Str::uuid(),
            'question_bank_id' => $bank->id,
            'urutan' => $bank->questions()->count() + 1,
            'tipe_soal' => $validated['tipe_soal'],
            'pertanyaan' => $validated['pertanyaan'],
            'media_type' => $validated['media_type'],
            'media_url' => $validated['media_url'] ?? null,
            'poin' => $validated['poin'],
            'waktu_jawab_detik' => $validated['waktu_jawab_detik'] ?? 0,
            'pembahasan' => $validated['pembahasan'] ?? null,
            'aktif' => true,
        ]);

        // Create options for multiple choice
        if ($validated['tipe_soal'] === 'pilihan_ganda' && isset($validated['opsi'])) {
            foreach ($validated['opsi'] as $opsi) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'huruf' => $opsi['huruf'],
                    'isi_opsi' => $opsi['isi'],
                    'adalah_benar' => $opsi['benar'],
                    'urutan' => 0,
                ]);
            }
        }

        // Update bank question count
        $bank->update(['jumlah_soal' => $bank->questions()->count()]);

        return redirect()->route('cbt.question-banks.show', $bank)
            ->with('success', 'Soal berhasil ditambahkan');
    }

    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'pertanyaan' => 'required|string',
            'media_url' => 'nullable|string|max:500',
            'poin' => 'required|numeric|min:0',
            'waktu_jawab_detik' => 'nullable|integer|min:0',
            'pembahasan' => 'nullable|string',
            'opsi' => 'array',
        ]);

        $question->update([
            'pertanyaan' => $validated['pertanyaan'],
            'media_url' => $validated['media_url'] ?? $question->media_url,
            'poin' => $validated['poin'],
            'waktu_jawab_detik' => $validated['waktu_jawab_detik'] ?? $question->waktu_jawab_detik,
            'pembahasan' => $validated['pembahasan'] ?? $question->pembahasan,
        ]);

        // Update options if provided
        if (isset($validated['opsi'])) {
            foreach ($validated['opsi'] as $opsiData) {
                $option = QuestionOption::where('question_id', $question->id)
                                       ->where('huruf', $opsiData['huruf'])
                                       ->first();
                if ($option) {
                    $option->update([
                        'isi_opsi' => $opsiData['isi'],
                        'adalah_benar' => $opsiData['benar'],
                    ]);
                }
            }
        }

        return back()->with('success', 'Soal berhasil diperbarui');
    }

    public function destroy(Question $question)
    {
        $bank = $question->questionBank;
        $question->delete();

        // Update bank question count
        $bank->update(['jumlah_soal' => $bank->questions()->count()]);

        return back()->with('success', 'Soal berhasil dihapus');
    }
}
