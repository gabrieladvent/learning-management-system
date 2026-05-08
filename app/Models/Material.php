<?php

namespace App\Models;

use App\Models\Enums\MaterialTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'type',
        'content',
        'topic',
        'order',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type'         => MaterialTypeEnum::class,
            'published_at' => 'datetime',
            'order'        => 'integer',
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
}
