<?php

namespace App\Models;

use App\Notifications\StudentAssignmentGraded;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssignmentSubmission extends Model implements HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'content',
        'link_url',
        'submitted_at',
        'score',
        'feedback',
        'graded_at',
        'is_late',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['content', 'link_url', 'submitted_at', 'score', 'feedback', 'graded_at', 'is_late'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
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

        static::saved(function (self $submission) {
            $wasScored = $submission->getOriginal('score') !== null;
            $isScoredNow = $submission->score !== null;

            if (! $wasScored && $isScoredNow) {
                $submission->loadMissing('student');

                if ($submission->student) {
                    $submission->student->notify(new StudentAssignmentGraded($submission));
                }
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
