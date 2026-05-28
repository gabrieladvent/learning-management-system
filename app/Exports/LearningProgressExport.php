<?php

namespace App\Exports;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassroomSubject;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\LearningProgressDailyRollup;
use App\Models\LearningProgressSession;
use App\Models\Material;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Implementasi docs/11 §14 — Data Dictionary Export.
 * 4 sheet wajib + manifest. Mode raw (identitas penuh) atau anonim (HMAC pseudo_id).
 */
class LearningProgressExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private readonly ClassroomSubject $classroomSubject,
        private readonly string $mode = 'raw', // 'raw' | 'anonim'
    ) {}

    public function sheets(): array
    {
        return [
            new LearningProgressStudentsSheet($this->classroomSubject, $this->mode),
            new LearningProgressDailyRollupsSheet($this->classroomSubject, $this->mode),
            new LearningProgressSubmissionsSheet($this->classroomSubject, $this->mode),
            new LearningProgressExamSessionsSheet($this->classroomSubject, $this->mode),
            new LearningProgressManifestSheet($this->classroomSubject, $this->mode),
        ];
    }

    public static function pseudoId(string $studentId): string
    {
        $secretKey = (string) config('learning_progress.export.pseudo_secret_env', 'LEARNING_PROGRESS_PSEUDO_SECRET');
        $secret = (string) env($secretKey);
        if ($secret === '') {
            // Fallback ke APP_KEY supaya export anonim tetap deterministik di dev
            // (peneliti yang serius wajib set LEARNING_PROGRESS_PSEUDO_SECRET di prod).
            $secret = (string) config('app.key');
        }

        return hash_hmac('sha256', $studentId, $secret);
    }
}

/**
 * Sheet `students`: 1 row per (siswa × classroom_subject ini).
 */
class LearningProgressStudentsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly ClassroomSubject $cs,
        private readonly string $mode,
    ) {}

    public function title(): string
    {
        return 'students';
    }

    public function headings(): array
    {
        $idLabel = $this->mode === 'anonim' ? 'student_pseudo_id' : 'student_id';

        return [
            $idLabel, 'full_name', 'nisn',
            'classroom_name', 'classroom_subject_id', 'subject_name',
            'academic_year', 'semester', 'is_active', 'tracking_opt_out',
        ];
    }

    public function collection(): Collection
    {
        $cs = $this->cs->loadMissing(['classroom', 'subject']);

        return Student::query()
            ->whereHas('classrooms', fn ($q) => $q->whereKey($cs->classroom_id))
            ->orderBy('full_name')
            ->get()
            ->map(function (Student $s) use ($cs) {
                $id = $this->mode === 'anonim' ? LearningProgressExport::pseudoId($s->id) : $s->id;
                $name = $this->mode === 'anonim' ? '' : (string) $s->full_name;
                $nisn = $this->mode === 'anonim' ? '' : (string) ($s->nisn ?? '');

                return [
                    $id, $name, $nisn,
                    (string) ($cs->classroom?->name ?? ''),
                    $cs->id,
                    (string) ($cs->subject?->name ?? ''),
                    (string) $cs->academic_year,
                    (int) $cs->semester,
                    $s->is_active ? 'TRUE' : 'FALSE',
                    $s->tracking_opt_out ? 'TRUE' : 'FALSE',
                ];
            });
    }
}

class LearningProgressDailyRollupsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly ClassroomSubject $cs,
        private readonly string $mode,
    ) {}

    public function title(): string
    {
        return 'daily_rollups';
    }

    public function headings(): array
    {
        $idLabel = $this->mode === 'anonim' ? 'student_pseudo_id' : 'student_id';

        return [
            $idLabel, 'classroom_subject_id', 'date',
            'material_seconds', 'assignment_seconds', 'exam_seconds',
            'materials_opened', 'assignments_worked', 'exams_attempted',
        ];
    }

    public function collection(): Collection
    {
        // Per §14.4: siswa terdaftar tanpa rollup → row tetap dihasilkan dengan 0.
        // MVP: hanya muat rollup yang sudah ada (siswa tanpa aktivitas → tidak muncul di sheet ini).
        // Researcher bisa LEFT JOIN dari sheet `students` di Excel kalau perlu zero-row.
        return LearningProgressDailyRollup::query()
            ->where('classroom_subject_id', $this->cs->id)
            ->orderBy('date')
            ->get()
            ->map(function (LearningProgressDailyRollup $r) {
                $id = $this->mode === 'anonim' ? LearningProgressExport::pseudoId($r->student_id) : $r->student_id;

                return [
                    $id, $r->classroom_subject_id, $r->date->format('Y-m-d'),
                    (int) $r->material_seconds, (int) $r->assignment_seconds, (int) $r->exam_seconds,
                    (int) $r->materials_opened, (int) $r->assignments_worked, (int) $r->exams_attempted,
                ];
            });
    }
}

class LearningProgressSubmissionsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly ClassroomSubject $cs,
        private readonly string $mode,
    ) {}

    public function title(): string
    {
        return 'submissions';
    }

    public function headings(): array
    {
        $idLabel = $this->mode === 'anonim' ? 'student_pseudo_id' : 'student_id';

        return [
            'submission_id', $idLabel, 'assignment_id', 'assignment_title',
            'deadline', 'submitted_at', 'is_late', 'score', 'max_score',
            'time_on_page_seconds',
        ];
    }

    public function collection(): Collection
    {
        $materialIds = Material::query()
            ->where('classroom_subject_id', $this->cs->id)
            ->pluck('id');

        $assignmentIds = Assignment::query()
            ->whereIn('material_id', $materialIds)
            ->pluck('id');

        $rows = AssignmentSubmission::query()
            ->whereIn('assignment_id', $assignmentIds)
            ->with('assignment')
            ->get();

        // Hitung time_on_page per (student, assignment) sekaligus.
        $timeMap = LearningProgressSession::query()
            ->whereIn('trackable_id', $assignmentIds)
            ->where('trackable_type', (new Assignment)->getMorphClass())
            ->selectRaw('student_id, trackable_id, SUM(active_seconds) AS total')
            ->groupBy('student_id', 'trackable_id')
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->student_id}|{$r->trackable_id}" => (int) $r->total]);

        return $rows->map(function (AssignmentSubmission $sub) use ($timeMap) {
            $id = $this->mode === 'anonim' ? LearningProgressExport::pseudoId($sub->student_id) : $sub->student_id;
            $assignment = $sub->assignment;

            return [
                $sub->id, $id, $sub->assignment_id,
                (string) ($assignment?->title ?? ''),
                $assignment?->deadline?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i') ?? '',
                $sub->submitted_at?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i') ?? '',
                $sub->is_late ? 'TRUE' : 'FALSE',
                $sub->score !== null ? number_format((float) $sub->score, 2, '.', '') : '',
                $assignment?->max_score !== null ? number_format((float) $assignment->max_score, 2, '.', '') : '',
                (int) ($timeMap["{$sub->student_id}|{$sub->assignment_id}"] ?? 0),
            ];
        });
    }
}

class LearningProgressExamSessionsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly ClassroomSubject $cs,
        private readonly string $mode,
    ) {}

    public function title(): string
    {
        return 'exam_sessions';
    }

    public function headings(): array
    {
        $idLabel = $this->mode === 'anonim' ? 'student_pseudo_id' : 'student_id';

        return [
            'session_id', $idLabel, 'exam_id', 'exam_title',
            'starts_at', 'started_at', 'submitted_at', 'submission_reason',
            'duration_minutes_configured', 'duration_seconds_actual',
            'total_score', 'max_total_score',
        ];
    }

    public function collection(): Collection
    {
        $materialIds = Material::query()
            ->where('classroom_subject_id', $this->cs->id)
            ->pluck('id');

        $examIds = Exam::query()
            ->whereIn('material_id', $materialIds)
            ->pluck('id');

        return ExamSession::query()
            ->whereIn('exam_id', $examIds)
            ->with('exam')
            ->get()
            ->map(function (ExamSession $sess) {
                $id = $this->mode === 'anonim' ? LearningProgressExport::pseudoId($sess->student_id) : $sess->student_id;
                $exam = $sess->exam;

                $actualSeconds = 0;
                if ($sess->started_at && $sess->submitted_at) {
                    $actualSeconds = (int) $sess->started_at->diffInSeconds($sess->submitted_at);
                }

                return [
                    $sess->id, $id, $sess->exam_id,
                    (string) ($exam?->title ?? ''),
                    $exam?->starts_at?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i') ?? '',
                    $sess->started_at?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i') ?? '',
                    $sess->submitted_at?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i') ?? '',
                    (string) ($sess->submission_reason ?? ''),
                    (int) ($exam?->duration_minutes ?? 0),
                    $actualSeconds,
                    $sess->total_score !== null ? number_format((float) $sess->total_score, 2, '.', '') : '',
                    $exam?->max_score !== null ? number_format((float) $exam->max_score, 2, '.', '') : '',
                ];
            });
    }
}

class LearningProgressManifestSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly ClassroomSubject $cs,
        private readonly string $mode,
    ) {}

    public function title(): string
    {
        return '_manifest';
    }

    public function headings(): array
    {
        return ['field', 'value'];
    }

    public function collection(): Collection
    {
        $configContent = file_exists($p = config_path('learning_progress.php')) ? (string) file_get_contents($p) : '';
        $configHash = $configContent !== '' ? hash('sha256', $configContent) : '';

        $commit = trim((string) @shell_exec('git rev-parse --short HEAD 2>/dev/null')) ?: 'unknown';

        $first = LearningProgressDailyRollup::query()
            ->where('classroom_subject_id', $this->cs->id)
            ->min('date');
        $last = LearningProgressDailyRollup::query()
            ->where('classroom_subject_id', $this->cs->id)
            ->max('date');
        $range = $first && $last
            ? CarbonImmutable::parse($first)->format('Y-m-d').' .. '.CarbonImmutable::parse($last)->format('Y-m-d')
            : '';

        return collect([
            ['exported_at', CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d H:i:s')],
            ['exported_by', (string) (auth()->id() ?? 'system')],
            ['mode', $this->mode],
            ['config_hash', $configHash],
            ['app_commit', $commit],
            ['date_range', $range],
            ['classroom_subject_id', $this->cs->id],
        ]);
    }
}
