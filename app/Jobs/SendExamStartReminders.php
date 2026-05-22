<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Notifications\StudentDeadlineReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class SendExamStartReminders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Kirim reminder untuk exam yang `starts_at`-nya antara now() dan now()+1h.
     * Hanya kirim ke siswa yang belum submit/selesai session.
     */
    public function handle(): void
    {
        $now = Carbon::now();
        $oneHourLater = $now->copy()->addHour();

        $exams = Exam::query()
            ->where('is_published', true)
            ->whereBetween('starts_at', [$now, $oneHourLater])
            ->with('material.classroomSubject.classroom.students')
            ->get();

        foreach ($exams as $exam) {
            $classroomStudents = $exam->material?->classroomSubject?->classroom?->students;

            if (! $classroomStudents || $classroomStudents->isEmpty()) {
                continue;
            }

            $studentsToNotify = $classroomStudents->reject(function ($student) use ($exam) {
                return $exam->sessions()
                    ->where('student_id', $student->id)
                    ->whereNotNull('submitted_at')
                    ->exists()
                    || $exam->submissions()
                        ->where('student_id', $student->id)
                        ->whereNotNull('submitted_at')
                        ->exists();
            });

            if ($studentsToNotify->isEmpty()) {
                continue;
            }

            Notification::send($studentsToNotify, new StudentDeadlineReminder($exam));
        }
    }
}
