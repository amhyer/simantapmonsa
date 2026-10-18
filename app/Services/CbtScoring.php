<?php

namespace App\Services;

/** Logika murni; tidak menerima skor maupun kunci jawaban dari browser. */
class CbtScoring
{
    public static function grade(array $questions, array $answers): array
    {
        $correct = 0;
        foreach ($questions as $question) {
            $answer = $answers[$question['id']] ?? null;
            if ($answer !== null && $answer === $question['correct_answer']) {
                $correct++;
            }
        }

        return [
            'correct_count' => $correct,
            'score' => count($questions) ? round(100 * $correct / count($questions), 2) : 0.0,
        ];
    }

    public static function publicQuestions(array $questions): array
    {
        return array_map(function (array $question) {
            unset($question['correct_answer']);
            return $question;
        }, $questions);
    }
}
