<?php

namespace Tests\Feature\Exam;

use App\Actions\Student\GetStudentExamSession;
use App\Actions\Student\SubmitExamSession;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Services\ExamGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class ExamGradingTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    /**
     * @return array{session: ExamSession, mc: ExamQuestion, sa: ExamQuestion, essay: ExamQuestion}
     */
    private function scaffoldExamSession(array $answers = []): array
    {
        $ctx = $this->scaffoldStudentWithMaterial();
        $exam = $this->makeExam($ctx['material'], ['starts_at' => Carbon::now()->subHour()]);

        $mc = ExamQuestion::create([
            'exam_id' => $exam->id, 'type' => 'multiple_choice', 'question' => '1+1?',
            'options' => ['A' => '2', 'B' => '3'], 'correct_answer' => 'A', 'score' => 10, 'order' => 1,
        ]);
        $sa = ExamQuestion::create([
            'exam_id' => $exam->id, 'type' => 'short_answer', 'question' => 'Ibukota?',
            'correct_answer' => 'Jakarta', 'score' => 10, 'order' => 2,
        ]);
        $essay = ExamQuestion::create([
            'exam_id' => $exam->id, 'type' => 'essay', 'question' => 'Jelaskan.',
            'correct_answer' => null, 'score' => 20, 'order' => 3,
        ]);

        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $ctx['student']->id,
            'started_at' => Carbon::now()->subMinutes(10),
        ]);

        foreach ($answers as $questionId => $answer) {
            ExamAnswer::create([
                'exam_session_id' => $session->id,
                'exam_question_id' => $questionId,
                'answer' => $answer,
            ]);
        }

        return compact('session', 'mc', 'sa', 'essay');
    }

    public function test_grades_mc_and_short_answer_and_leaves_essay_null(): void
    {
        $ctx = $this->scaffoldExamSession();
        // isi jawaban setelah tahu id soal
        ExamAnswer::create(['exam_session_id' => $ctx['session']->id, 'exam_question_id' => $ctx['mc']->id, 'answer' => 'A']);
        ExamAnswer::create(['exam_session_id' => $ctx['session']->id, 'exam_question_id' => $ctx['sa']->id, 'answer' => '  jakarta ']);
        ExamAnswer::create(['exam_session_id' => $ctx['session']->id, 'exam_question_id' => $ctx['essay']->id, 'answer' => 'panjang']);

        (new ExamGrader)->grade($ctx['session']);

        $this->assertSame(10.0, (float) ExamAnswer::where('exam_question_id', $ctx['mc']->id)->value('score'));
        $this->assertSame(10.0, (float) ExamAnswer::where('exam_question_id', $ctx['sa']->id)->value('score'), 'short_answer case/trim insensitive');
        $this->assertNull(ExamAnswer::where('exam_question_id', $ctx['essay']->id)->value('score'), 'essay tetap null');
        $this->assertSame(20.0, (float) $ctx['session']->fresh()->total_score);
    }

    public function test_wrong_mc_scores_zero(): void
    {
        $ctx = $this->scaffoldExamSession();
        ExamAnswer::create(['exam_session_id' => $ctx['session']->id, 'exam_question_id' => $ctx['mc']->id, 'answer' => 'B']);

        (new ExamGrader)->grade($ctx['session']);

        $this->assertSame(0.0, (float) ExamAnswer::where('exam_question_id', $ctx['mc']->id)->value('score'));
    }

    public function test_regrade_does_not_overwrite_manual_essay_score(): void
    {
        $ctx = $this->scaffoldExamSession();
        $essayAnswer = ExamAnswer::create([
            'exam_session_id' => $ctx['session']->id, 'exam_question_id' => $ctx['essay']->id, 'answer' => 'jawaban',
        ]);

        // Guru menilai manual
        $essayAnswer->update(['score' => 15]);

        (new ExamGrader)->grade($ctx['session']);

        $this->assertSame(15.0, (float) $essayAnswer->fresh()->score, 'nilai essay manual tidak boleh ditimpa jadi null');
        $this->assertSame(15.0, (float) $ctx['session']->fresh()->total_score);
    }

    public function test_score_is_hidden_until_results_released(): void
    {
        $ctx = $this->scaffoldStudentWithMaterial();
        $exam = $this->makeExam($ctx['material'], [
            'starts_at' => Carbon::now()->subHour(),
            'results_released_at' => Carbon::now()->addDay(), // belum dirilis
        ]);
        ExamQuestion::create([
            'exam_id' => $exam->id, 'type' => 'multiple_choice', 'question' => 'q',
            'options' => ['A' => '1'], 'correct_answer' => 'A', 'score' => 10, 'order' => 1,
        ]);
        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $ctx['student']->id,
            'started_at' => Carbon::now()->subMinutes(10),
            'submitted_at' => Carbon::now()->subMinutes(2),
            'total_score' => 10,
        ]);

        $action = app(GetStudentExamSession::class);

        $before = $action->handle($ctx['student'], $session->id);
        $this->assertNull($before['session']['total_score'], 'skor harus disembunyikan sebelum rilis');
        $this->assertFalse($before['session']['results_released']);

        $exam->update(['results_released_at' => Carbon::now()->subMinute()]);

        $after = $action->handle($ctx['student'], $session->id);
        $this->assertSame(10.0, $after['session']['total_score']);
        $this->assertTrue($after['session']['results_released']);
    }

    public function test_submit_exam_session_is_idempotent(): void
    {
        $ctx = $this->scaffoldExamSession();
        $student = $ctx['session']->student;
        ExamAnswer::create(['exam_session_id' => $ctx['session']->id, 'exam_question_id' => $ctx['mc']->id, 'answer' => 'A']);

        $action = app(SubmitExamSession::class);
        $first = $action->handle($student, $ctx['session']->id);
        $submittedAt = $first->submitted_at;

        // submit kedua kali harus mengembalikan session yang sama tanpa mengubah submitted_at
        $second = $action->handle($student, $ctx['session']->id);

        $this->assertNotNull($submittedAt);
        $this->assertEquals($submittedAt->timestamp, $second->submitted_at->timestamp);
    }
}
