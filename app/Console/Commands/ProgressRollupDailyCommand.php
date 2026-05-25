<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\LearningProgressDailyRollup;
use App\Models\Material;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProgressRollupDailyCommand extends Command
{
    protected $signature = 'progress:rollup-daily {--date= : Tanggal lokal Asia/Jakarta YYYY-MM-DD; default kemarin}';

    protected $description = 'Aggregat harian learning_progress_sessions ke learning_progress_daily_rollups (idempotent).';

    public function handle(): int
    {
        $dateInput = $this->option('date');
        $tz = 'Asia/Jakarta';

        try {
            $date = $dateInput
                ? CarbonImmutable::createFromFormat('Y-m-d', $dateInput, $tz)->startOfDay()
                : CarbonImmutable::now($tz)->subDay()->startOfDay();
        } catch (\Throwable) {
            $this->error('Format --date harus YYYY-MM-DD.');

            return self::INVALID;
        }

        $startUtc = $date->utc();
        $endUtc = $date->addDay()->utc();
        $dateString = $date->format('Y-m-d');

        $this->info("Rolling up tanggal {$dateString} (Asia/Jakarta) — window UTC {$startUtc} .. {$endUtc}");

        // Basis grouping: received_at di window UTC, dipakai sebagai proxy "sesi terjadi pada tanggal ini".
        // Kita memilih sessions yang last_seen_at di window untuk konsistensi dengan §3.5.
        $rows = DB::table('learning_progress_sessions')
            ->selectRaw('
                student_id,
                classroom_subject_id,
                trackable_type,
                trackable_id,
                SUM(active_seconds) AS seconds
            ')
            ->whereBetween('last_seen_at', [$startUtc->toDateTimeString(), $endUtc->toDateTimeString()])
            ->groupBy('student_id', 'classroom_subject_id', 'trackable_type', 'trackable_id')
            ->get();

        // Aggregate dalam memory per (student, classroom_subject).
        $agg = [];
        foreach ($rows as $row) {
            $key = $row->student_id.'|'.$row->classroom_subject_id;
            if (! isset($agg[$key])) {
                $agg[$key] = [
                    'student_id' => $row->student_id,
                    'classroom_subject_id' => $row->classroom_subject_id,
                    'material_seconds' => 0,
                    'assignment_seconds' => 0,
                    'exam_seconds' => 0,
                    'materials' => [],
                    'assignments' => [],
                    'exams' => [],
                ];
            }

            $seconds = (int) $row->seconds;
            switch ($row->trackable_type) {
                case (new Material)->getMorphClass():
                    $agg[$key]['material_seconds'] += $seconds;
                    $agg[$key]['materials'][$row->trackable_id] = true;
                    break;
                case (new Assignment)->getMorphClass():
                    $agg[$key]['assignment_seconds'] += $seconds;
                    $agg[$key]['assignments'][$row->trackable_id] = true;
                    break;
                case (new Exam)->getMorphClass():
                    $agg[$key]['exam_seconds'] += $seconds;
                    $agg[$key]['exams'][$row->trackable_id] = true;
                    break;
            }
        }

        $now = now();
        $upserted = 0;

        foreach ($agg as $row) {
            LearningProgressDailyRollup::updateOrCreate(
                [
                    'student_id' => $row['student_id'],
                    'classroom_subject_id' => $row['classroom_subject_id'],
                    'date' => $date->toDateString(),
                ],
                [
                    'material_seconds' => $row['material_seconds'],
                    'assignment_seconds' => $row['assignment_seconds'],
                    'exam_seconds' => $row['exam_seconds'],
                    'materials_opened' => count($row['materials']),
                    'assignments_worked' => count($row['assignments']),
                    'exams_attempted' => count($row['exams']),
                    'computed_at' => $now,
                ],
            );
            $upserted++;
        }

        $this->info("Upserted {$upserted} rollup row(s).");

        return self::SUCCESS;
    }
}
