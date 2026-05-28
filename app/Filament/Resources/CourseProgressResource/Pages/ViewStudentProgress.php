<?php

namespace App\Filament\Resources\CourseProgressResource\Pages;

use App\Filament\Resources\CourseProgressResource;
use App\Models\ClassroomSubject;
use App\Models\Student;
use App\Support\StudentProgressReport;
use Filament\Resources\Pages\Page;

/**
 * Level 3 — detail progres 1 siswa pada 1 mapel.
 *
 * Tab: Identitas · Material · Tugas · Ujian · Data Penelitian.
 * Data dihitung sekali di mount() via StudentProgressReport (1 siswa → query kecil).
 */
class ViewStudentProgress extends Page
{
    protected static string $resource = CourseProgressResource::class;

    protected static string $view = 'filament.resources.course-progress.student-detail';

    public ClassroomSubject $courseRecord;

    public Student $studentRecord;

    /** @var array<int, array<string,mixed>> */
    public array $materials = [];

    /** @var array<int, array<string,mixed>> */
    public array $assignments = [];

    /** @var array<int, array<string,mixed>> */
    public array $exams = [];

    /** @var array<string, mixed> */
    public array $research = [];

    public string $activeTab = 'identitas';

    public function mount(string $record, string $student): void
    {
        $this->courseRecord = ClassroomSubject::query()
            ->with(['classroom', 'subject', 'teacher'])
            ->findOrFail($record);

        $this->studentRecord = Student::query()->findOrFail($student);

        $this->authorizeAccess();
        $this->ensureStudentInCourse();

        $report = new StudentProgressReport($this->courseRecord, $this->studentRecord);
        $this->materials = $report->materials();
        $this->assignments = $report->assignments();
        $this->exams = $report->exams();
        $this->research = $report->research();
    }

    public function getTitle(): string
    {
        return "Detail — {$this->studentRecord->full_name}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            CourseProgressResource::getUrl('index') => 'Pantau Progress Belajar',
            CourseProgressResource::getUrl('view', ['record' => $this->courseRecord->id]) => "{$this->courseRecord->classroom?->name} · {$this->courseRecord->subject?->name}",
            $this->studentRecord->full_name,
        ];
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    protected function authorizeAccess(): void
    {
        $user = auth()->user();
        if ($user?->hasRole('super_admin')) {
            return;
        }

        abort_unless(
            $user?->teacher && $this->courseRecord->teacher_id === $user->teacher->id,
            403,
            'Bukan mapel yang Anda ampu.',
        );
    }

    protected function ensureStudentInCourse(): void
    {
        $enrolled = $this->studentRecord->classrooms()
            ->whereKey($this->courseRecord->classroom_id)
            ->exists();

        abort_unless($enrolled, 404, 'Siswa tidak terdaftar di kelas ini.');
    }
}
