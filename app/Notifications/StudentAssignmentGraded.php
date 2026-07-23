<?php

namespace App\Notifications;

use App\Models\AssignmentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentAssignmentGraded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AssignmentSubmission $submission) {}

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
        $assignment = $this->submission->assignment;
        $material = $assignment?->material;
        $maxScore = $assignment?->max_score;

        return [
            'type' => 'assignment_graded',
            'title' => 'Tugas dinilai: '.($assignment?->title ?? 'Tugas'),
            'body' => $maxScore
                ? sprintf('Nilai: %s / %s', $this->submission->score, $maxScore)
                : sprintf('Nilai: %s', $this->submission->score),
            'submission_id' => $this->submission->id,
            'assignment_id' => $assignment?->id,
            'material_id' => $material?->id,
            'score' => $this->submission->score,
            'url' => ($material && $assignment)
                ? route('student.assignments.show', [
                    'material' => $material->id,
                    'assignment' => $assignment->id,
                ])
                : null,
        ];
    }
}
