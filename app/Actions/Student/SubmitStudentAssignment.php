<?php

namespace App\Actions\Student;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubmitStudentAssignment
{
    /**
     * Validasi & simpan submission siswa.
     *
     * Aturan file (server-side, source-of-truth):
     * - extension harus ada di assignment.allowed_file_types
     * - size <= assignment.max_file_size_mb (MB)
     *
     * Aturan lain:
     * - Tidak boleh setelah deadline
     * - Siswa harus enrolled di classroom course
     * - Material & Assignment harus visible (is_published + range available)
     *
     * @param  array<int, UploadedFile>  $newFiles  File yang baru di-upload
     * @param  array<int, string>  $removedFileIds  UUID media existing yang dihapus saat edit
     */
    public function handle(
        Student $student,
        string $materialId,
        string $assignmentId,
        ?string $content,
        array $newFiles,
        array $removedFileIds,
    ): AssignmentSubmission {
        $material = $this->resolveMaterial($student, $materialId);
        $assignment = $this->resolveAssignment($material, $assignmentId);

        if ($assignment->deadline && now()->greaterThan($assignment->deadline)) {
            throw ValidationException::withMessages([
                'deadline' => 'Tugas sudah melewati deadline, tidak bisa dikumpulkan lagi.',
            ]);
        }

        $this->validateFiles($assignment, $newFiles);

        return DB::transaction(function () use ($assignment, $student, $content, $newFiles, $removedFileIds) {
            // withTrashed: unique(assignment_id, student_id) tidak respect soft-delete di DB level.
            // Tanpa ini, submission yang pernah dihapus admin → siswa tidak bisa submit lagi (1062).
            $submission = AssignmentSubmission::withTrashed()
                ->firstOrNew([
                    'assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                ]);

            // Defense in depth: kalau guru sudah menilai, kunci. Frontend juga sudah hide tombol Edit.
            if ($submission->exists && $submission->score !== null) {
                throw ValidationException::withMessages([
                    'submission' => 'Tugas sudah dinilai oleh guru. Tidak bisa diedit lagi.',
                ]);
            }

            // Tandai sebagai EDIT sebelum restore/save → cek state ASLI dari DB.
            // First submit (model baru) atau restore dari soft-delete → bukan edit.
            $isEdit = $submission->exists && ! $submission->trashed();

            if ($submission->trashed()) {
                $submission->restore();
            }

            $submission->content = $content;
            $submission->submitted_at = now();
            if ($isEdit) {
                $submission->last_edited_at = now();
            }
            $submission->save();

            if ($removedFileIds !== []) {
                $submission->getMedia('submission_files')
                    ->whereIn('uuid', $removedFileIds)
                    ->each(fn ($media) => $media->delete());
            }

            foreach ($newFiles as $file) {
                $submission->addMedia($file)->toMediaCollection('submission_files');
            }

            return $submission->fresh();
        });
    }

    private function resolveMaterial(Student $student, string $materialId): Material
    {
        $material = Material::query()
            ->whereKey($materialId)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->whereHas('classroomSubject.classroom.students', fn (Builder $q) => $q->whereKey($student->id))
            ->first();

        if (! $material) {
            throw new NotFoundHttpException('Materi tidak ditemukan atau belum tersedia.');
        }

        return $material;
    }

    private function resolveAssignment(Material $material, string $assignmentId): Assignment
    {
        $assignment = $material->assignments()
            ->whereKey($assignmentId)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->first();

        if (! $assignment) {
            throw new NotFoundHttpException('Tugas tidak ditemukan atau belum tersedia.');
        }

        return $assignment;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function validateFiles(Assignment $assignment, array $files): void
    {
        if ($files === []) {
            return;
        }

        $allowed = array_map('strtolower', $assignment->allowed_file_types ?? Assignment::DEFAULT_FILE_TYPES);
        $maxBytes = ($assignment->max_file_size_mb ?? 10) * 1024 * 1024;

        $errors = [];

        foreach ($files as $i => $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                $errors["files.{$i}"] = 'File tidak valid.';

                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
            if (! in_array($ext, $allowed, true)) {
                $errors["files.{$i}"] = "Tipe file .{$ext} tidak diizinkan. Diizinkan: ".implode(', ', $allowed).'.';

                continue;
            }

            if ($file->getSize() > $maxBytes) {
                $errors["files.{$i}"] = 'Ukuran file melebihi batas '.$assignment->max_file_size_mb.' MB.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
