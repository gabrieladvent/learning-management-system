<?php

namespace App\Actions\Student;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Material;
use App\Models\Student;
use App\Notifications\TeacherSubmissionAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubmitExamSubmission
{
    /**
     * Simpan submission ujian mode `submission` (text + file + link).
     *
     * Aturan file (server-side, source-of-truth):
     *  - extension harus ada di exam.allowed_file_types
     *  - size <= exam.max_file_size_mb (MB)
     *
     * Aturan window:
     *  - Tidak boleh sebelum `starts_at`
     *  - Tidak boleh setelah `available_until` (kalau ada) — itu batas paling longgar
     *
     * @param  array<int, UploadedFile>  $newFiles
     * @param  array<int, string>  $removedFileIds
     */
    public function handle(
        Student $student,
        string $materialId,
        string $examId,
        ?string $content,
        ?string $linkUrl,
        array $newFiles,
        array $removedFileIds,
    ): ExamSubmission {
        $material = $this->resolveMaterial($student, $materialId);
        $exam = $this->resolveExam($material, $examId);

        if ($exam->mode->value !== 'submission') {
            throw ValidationException::withMessages([
                'mode' => 'Ujian ini bukan mode submission.',
            ]);
        }

        if ($exam->starts_at && now()->lessThan($exam->starts_at)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Ujian belum dimulai. Tidak bisa mengumpulkan sekarang.',
            ]);
        }

        if ($exam->available_until && now()->greaterThan($exam->available_until)) {
            throw ValidationException::withMessages([
                'available_until' => 'Window pengumpulan ujian sudah ditutup.',
            ]);
        }

        $this->validateFiles($exam, $newFiles);

        $submission = DB::transaction(function () use ($exam, $student, $content, $linkUrl, $newFiles, $removedFileIds) {
            $submission = ExamSubmission::withTrashed()
                ->firstOrNew([
                    'exam_id' => $exam->id,
                    'student_id' => $student->id,
                ]);

            if ($submission->exists && $submission->score !== null) {
                throw ValidationException::withMessages([
                    'submission' => 'Ujian sudah dinilai oleh guru. Tidak bisa diedit lagi.',
                ]);
            }

            if ($submission->trashed()) {
                $submission->restore();
            }

            $submission->content = $content;
            $submission->link_url = $linkUrl;
            $submission->submitted_at = now();
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

        TeacherSubmissionAlert::forExamSubmission($submission->load('exam.material.classroomSubject.teacher', 'student'));

        return $submission;
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

    private function resolveExam(Material $material, string $examId): Exam
    {
        $exam = $material->exams()
            ->whereKey($examId)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->first();

        if (! $exam) {
            throw new NotFoundHttpException('Ujian tidak ditemukan atau belum tersedia.');
        }

        return $exam;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function validateFiles(Exam $exam, array $files): void
    {
        if ($files === []) {
            return;
        }

        $allowed = array_map('strtolower', $exam->allowed_file_types ?? Exam::DEFAULT_FILE_TYPES);
        $maxBytes = ($exam->max_file_size_mb ?? 10) * 1024 * 1024;

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
                $errors["files.{$i}"] = 'Ukuran file melebihi batas '.$exam->max_file_size_mb.' MB.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
