<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssignmentSubmission extends Model implements HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'content',
        'submitted_at',
        'score',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'score'        => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('submission_files');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
