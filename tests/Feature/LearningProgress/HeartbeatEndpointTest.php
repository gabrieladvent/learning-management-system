<?php

namespace Tests\Feature\LearningProgress;

use App\Models\LearningProgressEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class HeartbeatEndpointTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    public function test_authenticated_student_can_post_heartbeat_and_gets_204(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:01:00Z'));

        $response = $this->actingAs($student, 'student')
            ->postJson(route('student.progress.heartbeat'), [
                'session_id' => (string) Str::uuid(),
                'trackable_type' => 'material',
                'trackable_id' => $material->id,
                'events' => [
                    ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
                    ['event' => 'close', 'occurred_at' => '2026-05-25T08:01:00Z'],
                ],
            ]);

        $response->assertNoContent();
        $this->assertSame(2, LearningProgressEvent::query()->count());
    }

    public function test_validation_failure_returns_422(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();

        $response = $this->actingAs($student, 'student')
            ->postJson(route('student.progress.heartbeat'), [
                'session_id' => 'not-a-uuid',
                'trackable_type' => 'material',
                'trackable_id' => $material->id,
                'events' => [
                    ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_dismiss_disclosure_records_timestamp_on_user(): void
    {
        ['student' => $student] = $this->scaffoldStudentWithMaterial();
        // The scaffold doesn't tie a user to the student; create one explicitly.
        $user = User::create([
            'name' => 'Student User',
            'email' => 'student-'.Str::random(6).'@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $student->update(['user_id' => $user->id]);

        $this->actingAs($student->fresh(), 'student')
            ->postJson(route('student.progress.disclosure-seen'))
            ->assertNoContent();

        $this->assertNotNull($user->refresh()->tracking_disclosure_seen_at);
    }
}
