<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Notifications\StudentDeadlineReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class SendAssignmentDeadlineReminders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Kirim reminder untuk assignment yang deadline-nya antara now() dan now()+24h.
     * Hanya kirim ke siswa yang belum submit.
     */
    public function handle(): void
    {
        $now = Carbon::now();
        $tomorrow = $now->copy()->addDay();

        $assignments = Assignment::query()
            ->where('is_published', true)
            ->whereBetween('deadline', [$now, $tomorrow])
            ->with('material.classroomSubject.classroom.students')
            ->get();

        foreach ($assignments as $assignment) {
            $classroomStudents = $assignment->material?->classroomSubject?->classroom?->students;

            if (! $classroomStudents || $classroomStudents->isEmpty()) {
                continue;
            }

            $studentsToNotify = $classroomStudents->reject(
                fn ($student) => $assignment->submissions()
                    ->where('student_id', $student->id)
                    ->whereNotNull('submitted_at')
                    ->exists()
            );

            if ($studentsToNotify->isEmpty()) {
                continue;
            }

            Notification::send($studentsToNotify, new StudentDeadlineReminder($assignment));
        }
    }
}
