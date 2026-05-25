<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningProgressDailyRollup extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id',
        'classroom_subject_id',
        'date',
        'material_seconds',
        'assignment_seconds',
        'exam_seconds',
        'materials_opened',
        'assignments_worked',
        'exams_attempted',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'material_seconds' => 'integer',
            'assignment_seconds' => 'integer',
            'exam_seconds' => 'integer',
            'materials_opened' => 'integer',
            'assignments_worked' => 'integer',
            'exams_attempted' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroomSubject(): BelongsTo
    {
        return $this->belongsTo(ClassroomSubject::class);
    }
}
