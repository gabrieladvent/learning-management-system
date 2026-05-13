<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassroomSubject extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'classroom_id',
        'subject_id',
        'teacher_id',
        'academic_year',
        'semester',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class)->orderBy('order');
    }

    public function assignments(): HasManyThrough
    {
        return $this->hasManyThrough(Assignment::class, Material::class);
    }

    public function exams(): HasManyThrough
    {
        return $this->hasManyThrough(Exam::class, Material::class);
    }
}
