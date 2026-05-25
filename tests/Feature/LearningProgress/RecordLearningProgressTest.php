<?php

namespace Tests\Feature\LearningProgress;

use App\Actions\Student\RecordLearningProgress;
use App\Models\LearningProgressEvent;
use App\Models\LearningProgressSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class RecordLearningProgressTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    private RecordLearningProgress $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(RecordLearningProgress::class);
    }

    public function test_open_heartbeats_close_compute_active_seconds(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        $sessionId = (string) Str::uuid();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:01:00Z'));

        $payload = [
            'session_id' => $sessionId,
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
                ['event' => 'heartbeat', 'occurred_at' => '2026-05-25T08:00:20Z'],
                ['event' => 'heartbeat', 'occurred_at' => '2026-05-25T08:00:40Z'],
                ['event' => 'close', 'occurred_at' => '2026-05-25T08:01:00Z'],
            ],
        ];

        $result = $this->action->handle($student, $payload);

        $this->assertSame(RecordLearningProgress::RESULT_RECORDED, $result);

        $session = LearningProgressSession::where('session_id', $sessionId)->firstOrFail();
        $this->assertSame(60, $session->active_seconds);
        $this->assertSame(0, $session->idle_seconds);
        $this->assertSame('closed', $session->end_reason);
        $this->assertNotNull($session->ended_at);
        $this->assertSame(4, LearningProgressEvent::where('session_id', $sessionId)->count());
    }

    public function test_blur_then_focus_separates_active_and_idle(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        $sessionId = (string) Str::uuid();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:02:00Z'));

        $payload = [
            'session_id' => $sessionId,
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
                ['event' => 'blur', 'occurred_at' => '2026-05-25T08:00:30Z'],     // 30s active
                ['event' => 'focus', 'occurred_at' => '2026-05-25T08:00:50Z'],    // +20s idle (capped at 60s gap)
                ['event' => 'close', 'occurred_at' => '2026-05-25T08:01:20Z'],    // +30s active
            ],
        ];

        $this->action->handle($student, $payload);
        $session = LearningProgressSession::where('session_id', $sessionId)->firstOrFail();

        $this->assertSame(60, $session->active_seconds);
        $this->assertSame(20, $session->idle_seconds);
    }

    public function test_max_active_gap_caps_long_idle_period(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        $sessionId = (string) Str::uuid();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:10:00Z'));

        // 10-min gap between open and heartbeat — must be capped at config max_active_gap_ms (60_000ms).
        $payload = [
            'session_id' => $sessionId,
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
                ['event' => 'heartbeat', 'occurred_at' => '2026-05-25T08:10:00Z'],
            ],
        ];

        $this->action->handle($student, $payload);
        $session = LearningProgressSession::where('session_id', $sessionId)->firstOrFail();

        $this->assertSame(60, $session->active_seconds);
    }

    public function test_events_arriving_out_of_order_are_sorted_before_compute(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        $sessionId = (string) Str::uuid();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:01:30Z'));

        // Server must sort by occurred_at; deliberately scrambled.
        $payload = [
            'session_id' => $sessionId,
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'close', 'occurred_at' => '2026-05-25T08:01:00Z'],
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
                ['event' => 'heartbeat', 'occurred_at' => '2026-05-25T08:00:30Z'],
            ],
        ];

        $this->action->handle($student, $payload);
        $session = LearningProgressSession::where('session_id', $sessionId)->firstOrFail();

        $this->assertSame(60, $session->active_seconds);
        $this->assertSame('closed', $session->end_reason);
    }

    public function test_drift_beyond_limit_rejects_entire_batch(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:00:00Z'));
        $maxDrift = (int) config('learning_progress.validation.max_clock_drift_minutes');

        $this->expectException(ValidationException::class);

        $this->action->handle($student, [
            'session_id' => (string) Str::uuid(),
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => Carbon::now()->subMinutes($maxDrift + 1)->toIso8601String()],
            ],
        ]);
    }

    public function test_drift_exactly_at_boundary_is_accepted(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:00:00Z'));
        $maxDrift = (int) config('learning_progress.validation.max_clock_drift_minutes');

        $result = $this->action->handle($student, [
            'session_id' => (string) Str::uuid(),
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => Carbon::now()->subMinutes($maxDrift)->toIso8601String()],
            ],
        ]);

        $this->assertSame(RecordLearningProgress::RESULT_RECORDED, $result);
    }

    public function test_duration_ms_is_clamped_not_rejected(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        $sessionId = (string) Str::uuid();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:00:30Z'));

        $this->action->handle($student, [
            'session_id' => $sessionId,
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z', 'duration_ms' => 99_999_999],
            ],
        ]);

        $cap = (int) config('learning_progress.session.max_active_gap_ms');
        $event = LearningProgressEvent::where('session_id', $sessionId)->firstOrFail();
        $this->assertSame($cap, $event->duration_ms);
    }

    public function test_opted_out_student_does_not_persist_anything(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial(['tracking_opt_out' => true]);
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:00:30Z'));

        DB::enableQueryLog();

        $result = $this->action->handle($student, [
            'session_id' => (string) Str::uuid(),
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
            ],
        ]);

        $this->assertSame(RecordLearningProgress::RESULT_OPTED_OUT, $result);
        $this->assertSame(0, LearningProgressEvent::query()->count());
        $this->assertSame(0, LearningProgressSession::query()->count());

        // No write queries should be issued post opt-out check.
        $writeQueries = collect(DB::getQueryLog())->filter(
            fn ($q) => preg_match('/^\s*(insert|update|delete)/i', (string) $q['query']),
        );
        $this->assertCount(0, $writeQueries);
    }

    public function test_batch_uses_at_most_two_insert_or_update_queries(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:02:00Z'));

        // Warm up cache (config etc.) to avoid noise.
        DB::enableQueryLog();
        DB::flushQueryLog();

        $events = [['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z']];
        for ($i = 1; $i <= 9; $i++) {
            $events[] = [
                'event' => 'heartbeat',
                'occurred_at' => Carbon::parse('2026-05-25T08:00:00Z')->addSeconds($i * 10)->toIso8601String(),
            ];
        }

        $this->action->handle($student, [
            'session_id' => (string) Str::uuid(),
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => $events,
        ]);

        $writes = collect(DB::getQueryLog())->filter(function ($q) {
            $sql = ltrim((string) $q['query']);

            // Count only event/session writes — exclude classroom_subject lookups etc.
            return preg_match('/^(insert|update)\s+(into\s+)?["`]?learning_progress_(events|sessions)["`]?/i', $sql);
        });

        $this->assertLessThanOrEqual(
            2,
            $writes->count(),
            "Expected ≤ 2 INSERT/UPDATE against progress tables, got {$writes->count()}: ".$writes->pluck('query')->implode(' | '),
        );
    }

    public function test_heartbeat_does_not_write_to_activity_log(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:01:00Z'));

        $base = Activity::query()->count();

        for ($i = 0; $i < 10; $i++) {
            $sessionId = (string) Str::uuid();
            $this->action->handle($student, [
                'session_id' => $sessionId,
                'trackable_type' => 'material',
                'trackable_id' => $material->id,
                'events' => [
                    ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
                    ['event' => 'heartbeat', 'occurred_at' => '2026-05-25T08:00:20Z'],
                    ['event' => 'close', 'occurred_at' => '2026-05-25T08:00:40Z'],
                ],
            ]);
        }

        $this->assertSame($base, Activity::query()->count(), 'Heartbeat insert must not produce activity_log rows.');
    }

    public function test_session_comeback_spawns_new_session_with_original_id_in_meta(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        $clientSessionId = (string) Str::uuid();
        Carbon::setTestNow(Carbon::parse('2026-05-25T09:00:00Z'));

        // First batch — normal open/close.
        $this->action->handle($student, [
            'session_id' => $clientSessionId,
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:55:00Z'],
                ['event' => 'close', 'occurred_at' => '2026-05-25T08:56:00Z'],
            ],
        ]);

        $first = LearningProgressSession::query()->firstOrFail();
        $this->assertNotNull($first->ended_at);
        $this->assertSame($clientSessionId, $first->session_id);

        // Second batch — same client session_id but the prior session is already ended.
        // Server must spawn a NEW session row (with new server-generated session_id), not reopen.
        $this->action->handle($student, [
            'session_id' => $clientSessionId,
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T09:00:00Z'],
                ['event' => 'heartbeat', 'occurred_at' => '2026-05-25T09:00:20Z'],
            ],
        ]);

        $allSessions = LearningProgressSession::query()
            ->where('trackable_id', $material->id)
            ->orderBy('started_at')
            ->get();

        $this->assertCount(2, $allSessions);
        $second = $allSessions->last();
        $this->assertNotSame($clientSessionId, $second->session_id, 'Spawned session must use a new server-generated session_id.');

        $firstEventOfSpawnedSession = LearningProgressEvent::where('session_id', $second->session_id)
            ->orderBy('occurred_at')
            ->first();
        $this->assertNotNull($firstEventOfSpawnedSession);
        $this->assertSame(
            $clientSessionId,
            data_get($firstEventOfSpawnedSession->meta, 'original_client_session_id'),
            'First event of spawned session must record original client session_id for audit.',
        );
    }

    public function test_two_tabs_with_different_session_ids_create_two_sessions(): void
    {
        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();
        $sessionA = (string) Str::uuid();
        $sessionB = (string) Str::uuid();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:01:30Z'));

        foreach ([$sessionA, $sessionB] as $sid) {
            $this->action->handle($student, [
                'session_id' => $sid,
                'trackable_type' => 'material',
                'trackable_id' => $material->id,
                'events' => [
                    ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
                    ['event' => 'close', 'occurred_at' => '2026-05-25T08:00:30Z'],
                ],
            ]);
        }

        $this->assertSame(
            2,
            LearningProgressSession::query()->where('trackable_id', $material->id)->count(),
        );
    }

    public function test_trackable_owned_by_another_class_is_forbidden(): void
    {
        // Student A enrolled in class A; we'll try to record progress on Material B (different class).
        ['student' => $studentA] = $this->scaffoldStudentWithMaterial();
        ['material' => $materialB] = $this->scaffoldStudentWithMaterial();

        Carbon::setTestNow(Carbon::parse('2026-05-25T08:00:30Z'));

        $this->expectException(AccessDeniedHttpException::class);

        $this->action->handle($studentA, [
            'session_id' => (string) Str::uuid(),
            'trackable_type' => 'material',
            'trackable_id' => $materialB->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
            ],
        ]);
    }

    public function test_classroom_subject_id_is_snapshotted_from_trackable(): void
    {
        ['student' => $student, 'material' => $material, 'classroomSubject' => $cs] = $this->scaffoldStudentWithMaterial();
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:00:30Z'));

        $this->action->handle($student, [
            'session_id' => (string) Str::uuid(),
            'trackable_type' => 'material',
            'trackable_id' => $material->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
            ],
        ]);

        $this->assertSame($cs->id, LearningProgressSession::query()->value('classroom_subject_id'));
        $this->assertSame($cs->id, LearningProgressEvent::query()->value('classroom_subject_id'));
    }

    public function test_assignment_classroom_subject_id_is_resolved_via_material(): void
    {
        ['student' => $student, 'material' => $material, 'classroomSubject' => $cs] = $this->scaffoldStudentWithMaterial();
        $assignment = $this->makeAssignment($material);
        Carbon::setTestNow(Carbon::parse('2026-05-25T08:00:30Z'));

        $this->action->handle($student, [
            'session_id' => (string) Str::uuid(),
            'trackable_type' => 'assignment',
            'trackable_id' => $assignment->id,
            'events' => [
                ['event' => 'open', 'occurred_at' => '2026-05-25T08:00:00Z'],
            ],
        ]);

        $this->assertSame($cs->id, LearningProgressSession::query()->value('classroom_subject_id'));
    }
}
