<?php

namespace Tests\Feature\LearningProgress;

use App\Models\ClassroomSubject;
use App\Models\LearningProgressDailyRollup;
use App\Models\LearningProgressSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class RollupDailyCommandTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    public function test_two_sessions_same_day_aggregate_into_one_rollup_row(): void
    {
        ['student' => $student, 'material' => $material, 'classroomSubject' => $cs] = $this->scaffoldStudentWithMaterial();
        Carbon::setTestNow(Carbon::parse('2026-05-25T10:01:00Z'));

        // 2 sesi pada 2026-05-25 (Asia/Jakarta) — last_seen di window UTC.
        $this->makeSession($student->id, $material, $cs, '2026-05-25T08:00:00Z', '2026-05-25T08:01:00Z', 60);
        $this->makeSession($student->id, $material, $cs, '2026-05-25T09:00:00Z', '2026-05-25T09:00:30Z', 30);

        $this->artisan('progress:rollup-daily', ['--date' => '2026-05-25'])->assertSuccessful();

        $rollups = LearningProgressDailyRollup::query()->get();
        $this->assertCount(1, $rollups);

        $row = $rollups->first();
        $this->assertSame($student->id, $row->student_id);
        $this->assertSame($cs->id, $row->classroom_subject_id);
        $this->assertSame('2026-05-25', $row->date->format('Y-m-d'));
        $this->assertSame(90, $row->material_seconds);
        $this->assertSame(1, $row->materials_opened);
    }

    public function test_rollup_is_idempotent_on_rerun(): void
    {
        ['student' => $student, 'material' => $material, 'classroomSubject' => $cs] = $this->scaffoldStudentWithMaterial();
        Carbon::setTestNow(Carbon::parse('2026-05-25T10:00:00Z'));

        $this->makeSession($student->id, $material, $cs, '2026-05-25T08:00:00Z', '2026-05-25T08:01:00Z', 60);

        $this->artisan('progress:rollup-daily', ['--date' => '2026-05-25'])->assertSuccessful();
        $firstId = LearningProgressDailyRollup::query()->value('id');
        $firstCreatedAt = LearningProgressDailyRollup::query()->value('created_at');

        // Tambah satu sesi lagi setelah rollup pertama; re-run harus tetap 1 row, jumlah ter-update.
        $this->makeSession($student->id, $material, $cs, '2026-05-25T09:00:00Z', '2026-05-25T09:00:30Z', 30);

        $this->artisan('progress:rollup-daily', ['--date' => '2026-05-25'])->assertSuccessful();

        $rollups = LearningProgressDailyRollup::query()->get();
        $this->assertCount(1, $rollups, 'Rollup must be idempotent — single row per (student, classroom_subject, date).');

        $row = $rollups->first();
        $this->assertSame($firstId, $row->id, 'ID must not change on re-run.');
        $this->assertTrue($firstCreatedAt->equalTo($row->created_at), 'created_at must be preserved on re-run.');
        $this->assertSame(90, $row->material_seconds);
    }

    public function test_assignment_and_exam_seconds_aggregated_separately(): void
    {
        ['student' => $student, 'material' => $material, 'classroomSubject' => $cs] = $this->scaffoldStudentWithMaterial();
        $assignment = $this->makeAssignment($material);
        $exam = $this->makeExam($material);
        Carbon::setTestNow(Carbon::parse('2026-05-25T10:00:00Z'));

        $this->makeSession($student->id, $material, $cs, '2026-05-25T08:00:00Z', '2026-05-25T08:01:00Z', 60);
        $this->makeSession($student->id, $assignment, $cs, '2026-05-25T08:10:00Z', '2026-05-25T08:10:45Z', 45);
        $this->makeSession($student->id, $exam, $cs, '2026-05-25T08:20:00Z', '2026-05-25T08:20:20Z', 20);

        $this->artisan('progress:rollup-daily', ['--date' => '2026-05-25'])->assertSuccessful();

        $row = LearningProgressDailyRollup::query()->firstOrFail();
        $this->assertSame(60, $row->material_seconds);
        $this->assertSame(45, $row->assignment_seconds);
        $this->assertSame(20, $row->exam_seconds);
        $this->assertSame(1, $row->materials_opened);
        $this->assertSame(1, $row->assignments_worked);
        $this->assertSame(1, $row->exams_attempted);
    }

    public function test_session_outside_local_day_is_excluded(): void
    {
        ['student' => $student, 'material' => $material, 'classroomSubject' => $cs] = $this->scaffoldStudentWithMaterial();
        Carbon::setTestNow(Carbon::parse('2026-05-26T10:00:00Z'));

        // 2026-05-25 Asia/Jakarta = 2026-05-24 17:00:00 .. 2026-05-25 17:00:00 UTC.
        // 18:00 UTC pada 2026-05-25 → 2026-05-26 01:00 lokal → di luar window.
        $this->makeSession($student->id, $material, $cs, '2026-05-25T17:00:00Z', '2026-05-25T18:00:00Z', 60);

        $this->artisan('progress:rollup-daily', ['--date' => '2026-05-25'])->assertSuccessful();

        $this->assertSame(0, LearningProgressDailyRollup::query()->count());
    }

    private function makeSession(
        string $studentId,
        Model $trackable,
        ClassroomSubject $cs,
        string $startedAt,
        string $lastSeenAt,
        int $activeSeconds,
    ): LearningProgressSession {
        return LearningProgressSession::create([
            'student_id' => $studentId,
            'trackable_type' => $trackable->getMorphClass(),
            'trackable_id' => $trackable->getKey(),
            'classroom_subject_id' => $cs->id,
            'session_id' => (string) Str::uuid(),
            'started_at' => $startedAt,
            'last_seen_at' => $lastSeenAt,
            'ended_at' => $lastSeenAt,
            'end_reason' => 'closed',
            'active_seconds' => $activeSeconds,
            'idle_seconds' => 0,
        ]);
    }
}
