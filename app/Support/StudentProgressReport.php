<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassroomSubject;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\LearningProgressSession;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Merangkum seluruh progres belajar 1 siswa pada 1 mata pelajaran.
 * Dipakai oleh Level 3 (ViewStudentProgress) — durasi material, progres tugas/ujian,
 * agregasi, dan variabel data penelitian (docs/11 §13.2).
 *
 * Semua query di-scope ke (student × classroom_subject). Untuk 1 siswa, jumlah query
 * masih kecil (~15-20) — acceptable untuk halaman detail.
 */
class StudentProgressReport
{
    public function __construct(
        private readonly ClassroomSubject $classroomSubject,
        private readonly Student $student,
    ) {}

    /**
     * @return array{title:string,topic:?string,type:string,duration_seconds:int,last_accessed:?string,completed:bool,completion_basis:string}[]
     */
    public function materials(): array
    {
        $materials = Material::query()
            ->where('classroom_subject_id', $this->classroomSubject->id)
            ->where('is_published', true)
            ->orderBy('order')
            ->get();

        // Sessions agregat per material (1 query).
        $sessionAgg = LearningProgressSession::query()
            ->where('student_id', $this->student->id)
            ->where('classroom_subject_id', $this->classroomSubject->id)
            ->where('trackable_type', (new Material)->getMorphClass())
            ->selectRaw('trackable_id, SUM(active_seconds) AS secs, MAX(last_seen_at) AS last_seen')
            ->groupBy('trackable_id')
            ->get()
            ->keyBy('trackable_id');

        // Material yang sudah di-download siswa ini (1 query).
        $downloadedIds = Activity::query()
            ->where('log_name', 'material_download')
            ->where('subject_type', (new Material)->getMorphClass())
            ->whereIn('subject_id', $materials->pluck('id'))
            ->where('causer_type', (new Student)->getMorphClass())
            ->where('causer_id', $this->student->id)
            ->pluck('subject_id')
            ->unique()
            ->flip();

        return $materials->map(function (Material $m) use ($sessionAgg, $downloadedIds) {
            $agg = $sessionAgg->get($m->id);
            $seconds = (int) ($agg->secs ?? 0);
            $lastSeen = $agg->last_seen ?? null;
            $type = MaterialCompletion::classify($m);
            $downloaded = $downloadedIds->has($m->id);
            $completed = MaterialCompletion::isCompleted($m, $type, $seconds, $downloaded);

            return [
                'title' => $m->title,
                'topic' => $m->topic,
                'type' => $type,
                'duration_seconds' => $seconds,
                'last_accessed' => $lastSeen,
                'completed' => $completed,
                // Dasar penilaian "selesai" — supaya UI bisa jelasin kenapa selesai walau durasi 0.
                'completion_basis' => match (true) {
                    ! $completed => 'none',
                    $type === 'file' => 'download',
                    default => 'duration',
                },
            ];
        })->all();
    }

    /**
     * @return array{title:string,deadline:?string,submitted_at:?string,is_late:bool,score:?float,max_score:?float,time_on_page_seconds:int,status:string}[]
     */
    public function assignments(): array
    {
        $materialIds = Material::query()
            ->where('classroom_subject_id', $this->classroomSubject->id)
            ->where('is_published', true)
            ->pluck('id');

        $assignments = Assignment::query()
            ->whereIn('material_id', $materialIds)
            ->where('is_published', true)
            ->orderBy('order')
            ->get();

        $submissions = AssignmentSubmission::query()
            ->where('student_id', $this->student->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        $timeOnPage = LearningProgressSession::query()
            ->where('student_id', $this->student->id)
            ->where('trackable_type', (new Assignment)->getMorphClass())
            ->whereIn('trackable_id', $assignments->pluck('id'))
            ->selectRaw('trackable_id, SUM(active_seconds) AS secs')
            ->groupBy('trackable_id')
            ->get()
            ->keyBy('trackable_id');

        return $assignments->map(function (Assignment $a) use ($submissions, $timeOnPage) {
            $sub = $submissions->get($a->id);

            $status = 'belum';
            if ($sub && $sub->submitted_at) {
                $status = $sub->score !== null ? 'dinilai' : 'submitted';
            } elseif ($a->deadline && now()->greaterThan($a->deadline)) {
                $status = 'overdue';
            }

            return [
                'title' => $a->title,
                'deadline' => $a->deadline?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
                'submitted_at' => $sub?->submitted_at?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
                'is_late' => (bool) ($sub?->is_late ?? false),
                'score' => $sub?->score !== null ? (float) $sub->score : null,
                'max_score' => $a->max_score !== null ? (float) $a->max_score : null,
                'time_on_page_seconds' => (int) ($timeOnPage->get($a->id)->secs ?? 0),
                'status' => $status,
            ];
        })->all();
    }

    /**
     * @return array{title:string,status:string,started_at:?string,submitted_at:?string,duration_seconds:int,detail_seconds:int,score:?float,max_score:?float}[]
     */
    public function exams(): array
    {
        $materialIds = Material::query()
            ->where('classroom_subject_id', $this->classroomSubject->id)
            ->where('is_published', true)
            ->pluck('id');

        $exams = Exam::query()
            ->whereIn('material_id', $materialIds)
            ->where('is_published', true)
            ->orderBy('order')
            ->get();

        $sessions = ExamSession::query()
            ->where('student_id', $this->student->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->keyBy('exam_id');

        // Waktu di halaman detail exam (sebelum mulai / sesudah submit) — dari learning_progress.
        $detailSeconds = LearningProgressSession::query()
            ->where('student_id', $this->student->id)
            ->where('trackable_type', (new Exam)->getMorphClass())
            ->whereIn('trackable_id', $exams->pluck('id'))
            ->selectRaw('trackable_id, SUM(active_seconds) AS secs')
            ->groupBy('trackable_id')
            ->get()
            ->keyBy('trackable_id');

        return $exams->map(function (Exam $e) use ($sessions, $detailSeconds) {
            $sess = $sessions->get($e->id);

            $status = 'belum';
            if ($sess && $sess->submitted_at) {
                $status = $sess->total_score !== null ? 'dinilai' : 'submitted';
            } elseif ($sess && $sess->started_at) {
                $status = 'sedang_mengerjakan';
            }

            $workSeconds = 0;
            if ($sess?->started_at && $sess?->submitted_at) {
                $workSeconds = (int) $sess->started_at->diffInSeconds($sess->submitted_at);
            }

            return [
                'title' => $e->title,
                'status' => $status,
                'started_at' => $sess?->started_at?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
                'submitted_at' => $sess?->submitted_at?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
                'duration_seconds' => $workSeconds,
                'detail_seconds' => (int) ($detailSeconds->get($e->id)->secs ?? 0),
                'score' => $sess?->total_score !== null ? (float) $sess->total_score : null,
                'max_score' => $e->max_score !== null ? (float) $e->max_score : null,
            ];
        })->all();
    }

    /**
     * Variabel data penelitian per §13.2.
     *
     * @return array<string, mixed>
     */
    public function research(): array
    {
        $materials = $this->materials();
        $assignments = $this->assignments();
        $exams = $this->exams();

        $totalMaterials = count($materials);
        $completedMaterials = count(array_filter($materials, fn ($m) => $m['completed']));
        $materialActiveSeconds = array_sum(array_column($materials, 'duration_seconds'));

        $totalAssignments = count($assignments);
        $submitted = count(array_filter($assignments, fn ($a) => $a['submitted_at'] !== null));
        $late = count(array_filter($assignments, fn ($a) => $a['is_late']));

        $totalExams = count($exams);
        $examAttempted = count(array_filter($exams, fn ($e) => $e['submitted_at'] !== null));

        $examScores = array_filter(array_column($exams, 'score'), fn ($v) => $v !== null);
        $assignmentScores = array_filter(array_column($assignments, 'score'), fn ($v) => $v !== null);

        $dailyEngagement = (float) DB::table('learning_progress_daily_rollups')
            ->where('student_id', $this->student->id)
            ->where('classroom_subject_id', $this->classroomSubject->id)
            ->selectRaw('AVG(material_seconds + assignment_seconds + exam_seconds) AS avg_daily')
            ->value('avg_daily');

        return [
            'material_active_seconds_total' => $materialActiveSeconds,
            'material_completion_rate' => $totalMaterials > 0 ? round($completedMaterials / $totalMaterials, 3) : 0.0,
            'assignment_submit_rate' => $totalAssignments > 0 ? round($submitted / $totalAssignments, 3) : 0.0,
            'assignment_late_rate' => $submitted > 0 ? round($late / $submitted, 3) : 0.0,
            'exam_attempt_rate' => $totalExams > 0 ? round($examAttempted / $totalExams, 3) : 0.0,
            'exam_avg_score' => $examScores !== [] ? round(array_sum($examScores) / count($examScores), 2) : null,
            'assignment_avg_score' => $assignmentScores !== [] ? round(array_sum($assignmentScores) / count($assignmentScores), 2) : null,
            'daily_engagement_seconds' => (int) round($dailyEngagement),
            'risk_status' => $this->riskStatus($materialActiveSeconds, $assignments),
        ];
    }

    public function riskStatus(int $materialActiveSeconds, array $assignments): string
    {
        $overdue = count(array_filter(
            $assignments,
            fn ($a) => $a['status'] === 'overdue',
        ));

        $classAvg = (float) DB::table('learning_progress_daily_rollups')
            ->where('classroom_subject_id', $this->classroomSubject->id)
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->avg('material_seconds');

        return LearningProgressMetrics::riskStatus($overdue, $materialActiveSeconds, $classAvg);
    }
}
