<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentAssignmentPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Assignment $assignment) {}

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
        $material = $this->assignment->material;
        $subjectName = $material?->classroomSubject?->subject?->name;

        return [
            'type' => 'assignment_published',
            'title' => 'Tugas baru: '.$this->assignment->title,
            'body' => $subjectName ? "Mata pelajaran: {$subjectName}" : null,
            'assignment_id' => $this->assignment->id,
            'material_id' => $material?->id,
            'deadline' => $this->assignment->deadline?->toIso8601String(),
            'url' => $material
                ? route('student.assignments.show', [
                    'material' => $material->id,
                    'assignment' => $this->assignment->id,
                ])
                : null,
        ];
    }
}
