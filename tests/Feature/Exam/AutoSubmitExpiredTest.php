<?php

namespace Tests\Feature\Exam;

use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class AutoSubmitExpiredTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    private function makeSession(array $sessionOverrides = []): ExamSession
    {
        $ctx = $this->scaffoldStudentWithMaterial();
        $exam = $this->makeExam($ctx['material'], [
            'starts_at' => Carbon::now()->subHours(2),
            'duration_minutes' => 60,
        ]);

        $q = ExamQuestion::create([
            'exam_id' => $exam->id, 'type' => 'multiple_choice', 'question' => 'q',
            'options' => ['A' => '1', 'B' => '2'], 'correct_answer' => 'A', 'score' => 10, 'order' => 1,
        ]);

        $session = ExamSession::create(array_merge([
            'exam_id' => $exam->id,
            'student_id' => $ctx['student']->id,
            'started_at' => Carbon::now()->subMinutes(61), // expired (started + 60 < now)
        ], $sessionOverrides));

        ExamAnswer::create([
            'exam_session_id' => $session->id, 'exam_question_id' => $q->id, 'answer' => 'A',
        ]);

        return $session;
    }

    public function test_expired_session_is_auto_submitted_and_graded(): void
    {
        $session = $this->makeSession();

        Artisan::call('exam:auto-submit-expired');

        $fresh = $session->fresh();
        $this->assertNotNull($fresh->submitted_at);
        $this->assertSame('auto_timeout', $fresh->submission_reason);
        // submitted_at = started_at + duration, bukan now()
        $this->assertEquals(
            $session->started_at->copy()->addMinutes(60)->timestamp,
            $fresh->submitted_at->timestamp,
        );
        $this->assertSame(10.0, (float) $fresh->total_score);
    }

    public function test_already_submitted_session_is_not_overwritten(): void
    {
        $submittedAt = Carbon::now()->subMinutes(5);
        $session = $this->makeSession([
            'submitted_at' => $submittedAt,
            'submission_reason' => 'student',
            'total_score' => 99,
        ]);

        Artisan::call('exam:auto-submit-expired');

        $fresh = $session->fresh();
        // Race guard: session yang sudah disubmit siswa TIDAK boleh ditimpa auto_timeout
        $this->assertSame('student', $fresh->submission_reason);
        $this->assertEquals($submittedAt->timestamp, $fresh->submitted_at->timestamp);
        $this->assertSame(99.0, (float) $fresh->total_score);
    }

    public function test_not_yet_expired_session_is_left_alone(): void
    {
        $session = $this->makeSession([
            'started_at' => Carbon::now()->subMinutes(10), // 10 < 60 → belum expired
        ]);

        Artisan::call('exam:auto-submit-expired');

        $this->assertNull($session->fresh()->submitted_at);
    }
}
