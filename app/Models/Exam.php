<?php

namespace App\Models;

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

    protected $fillable = [
        'material_id',
        'title',
        'description',
        'starts_at',
        'duration_minutes',
        'shuffle_questions',
        'status',
        'order',
        'available_from',
        'available_until',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'shuffle_questions' => 'boolean',
            'status' => ExamStatusEnum::class,
            'order' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'is_published' => 'boolean',
        ];
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
}
