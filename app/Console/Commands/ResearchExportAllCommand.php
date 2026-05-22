<?php

namespace App\Console\Commands;

use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;
use ZipArchive;

class ResearchExportAllCommand extends Command
{
    protected $signature = 'research:export-all';

    protected $description = 'Bundle semua data penelitian (CSV) ke 1 zip di storage/exports/.';

    public function handle(): int
    {
        $date = now()->format('Y-m-d');
        $tmpDir = storage_path("app/research-tmp-{$date}");
        $outDir = storage_path('app/private/exports');

        File::ensureDirectoryExists($tmpDir);
        File::ensureDirectoryExists($outDir);

        $this->info('Exporting CSV files...');

        $this->writeCsv("{$tmpDir}/students.csv", $this->studentsRows());
        $this->writeCsv("{$tmpDir}/classrooms.csv", $this->classroomsRows());
        $this->writeCsv("{$tmpDir}/assignment_submissions.csv", $this->assignmentSubmissionsRows());
        $this->writeCsv("{$tmpDir}/exam_sessions.csv", $this->examSessionsRows());
        $this->writeCsv("{$tmpDir}/activity_log.csv", $this->activityLogRows());

        $zipPath = "{$outDir}/research-{$date}.zip";

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Tidak bisa membuka {$zipPath} untuk write.");

            return self::FAILURE;
        }

        foreach (File::files($tmpDir) as $file) {
            $zip->addFile($file->getRealPath(), $file->getFilename());
        }
        $zip->close();

        File::deleteDirectory($tmpDir);

        $this->info("Done. Output: {$zipPath}");

        return self::SUCCESS;
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    private function studentsRows(): iterable
    {
        yield ['id', 'nisn', 'full_name', 'gender', 'birth_date', 'is_active', 'last_login_at'];

        foreach (Student::query()->with('user:id,last_login_at')->cursor() as $student) {
            yield [
                $student->id,
                $student->nisn,
                $student->full_name,
                $student->gender?->value,
                $student->birth_date?->toDateString(),
                $student->is_active ? '1' : '0',
                $student->user?->last_login_at?->toDateTimeString(),
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    private function classroomsRows(): iterable
    {
        yield ['id', 'name', 'grade_level', 'academic_year', 'teacher_id', 'is_active', 'student_count'];

        foreach (Classroom::query()->withCount('students')->cursor() as $classroom) {
            yield [
                $classroom->id,
                $classroom->name,
                $classroom->grade_level,
                $classroom->academic_year,
                $classroom->teacher_id,
                $classroom->is_active ? '1' : '0',
                $classroom->students_count,
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    private function assignmentSubmissionsRows(): iterable
    {
        yield [
            'id', 'assignment_id', 'student_id', 'submitted_at', 'is_late',
            'score', 'graded_at', 'feedback_present', 'created_at',
        ];

        foreach (AssignmentSubmission::query()->cursor() as $sub) {
            yield [
                $sub->id,
                $sub->assignment_id,
                $sub->student_id,
                $sub->submitted_at?->toDateTimeString(),
                $sub->is_late ? '1' : '0',
                $sub->score,
                $sub->graded_at?->toDateTimeString(),
                filled($sub->feedback) ? '1' : '0',
                $sub->created_at?->toDateTimeString(),
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    private function examSessionsRows(): iterable
    {
        yield [
            'id', 'exam_id', 'student_id', 'started_at', 'submitted_at',
            'total_score', 'submission_reason', 'created_at',
        ];

        foreach (ExamSession::query()->cursor() as $session) {
            yield [
                $session->id,
                $session->exam_id,
                $session->student_id,
                $session->started_at?->toDateTimeString(),
                $session->submitted_at?->toDateTimeString(),
                $session->total_score,
                $session->submission_reason,
                $session->created_at?->toDateTimeString(),
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    private function activityLogRows(): iterable
    {
        yield [
            'id', 'log_name', 'event', 'subject_type', 'subject_id',
            'causer_type', 'causer_id', 'description', 'created_at',
        ];

        foreach (Activity::query()->cursor() as $row) {
            yield [
                $row->id,
                $row->log_name,
                $row->event,
                $row->subject_type,
                $row->subject_id,
                $row->causer_type,
                $row->causer_id,
                $row->description,
                $row->created_at?->toDateTimeString(),
            ];
        }
    }

    /**
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    private function writeCsv(string $path, iterable $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            return;
        }
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
}
