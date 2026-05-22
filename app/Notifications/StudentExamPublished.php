<?php

namespace App\Notifications;

use App\Models\Exam;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentExamPublished extends Notification
{
    use Queueable;

    public function __construct(public Exam $exam) {}

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
        $material = $this->exam->material;
        $subjectName = $material?->classroomSubject?->subject?->name;

        return [
            'type' => 'exam_published',
            'title' => 'Ujian baru: '.$this->exam->title,
            'body' => $subjectName ? "Mata pelajaran: {$subjectName}" : null,
            'exam_id' => $this->exam->id,
            'material_id' => $material?->id,
            'starts_at' => $this->exam->starts_at?->toIso8601String(),
            'available_from' => $this->exam->available_from?->toIso8601String(),
            'available_until' => $this->exam->available_until?->toIso8601String(),
            'url' => $material
                ? route('student.exams.show', [
                    'material' => $material->id,
                    'exam' => $this->exam->id,
                ])
                : null,
        ];
    }
}
