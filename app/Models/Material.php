<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Material extends Model implements HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'classroom_subject_id',
        'title',
        'description',
        'content',
        'link_url',
        'topic',
        'order',
        'available_from',
        'available_until',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'is_published' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('material_files');
    }

    public function classroomSubject(): BelongsTo
    {
        return $this->belongsTo(ClassroomSubject::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->orderBy('order');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class)->orderBy('order');
    }
}
