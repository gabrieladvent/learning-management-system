<?php

namespace App\Models;

use App\Models\Enums\ExamModeEnum;
use App\Models\Enums\ExamStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const DEFAULT_FILE_TYPES = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'];

    protected $fillable = [
        'material_id',
        'title',
        'description',
        'mode',
        'starts_at',
        'duration_minutes',
        'max_score',
        'shuffle_questions',
        'allowed_file_types',
        'max_file_size_mb',
        'status',
        'order',
        'available_from',
        'available_until',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'mode' => ExamModeEnum::class,
            'starts_at' => 'datetime',
            'max_score' => 'decimal:2',
            'shuffle_questions' => 'boolean',
            'allowed_file_types' => 'array',
            'max_file_size_mb' => 'integer',
            'status' => ExamStatusEnum::class,
            'order' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $exam) {
            if (empty($exam->order)) {
                $exam->order = (static::where('material_id', $exam->material_id)->max('order') ?? 0) + 1;
            }
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('order');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ExamSubmission::class);
    }
}
