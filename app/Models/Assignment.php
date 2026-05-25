<?php

namespace App\Models;

use App\Models\Concerns\HasLearningProgress;
use App\Notifications\StudentAssignmentPublished;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Notification;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Assignment extends Model implements HasMedia
{
    use HasFactory;
    use HasLearningProgress;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    public const DEFAULT_FILE_TYPES = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'];

    protected $fillable = [
        'material_id',
        'title',
        'description',
        'deadline',
        'max_score',
        'order',
        'allowed_file_types',
        'max_file_size_mb',
        'available_from',
        'available_until',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'max_score' => 'decimal:2',
            'order' => 'integer',
            'allowed_file_types' => 'array',
            'max_file_size_mb' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $assignment) {
            if (empty($assignment->order)) {
                $assignment->order = (static::where('material_id', $assignment->material_id)->max('order') ?? 0) + 1;
            }
        });

        static::saved(function (self $assignment) {
            $wasPublished = (bool) ($assignment->getOriginal('is_published') ?? false);
            $isPublished = (bool) $assignment->is_published;

            if (! $wasPublished && $isPublished) {
                $students = $assignment->material?->classroomSubject?->classroom?->students;

                if ($students && $students->isNotEmpty()) {
                    Notification::send($students, new StudentAssignmentPublished($assignment));
                }
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('assignment_attachments');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
