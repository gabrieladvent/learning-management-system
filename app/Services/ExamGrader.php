<?php

namespace App\Services;

use App\Models\Enums\QuestionTypeEnum;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use Illuminate\Support\Facades\DB;

class ExamGrader
{
    /**
     * Auto-grade semua jawaban di session.
     *
     * Rule per question type:
     *  - multiple_choice : answer === correct_answer (exact) → score = question.score, else 0
     *  - short_answer    : strtolower(trim(answer)) === strtolower(trim(correct_answer)) → full / 0
     *  - essay           : skip (score tetap null, menunggu guru menilai manual)
     *
     * Tidak mengubah session.submitted_at — itu di-set di action SubmitExam.
     * total_score di-recompute sebagai sum score semua answer (essay tetap null = 0
     * sampai guru nilai).
     */
    public function grade(ExamSession $session): ExamSession
    {
        return DB::transaction(function () use ($session) {
            $questions = $session->exam->questions()->get()->keyBy('id');

            $session->answers()
                ->get()
                ->each(function (ExamAnswer $answer) use ($questions) {
                    /** @var ?ExamQuestion $question */
                    $question = $questions->get($answer->exam_question_id);

                    if (! $question) {
                        return;
                    }

                    $score = $this->scoreFor($question, $answer->answer);

                    // Essay: jangan overwrite kalau guru sudah pernah nilai.
                    if ($score === null && $answer->score !== null) {
                        return;
                    }

                    $answer->score = $score;
                    $answer->save();
                });

            // total_score = sum dari score yang sudah terisi. Untuk session yang masih
            // punya essay belum dinilai, ini berarti "partial total" — frontend akan
            // tampilkan label "Menunggu penilaian" sampai semua answer.score terisi.
            $session->total_score = (float) $session->answers()->sum('score');
            $session->save();

            return $session->fresh();
        });
    }

    private function scoreFor(ExamQuestion $question, ?string $answer): ?float
    {
        $full = (float) $question->score;

        if ($question->type === QuestionTypeEnum::Essay) {
            return null;
        }

        if ($answer === null || trim($answer) === '') {
            return 0.0;
        }

        $correct = (string) ($question->correct_answer ?? '');

        if ($question->type === QuestionTypeEnum::MultipleChoice) {
            return $answer === $correct ? $full : 0.0;
        }

        // short_answer: case-insensitive trim match
        return mb_strtolower(trim($answer)) === mb_strtolower(trim($correct)) ? $full : 0.0;
    }
}
