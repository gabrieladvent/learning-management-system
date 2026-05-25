<?php

namespace App\Models;

use App\Models\Enums\LearningProgressEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LearningProgressEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'trackable_type',
        'trackable_id',
        'classroom_subject_id',
        'session_id',
        'event',
        'occurred_at',
        'received_at',
        'duration_ms',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event' => LearningProgressEventType::class,
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'duration_ms' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
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

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }
}
