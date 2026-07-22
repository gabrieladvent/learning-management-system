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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendExamStartReminders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Job cron idempoten — kalau gagal di tengah, jangan retry membabi buta yang
     * bisa mengirim ulang ke siswa yang sudah dapat.
     */
    public int $tries = 1;

    public int $timeout = 120;

    /**
     * Kirim reminder untuk exam yang `starts_at`-nya antara now() dan now()+1h.
     * Hanya kirim ke siswa yang belum submit/selesai DAN belum pernah di-reminder
     * (dedup via tabel exam_start_reminders — job jalan tiap 15 menit sehingga
     * satu exam bisa masuk window beberapa kali).
     */
    public function handle(): void
    {
        $now = Carbon::now();
        $oneHourLater = $now->copy()->addHour();

        $exams = Exam::query()
            ->where('is_published', true)
            ->whereBetween('starts_at', [$now, $oneHourLater])
            ->with('material.classroomSubject.classroom.students:id')
            ->get();

        foreach ($exams as $exam) {
            $classroomStudents = $exam->material?->classroomSubject?->classroom?->students;

            if (! $classroomStudents || $classroomStudents->isEmpty()) {
                continue;
            }

            // Pluck sekali per exam (hindari N+1 exists() per siswa):
            //  - siswa yang sudah submit online_quiz
            //  - siswa yang sudah submit mode submission
            //  - siswa yang sudah pernah di-reminder untuk exam ini
            $submittedSessionIds = $exam->sessions()->whereNotNull('submitted_at')->pluck('student_id');
            $submittedSubmissionIds = $exam->submissions()->whereNotNull('submitted_at')->pluck('student_id');
            $alreadyRemindedIds = DB::table('exam_start_reminders')
                ->where('exam_id', $exam->id)
                ->pluck('student_id');

            $excluded = $submittedSessionIds
                ->merge($submittedSubmissionIds)
                ->merge($alreadyRemindedIds)
                ->unique()
                ->flip();

            $studentsToNotify = $classroomStudents->reject(
                fn ($student) => $excluded->has($student->id)
            );

            if ($studentsToNotify->isEmpty()) {
                continue;
            }

            Notification::send($studentsToNotify, new StudentDeadlineReminder($exam));

            // Catat log dedup. insertOrIgnore + unique(exam_id, student_id) aman
            // walau ada dua run yang berebut (idempoten).
            $rows = $studentsToNotify->map(fn ($student) => [
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'reminded_at' => $now,
            ])->all();

            DB::table('exam_start_reminders')->insertOrIgnore($rows);
        }
    }
}
