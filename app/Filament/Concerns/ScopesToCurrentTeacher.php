<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Scoping data "Pengajaran" ke guru yang sedang login.
 *
 * Aturan (satu sumber kebenaran — sebelumnya di-copy-paste di ~20 tempat):
 *  - super_admin           → lihat semua.
 *  - user punya Teacher    → hanya record miliknya (via kolom teacher_id atau
 *                            relasi yang menuju teacher_id).
 *  - selain itu            → tidak lihat apa pun (1 = 0).
 *
 * Dua bentuk scope:
 *  - Model punya kolom teacher_id langsung (mis. ClassroomSubject)   → $relationPath = null
 *  - Scope lewat relasi (mis. Exam → material.classroomSubject)      → $relationPath = 'material.classroomSubject'
 */
trait ScopesToCurrentTeacher
{
    protected static function scopeToCurrentTeacher(Builder $query, ?string $relationPath = null): Builder
    {
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        if ($user?->teacher) {
            $teacherId = $user->teacher->id;

            return $relationPath === null
                ? $query->where('teacher_id', $teacherId)
                : $query->whereHas($relationPath, fn (Builder $q) => $q->where('teacher_id', $teacherId));
        }

        return $query->whereRaw('1 = 0');
    }
}
