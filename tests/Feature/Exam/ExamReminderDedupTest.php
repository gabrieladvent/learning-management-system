<?php

namespace Tests\Feature\Exam;

use App\Jobs\SendExamStartReminders;
use App\Notifications\StudentDeadlineReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class ExamReminderDedupTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    public function test_reminder_sent_only_once_even_when_job_runs_multiple_times(): void
    {
        Notification::fake();

        $ctx = $this->scaffoldStudentWithMaterial();
        $this->makeExam($ctx['material'], [
            'starts_at' => Carbon::now()->addMinutes(30), // dalam window now..now+1h
        ]);

        // Job jalan 4× (mensimulasikan cadence 15 menit selama exam dalam window)
        (new SendExamStartReminders)->handle();
        (new SendExamStartReminders)->handle();
        (new SendExamStartReminders)->handle();
        (new SendExamStartReminders)->handle();

        Notification::assertSentToTimes($ctx['student'], StudentDeadlineReminder::class, 1);

        $this->assertDatabaseCount('exam_start_reminders', 1);
    }

    public function test_no_reminder_when_exam_outside_window(): void
    {
        Notification::fake();

        $ctx = $this->scaffoldStudentWithMaterial();
        $this->makeExam($ctx['material'], [
            'starts_at' => Carbon::now()->addHours(3), // di luar window
        ]);

        (new SendExamStartReminders)->handle();

        Notification::assertNotSentTo($ctx['student'], StudentDeadlineReminder::class);
        $this->assertDatabaseCount('exam_start_reminders', 0);
    }
}
