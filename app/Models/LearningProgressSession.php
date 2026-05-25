<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LearningProgressSession extends Model
{
    use HasUuids;
    use LogsActivity;

    protected $fillable = [
        'student_id',
        'trackable_type',
        'trackable_id',
        'classroom_subject_id',
        'session_id',
        'started_at',
        'last_seen_at',
        'ended_at',
        'active_seconds',
        'idle_seconds',
        'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
            'active_seconds' => 'integer',
            'idle_seconds' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['active_seconds', 'idle_seconds', 'ended_at', 'end_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
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
