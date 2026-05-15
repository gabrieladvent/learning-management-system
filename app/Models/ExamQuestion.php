<?php

namespace App\Models;

use App\Models\Enums\QuestionTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ExamQuestion extends Model implements HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    protected $fillable = [
        'exam_id',
        'type',
        'question',
        'options',
        'correct_answer',
        'score',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionTypeEnum::class,
            'options' => 'array',
            'score' => 'decimal:2',
            'order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $question) {
            if (empty($question->order)) {
                $question->order = (static::where('exam_id', $question->exam_id)->max('order') ?? 0) + 1;
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('question_files');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }
}
