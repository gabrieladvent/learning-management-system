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
        'last_edited_at',
        'score',
        'feedback',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'last_edited_at' => 'datetime',
            'score' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    /**
     * Auto-set graded_at saat guru pertama kali mengisi score.
     * Tidak menggeser ulang kalau score sudah pernah di-set & graded_at sudah ada
     * (mis. guru cuma edit feedback) — supaya log activity tetap akurat.
     */
    protected static function booted(): void
    {
        static::saving(function (self $submission) {
            if ($submission->isDirty('score') && $submission->score !== null && $submission->graded_at === null) {
                $submission->graded_at = now();
            }
        });
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
