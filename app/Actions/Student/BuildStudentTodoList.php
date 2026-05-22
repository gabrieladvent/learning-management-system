<?php

namespace App\Actions\Student;

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BuildStudentTodoList
{
    /**
     * Semua tugas/ujian aktif siswa, dibagi 3:
     *  - today: yang batasnya hari ini (untuk daftar inline dashboard)
     *  - this_week: yang batasnya dalam 7 hari (termasuk hari ini & overdue masih dalam window)
     *  - later: sisanya (deadline > 7 hari, exam upcoming > 7 hari, atau tanpa batas waktu)
     *
     * Badge `count_this_week` = jumlah item "this_week" yang harus dikerjakan minggu ini.
     *
     * @return array{
     *     today: array<int, array<string, mixed>>,
     *     this_week: array<int, array<string, mixed>>,
     *     later: array<int, array<string, mixed>>,
     *     count_this_week: int,
     * }
     */
    public function handle(Student $student): array
    {
        $classroomIds = $student->classrooms()->pluck('classrooms.id')->all();

        $now = Carbon::now();
        $weekEnd = $now->copy()->addDays(7)->endOfDay();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $assignments = Assignment::query()
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', $now))
            ->where(fn (Builder $q) => $q->whereNull('deadline')->orWhere('deadline', '>=', $now))
            ->whereHas('material.classroomSubject.classroom', fn (Builder $q) => $q->whereIn('id', $classroomIds))
            ->whereDoesntHave('submissions', fn (Builder $q) => $q->where('student_id', $student->id)->whereNotNull('submitted_at'))
            ->with('material.classroomSubject.subject')
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->limit(100)
            ->get();

        $assignmentItems = $assignments->map(function (Assignment $assignment) use ($weekEnd, $todayStart, $todayEnd) {
            $material = $assignment->material;
            $deadline = $assignment->deadline;
            $isToday = $deadline !== null
                && $deadline->greaterThanOrEqualTo($todayStart)
                && $deadline->lessThanOrEqualTo($todayEnd);
            $isWithinWeek = $deadline !== null && $deadline->lessThanOrEqualTo($weekEnd);

            return [
                'kind' => 'assignment',
                'state' => 'pending',
                'id' => $assignment->id,
                'title' => $assignment->title,
                'subject_name' => $material?->classroomSubject?->subject?->name,
                'deadline' => $deadline?->toIso8601String(),
                'starts_at' => null,
                'available_from' => null,
                'available_until' => null,
                'is_today' => $isToday,
                'is_within_week' => $isWithinWeek,
                'url' => $material
                    ? route('student.assignments.show', [
                        'material' => $material->id,
                        'assignment' => $assignment->id,
                    ])
                    : null,
            ];
        });

        $exams = Exam::query()
            ->where('is_published', true)
            ->whereHas('material.classroomSubject.classroom', fn (Builder $q) => $q->whereIn('id', $classroomIds))
            ->where(function (Builder $q) use ($now) {
                $q->whereNotNull('starts_at')
                    ->where('starts_at', '>=', $now)
                    ->orWhere(function (Builder $q2) use ($now) {
                        $q2->whereNotNull('available_from')
                            ->whereNotNull('available_until')
                            ->where('available_from', '<=', $now)
                            ->where('available_until', '>=', $now);
                    });
            })
            ->whereDoesntHave('sessions', fn (Builder $q) => $q->where('student_id', $student->id)->whereNotNull('submitted_at'))
            ->whereDoesntHave('submissions', fn (Builder $q) => $q->where('student_id', $student->id)->whereNotNull('submitted_at'))
            ->with('material.classroomSubject.subject')
            ->orderByRaw('starts_at IS NULL, starts_at ASC')
            ->limit(100)
            ->get();

        $examItems = $exams->map(function (Exam $exam) use ($now, $weekEnd, $todayStart, $todayEnd) {
            $material = $exam->material;
            $isAvailableNow = $exam->available_from !== null
                && $exam->available_until !== null
                && $exam->available_from->lessThanOrEqualTo($now)
                && $exam->available_until->greaterThanOrEqualTo($now);
            $startsToday = $exam->starts_at !== null
                && $exam->starts_at->greaterThanOrEqualTo($todayStart)
                && $exam->starts_at->lessThanOrEqualTo($todayEnd);
            $endsToday = $exam->available_until !== null
                && $exam->available_until->greaterThanOrEqualTo($todayStart)
                && $exam->available_until->lessThanOrEqualTo($todayEnd);
            $isToday = $startsToday || ($isAvailableNow && $endsToday);
            $startsWithinWeek = $exam->starts_at !== null && $exam->starts_at->lessThanOrEqualTo($weekEnd);
            $isWithinWeek = $isAvailableNow || $startsWithinWeek;

            return [
                'kind' => 'exam',
                'state' => $isAvailableNow ? 'available' : 'upcoming',
                'id' => $exam->id,
                'title' => $exam->title,
                'subject_name' => $material?->classroomSubject?->subject?->name,
                'deadline' => null,
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'available_from' => $exam->available_from?->toIso8601String(),
                'available_until' => $exam->available_until?->toIso8601String(),
                'is_today' => $isToday,
                'is_within_week' => $isWithinWeek,
                'url' => $material
                    ? route('student.exams.show', [
                        'material' => $material->id,
                        'exam' => $exam->id,
                    ])
                    : null,
            ];
        });

        $all = $assignmentItems->concat($examItems)->values()->all();

        $today = array_values(array_filter($all, fn ($item) => $item['is_today']));
        $thisWeek = array_values(array_filter($all, fn ($item) => $item['is_within_week']));
        $later = array_values(array_filter($all, fn ($item) => ! $item['is_within_week']));

        return [
            'today' => $today,
            'this_week' => $thisWeek,
            'later' => $later,
            'count_this_week' => count($thisWeek),
        ];
    }
}
