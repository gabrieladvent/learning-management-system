<?php

namespace Tests\Feature\LearningProgress;

use App\Models\LearningProgressSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class CloseStaleSessionsCommandTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    public function test_session_idle_past_timeout_is_closed_with_timeout_reason(): void
    {
        ['student' => $student, 'material' => $material, 'classroomSubject' => $cs] = $this->scaffoldStudentWithMaterial();
        $timeoutMinutes = (int) config('learning_progress.session.timeout_minutes', 5);

        Carbon::setTestNow(Carbon::parse('2026-05-25T08:00:00Z'));

        $stale = LearningProgressSession::create([
            'student_id' => $student->id,
            'trackable_type' => $material->getMorphClass(),
            'trackable_id' => $material->id,
            'classroom_subject_id' => $cs->id,
            'session_id' => (string) Str::uuid(),
            'started_at' => Carbon::now()->subMinutes($timeoutMinutes + 10),
            'last_seen_at' => Carbon::now()->subMinutes($timeoutMinutes + 5),
            'active_seconds' => 30,
            'idle_seconds' => 0,
        ]);

        $fresh = LearningProgressSession::create([
            'student_id' => $student->id,
            'trackable_type' => $material->getMorphClass(),
            'trackable_id' => $material->id,
            'classroom_subject_id' => $cs->id,
            'session_id' => (string) Str::uuid(),
            'started_at' => Carbon::now()->subMinute(),
            'last_seen_at' => Carbon::now(),
            'active_seconds' => 10,
            'idle_seconds' => 0,
        ]);

        $this->artisan('progress:close-stale-sessions')->assertSuccessful();

        $stale->refresh();
        $this->assertNotNull($stale->ended_at);
        $this->assertSame('timeout', $stale->end_reason);
        $this->assertTrue($stale->ended_at->equalTo($stale->last_seen_at));

        $fresh->refresh();
        $this->assertNull($fresh->ended_at);
        $this->assertNull($fresh->end_reason);
    }
}
