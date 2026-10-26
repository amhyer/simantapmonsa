<?php

namespace App\Services;

use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\Tryout\StudentAnswer;
use App\Models\Tryout\TryoutQuestion;
use Illuminate\Support\Collection;

/**
 * Supplies subject-level details for current and historical Tryout results.
 * Older rows can have an empty or legacy-shaped JSON summary; in that case we
 * use the unified exam result first, then recompute from the mirrored answers.
 */
class TryoutResultDetailService
{
    /** @return array<int, array<string, mixed>> */
    public function forResult(TryoutResult $result): array
    {
        $passingGrades = $this->passingGrades($result);
        $stored = $this->normalize($result->detail_nilai_mapel, $passingGrades);
        if ($stored !== []) {
            return $stored;
        }

        $attempt = ExamAttempt::query()
            ->with('result')
            ->where('sumber_tabel', 'tryout_results')
            ->where('sumber_id', $result->getKey())
            ->first();

        $engineDetails = $this->normalize($attempt?->result?->detail_per_section, $passingGrades);
        if ($engineDetails !== []) {
            return $engineDetails;
        }

        return $this->recomputeFromLegacyAnswers($result);
    }

    /** @param array<int, array<string, mixed>> $details
     *  @return array<int, array<string, mixed>>
     */
    public function weaknesses(TryoutResult $result, array $details): array
    {
        $stored = $result->analisis_kelemahan;
        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        return collect($details)
            ->filter(fn (array $row): bool => ! (bool) ($row['lulus'] ?? true))
            ->sortBy('persentase')
            ->take(3)
            ->map(fn (array $row): array => [
                'nama_mapel' => $row['nama_mapel'] ?? 'Mata pelajaran',
                'persentase' => (float) ($row['persentase'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Accept only the current list-of-rows format. Invalid/legacy maps fall
     * through to the persisted exam result or answer-based recomputation.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalize(mixed $details, array $passingGrades = []): array
    {
        if (! is_array($details) || ! array_is_list($details)) {
            return [];
        }

        $normalized = [];

        foreach ($details as $row) {
            if (! is_array($row)
                || ! is_numeric($row['skor'] ?? null)
                || ! is_numeric($row['maksimal'] ?? null)) {
                continue;
            }

            $score = (float) $row['skor'];
            $maxScore = (float) $row['maksimal'];
            $percentage = is_numeric($row['persentase'] ?? null)
                ? (float) $row['persentase']
                : ($maxScore > 0 ? round($score / $maxScore * 100, 2) : 0.0);
            $subjectId = isset($row['mata_pelajaran_id']) ? (int) $row['mata_pelajaran_id'] : null;
            $passingGrade = is_numeric($row['passing_grade_mapel'] ?? null)
                ? (float) $row['passing_grade_mapel']
                : ($subjectId !== null ? (float) ($passingGrades[$subjectId] ?? 60) : 60.0);
            $passed = ($row['lulus'] ?? null) !== null
                ? filter_var($row['lulus'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null;

            $normalized[] = [
                'mata_pelajaran_id' => $subjectId,
                'nama_mapel' => trim((string) ($row['nama_mapel'] ?? '')) ?: 'Mata pelajaran',
                'skor' => $score,
                'maksimal' => $maxScore,
                'persentase' => $percentage,
                'passing_grade_mapel' => $passingGrade,
                'lulus' => $passed ?? ($percentage >= $passingGrade),
            ];
        }

        return $normalized;
    }

    /** @return array<int, float> */
    private function passingGrades(TryoutResult $result): array
    {
        $session = TryoutSession::withTrashed()->find($result->tryout_session_id);
        if (! $session) {
            return [];
        }

        $package = Package::withTrashed()->with('subjects')->find($session->package_id);

        return $package?->subjects
            ->mapWithKeys(fn ($subject): array => [
                (int) $subject->mata_pelajaran_id => (float) ($subject->passing_grade_mapel ?? 60),
            ])
            ->all() ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    private function recomputeFromLegacyAnswers(TryoutResult $result): array
    {
        $session = TryoutSession::withTrashed()->find($result->tryout_session_id);
        if (! $session) {
            return [];
        }

        $package = Package::withTrashed()
            ->with('subjects.mataPelajaran')
            ->find($session->package_id);
        $subjects = $package?->subjects ?? collect();

        if ($subjects->isEmpty()) {
            return [];
        }

        $answers = StudentAnswer::query()
            ->where('tryout_session_id', $result->tryout_session_id)
            ->where('siswa_id', $result->siswa_id)
            ->where('attempt_number', $result->attempt_number)
            ->get(['tryout_subject_id', 'poin_diperoleh']);

        // Do not invent zero scores when no historical answers remain.
        if ($answers->isEmpty()) {
            return [];
        }

        $subjectIds = $subjects->pluck('id');
        $maxScores = TryoutQuestion::withTrashed()
            ->whereIn('tryout_subject_id', $subjectIds)
            ->get(['tryout_subject_id', 'poin'])
            ->groupBy('tryout_subject_id')
            ->map(fn (Collection $questions): float => (float) $questions->sum('poin'));
        $answersBySubject = $answers->groupBy('tryout_subject_id');

        return $subjects->map(function ($subject) use ($answersBySubject, $maxScores): array {
            $subjectAnswers = $answersBySubject->get((string) $subject->id)
                ?? $answersBySubject->get($subject->id, collect());
            $score = (float) $subjectAnswers->sum('poin_diperoleh');
            $maxScore = (float) $maxScores->get($subject->id, 0);
            $percentage = $maxScore > 0 ? round($score / $maxScore * 100, 2) : 0.0;
            $passingGrade = (float) ($subject->passing_grade_mapel ?? 60);

            return [
                'mata_pelajaran_id' => (int) $subject->mata_pelajaran_id,
                'nama_mapel' => $subject->mataPelajaran?->nama ?? 'Mata pelajaran',
                'skor' => $score,
                'maksimal' => $maxScore,
                'persentase' => $percentage,
                'passing_grade_mapel' => $passingGrade,
                'lulus' => $percentage >= $passingGrade,
            ];
        })->values()->all();
    }
}
