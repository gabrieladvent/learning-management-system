<?php

namespace App\Actions\Student;

use App\Models\Assignment;
use App\Models\Enums\LearningProgressEventType;
use App\Models\Exam;
use App\Models\LearningProgressSession;
use App\Models\Material;
use App\Models\Student;
use App\Support\ActiveTimeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RecordLearningProgress
{
    public const RESULT_RECORDED = 'recorded';

    public const RESULT_OPTED_OUT = 'opted_out';

    private const TRACKABLE_MAP = [
        'material' => Material::class,
        'assignment' => Assignment::class,
        'exam' => Exam::class,
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Student $student, array $payload): string
    {
        $validated = $this->validate($payload);

        if ($student->tracking_opt_out) {
            return self::RESULT_OPTED_OUT;
        }

        $trackable = $this->resolveTrackable($student, $validated['trackable_type'], $validated['trackable_id']);
        $classroomSubjectId = $this->resolveClassroomSubjectId($trackable);

        // Heartbeat upserts saja JANGAN spam activity_log. Hanya koreksi manual
        // super admin (di luar action ini) yang boleh terlog. Lihat §3.2.
        activity()->disableLogging();
        try {
            DB::transaction(function () use ($student, $trackable, $classroomSubjectId, $validated) {
                $this->ingest($student, $trackable, $classroomSubjectId, $validated);
            });
        } finally {
            activity()->enableLogging();
        }

        return self::RESULT_RECORDED;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{session_id:string,trackable_type:string,trackable_id:string,events:array<int,array<string,mixed>>}
     */
    private function validate(array $payload): array
    {
        $maxEvents = (int) config('learning_progress.validation.max_events_per_request', 50);
        $maxDriftMinutes = (int) config('learning_progress.validation.max_clock_drift_minutes', 10);
        $maxGapMs = (int) config('learning_progress.session.max_active_gap_ms', 60_000);

        $sessionId = (string) ($payload['session_id'] ?? '');
        $trackableType = (string) ($payload['trackable_type'] ?? '');
        $trackableId = (string) ($payload['trackable_id'] ?? '');
        $events = $payload['events'] ?? null;

        $errors = [];

        if (! Str::isUuid($sessionId)) {
            $errors['session_id'] = ['session_id harus UUID v4.'];
        }

        if (! array_key_exists($trackableType, self::TRACKABLE_MAP)) {
            $errors['trackable_type'] = ['trackable_type tidak valid.'];
        }

        if (! Str::isUuid($trackableId)) {
            $errors['trackable_id'] = ['trackable_id harus UUID.'];
        }

        if (! is_array($events) || $events === []) {
            $errors['events'] = ['events harus array tidak kosong.'];
        } elseif (count($events) > $maxEvents) {
            $errors['events'] = ["events melebihi batas {$maxEvents}/request."];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $now = CarbonImmutable::now('UTC');
        $cleanEvents = [];

        foreach ($events as $i => $event) {
            $rawType = is_array($event) ? ($event['event'] ?? null) : null;
            $type = LearningProgressEventType::tryFrom((string) $rawType);
            if (! $type) {
                $errors["events.{$i}.event"] = ['event type tidak valid.'];

                continue;
            }

            $rawOccurredAt = is_array($event) ? ($event['occurred_at'] ?? null) : null;
            try {
                $occurredAt = CarbonImmutable::parse((string) $rawOccurredAt)->utc();
            } catch (\Throwable) {
                $errors["events.{$i}.occurred_at"] = ['occurred_at harus ISO 8601.'];

                continue;
            }

            $driftMinutes = abs($now->diffInRealSeconds($occurredAt)) / 60;
            if ($driftMinutes > $maxDriftMinutes) {
                $errors["events.{$i}.occurred_at"] = ["clock drift > {$maxDriftMinutes} menit, batch ditolak."];

                continue;
            }

            $duration = $event['duration_ms'] ?? null;
            if ($duration !== null) {
                $duration = max(0, min($maxGapMs, (int) $duration));
            }

            $meta = $this->sanitizeMeta($event['meta'] ?? null);

            $cleanEvents[] = [
                'event' => $type,
                'occurred_at' => $occurredAt,
                'duration_ms' => $duration,
                'meta' => $meta,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'session_id' => $sessionId,
            'trackable_type' => $trackableType,
            'trackable_id' => $trackableId,
            'events' => $cleanEvents,
        ];
    }

    private function sanitizeMeta(mixed $meta): ?array
    {
        if (! is_array($meta)) {
            return null;
        }

        $out = [];

        if (array_key_exists('scroll_depth', $meta) && is_numeric($meta['scroll_depth'])) {
            $out['scroll_depth'] = max(0.0, min(1.0, (float) $meta['scroll_depth']));
        }

        if (array_key_exists('viewport', $meta) && is_string($meta['viewport'])) {
            $out['viewport'] = mb_substr($meta['viewport'], 0, 32);
        }

        return $out === [] ? null : $out;
    }

    private function resolveTrackable(Student $student, string $type, string $id): Model
    {
        $class = self::TRACKABLE_MAP[$type];

        $query = $class::query()->whereKey($id);
        $trackable = $this->scopeForStudent($query, $type, $student)->first();

        if (! $trackable) {
            $exists = $class::query()->whereKey($id)->exists();
            if ($exists) {
                throw new AccessDeniedHttpException('Trackable bukan milik kelas siswa.');
            }
            throw new NotFoundHttpException('Trackable tidak ditemukan.');
        }

        return $trackable;
    }

    private function scopeForStudent(Builder $query, string $type, Student $student): Builder
    {
        $studentRelation = fn (Builder $q) => $q->whereKey($student->id);

        return match ($type) {
            'material' => $query->whereHas('classroomSubject.classroom.students', $studentRelation),
            'assignment', 'exam' => $query->whereHas('material.classroomSubject.classroom.students', $studentRelation),
        };
    }

    private function resolveClassroomSubjectId(Model $trackable): string
    {
        return match (true) {
            $trackable instanceof Material => (string) $trackable->classroom_subject_id,
            $trackable instanceof Assignment, $trackable instanceof Exam => (string) $trackable->material->classroom_subject_id,
            default => throw new \LogicException('Unknown trackable type'),
        };
    }

    /**
     * @param  array{session_id:string,trackable_type:string,trackable_id:string,events:array<int,array<string,mixed>>}  $payload
     */
    private function ingest(Student $student, Model $trackable, string $classroomSubjectId, array $payload): void
    {
        $now = CarbonImmutable::now('UTC');
        $clientSessionId = $payload['session_id'];
        $trackableType = $trackable->getMorphClass();
        $trackableId = (string) $trackable->getKey();
        $maxGapMs = (int) config('learning_progress.session.max_active_gap_ms', 60_000);

        // Sort the batch ASC by occurred_at (network reorder + beacon flush can scramble).
        $sorted = $payload['events'];
        usort($sorted, fn (array $a, array $b) => $a['occurred_at'] <=> $b['occurred_at']);

        // SELECT 1: existing session row for (student, trackable, client_session_id).
        $existing = LearningProgressSession::query()
            ->where('student_id', $student->id)
            ->where('trackable_type', $trackableType)
            ->where('trackable_id', $trackableId)
            ->where('session_id', $clientSessionId)
            ->first();

        $isComeback = $existing !== null && $existing->ended_at !== null;
        $isContinuation = $existing !== null && $existing->ended_at === null;

        $sessionIdToStore = $isComeback ? (string) Str::uuid() : $clientSessionId;
        $isReplacement = $sessionIdToStore !== $clientSessionId;

        // Compute incremental delta (algoritma murni di ActiveTimeCalculator).
        $calculator = new ActiveTimeCalculator;
        if ($isContinuation) {
            // SELECT 2 (continuation only): fetch state anchor — last persisted event for this session.
            $anchor = $this->fetchStateAnchor($existing);
            [$deltaActiveMs, $deltaIdleMs] = $calculator->computeDelta($anchor, $sorted, $maxGapMs);
        } else {
            [$deltaActiveMs, $deltaIdleMs] = $calculator->computeDelta(null, $sorted, $maxGapMs);
        }

        $lastOccurred = end($sorted)['occurred_at'];
        $hasClose = $calculator->hasClose($sorted);

        // INSERT events — 1 query.
        $eventRows = $this->buildEventRows(
            $sorted,
            $student->id,
            $trackableType,
            $trackableId,
            $classroomSubjectId,
            $sessionIdToStore,
            $now,
            $isReplacement ? $clientSessionId : null,
        );
        DB::table('learning_progress_events')->insert($eventRows);

        // INSERT or UPDATE session — 1 query.
        if ($isContinuation) {
            $existing->active_seconds = (int) $existing->active_seconds + intdiv($deltaActiveMs, 1000);
            $existing->idle_seconds = (int) $existing->idle_seconds + intdiv($deltaIdleMs, 1000);
            $existing->last_seen_at = $lastOccurred->toDateTimeString();
            if ($hasClose && $existing->ended_at === null) {
                $existing->ended_at = $lastOccurred->toDateTimeString();
                $existing->end_reason = 'closed';
            }
            $existing->save();
        } else {
            /** @var CarbonImmutable $firstOccurred */
            $firstOccurred = $sorted[0]['occurred_at'];
            LearningProgressSession::create([
                'student_id' => $student->id,
                'trackable_type' => $trackableType,
                'trackable_id' => $trackableId,
                'classroom_subject_id' => $classroomSubjectId,
                'session_id' => $sessionIdToStore,
                'started_at' => $firstOccurred->toDateTimeString(),
                'last_seen_at' => $lastOccurred->toDateTimeString(),
                'active_seconds' => intdiv($deltaActiveMs, 1000),
                'idle_seconds' => intdiv($deltaIdleMs, 1000),
                'ended_at' => $hasClose ? $lastOccurred->toDateTimeString() : null,
                'end_reason' => $hasClose ? 'closed' : null,
            ]);
        }
    }

    /**
     * Find the last persisted event (and walk back through heartbeat/close events to find
     * the most recent state-bearing event for continuity).
     *
     * @return array{occurred_at:CarbonImmutable, state:string}|null
     */
    private function fetchStateAnchor(LearningProgressSession $session): ?array
    {
        $row = DB::table('learning_progress_events')
            ->where('student_id', $session->student_id)
            ->where('trackable_type', $session->trackable_type)
            ->where('trackable_id', $session->trackable_id)
            ->where('session_id', $session->session_id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first(['event', 'occurred_at']);

        if (! $row) {
            return null;
        }

        // Determine state — walk back through heartbeat to find a state-bearing event.
        $anchorOccurredAt = CarbonImmutable::parse($row->occurred_at, 'UTC');
        $type = LearningProgressEventType::from($row->event);

        if ($type->isActiveState() || $type->isIdleState()) {
            return [
                'occurred_at' => $anchorOccurredAt,
                'state' => $type->isActiveState() ? 'active' : 'idle',
            ];
        }

        // Walk back to find the last state-bearing event.
        $stateRow = DB::table('learning_progress_events')
            ->where('student_id', $session->student_id)
            ->where('trackable_type', $session->trackable_type)
            ->where('trackable_id', $session->trackable_id)
            ->where('session_id', $session->session_id)
            ->whereIn('event', ['open', 'focus', 'blur', 'idle'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first(['event']);

        $state = 'active';
        if ($stateRow) {
            $stateType = LearningProgressEventType::from($stateRow->event);
            $state = $stateType->isIdleState() ? 'idle' : 'active';
        }

        return [
            'occurred_at' => $anchorOccurredAt,
            'state' => $state,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $sorted
     * @return array<int,array<string,mixed>>
     */
    private function buildEventRows(
        array $sorted,
        string $studentId,
        string $trackableType,
        string $trackableId,
        string $classroomSubjectId,
        string $sessionIdToStore,
        CarbonImmutable $now,
        ?string $originalClientSessionId,
    ): array {
        $rows = [];

        foreach ($sorted as $index => $event) {
            /** @var CarbonImmutable $occurredAt */
            $occurredAt = $event['occurred_at'];
            $meta = $event['meta'];

            if ($originalClientSessionId !== null && $index === 0) {
                $meta = $meta ?? [];
                $meta['original_client_session_id'] = $originalClientSessionId;
            }

            $rows[] = [
                'student_id' => $studentId,
                'trackable_type' => $trackableType,
                'trackable_id' => $trackableId,
                'classroom_subject_id' => $classroomSubjectId,
                'session_id' => $sessionIdToStore,
                'event' => $event['event']->value,
                'occurred_at' => $occurredAt->toDateTimeString(),
                'received_at' => $now->format('Y-m-d H:i:s.v'),
                'duration_ms' => $event['duration_ms'],
                'meta' => $meta ? json_encode($meta) : null,
                'created_at' => $now->toDateTimeString(),
            ];
        }

        return $rows;
    }

    public function statusToHttp(string $result): int
    {
        return match ($result) {
            self::RESULT_RECORDED, self::RESULT_OPTED_OUT => Response::HTTP_NO_CONTENT,
            default => Response::HTTP_NO_CONTENT,
        };
    }
}
