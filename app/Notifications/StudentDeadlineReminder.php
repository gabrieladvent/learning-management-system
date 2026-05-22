<?php

namespace App\Notifications;

use App\Models\Assignment;
use App\Models\Exam;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentDeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Reminder untuk assignment yang deadline-nya 24 jam lagi
     * atau exam yang akan mulai 1 jam lagi.
     */
    public function __construct(public Assignment|Exam $target) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        if ($this->target instanceof Assignment) {
            return $this->arrayForAssignment($this->target);
        }

        return $this->arrayForExam($this->target);
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayForAssignment(Assignment $assignment): array
    {
        $material = $assignment->material;
        $subjectName = $material?->classroomSubject?->subject?->name;

        return [
            'type' => 'assignment_deadline_reminder',
            'title' => 'Deadline besok: '.$assignment->title,
            'body' => $subjectName
                ? "Mata pelajaran: {$subjectName}. Jangan lupa kumpulkan."
                : 'Jangan lupa kumpulkan tugas.',
            'assignment_id' => $assignment->id,
            'material_id' => $material?->id,
            'deadline' => $assignment->deadline?->toIso8601String(),
            'url' => $material
                ? route('student.assignments.show', [
                    'material' => $material->id,
                    'assignment' => $assignment->id,
                ])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayForExam(Exam $exam): array
    {
        $material = $exam->material;
        $subjectName = $material?->classroomSubject?->subject?->name;

        return [
            'type' => 'exam_start_reminder',
            'title' => 'Ujian sebentar lagi: '.$exam->title,
            'body' => $subjectName
                ? "Mata pelajaran: {$subjectName}. Ujian akan mulai dalam 1 jam."
                : 'Ujian akan mulai dalam 1 jam.',
            'exam_id' => $exam->id,
            'material_id' => $material?->id,
            'starts_at' => $exam->starts_at?->toIso8601String(),
            'url' => $material
                ? route('student.exams.show', [
                    'material' => $material->id,
                    'exam' => $exam->id,
                ])
                : null,
        ];
    }
}
