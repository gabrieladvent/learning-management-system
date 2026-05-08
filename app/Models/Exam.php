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
        'classroom_subject_id',
        'title',
        'description',
        'starts_at',
        'duration_minutes',
        'shuffle_questions',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'         => 'datetime',
            'shuffle_questions' => 'boolean',
            'status'            => ExamStatusEnum::class,
        ];
    }

    public function classroomSubject(): BelongsTo
    {
        return $this->belongsTo(ClassroomSubject::class);
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
