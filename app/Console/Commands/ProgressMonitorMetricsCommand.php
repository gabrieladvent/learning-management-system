<?php

namespace App\Console\Commands;

use App\Models\LearningProgressSession;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProgressMonitorMetricsCommand extends Command
{
    protected $signature = 'progress:monitor-metrics';

    protected $description = 'Tulis counter Prometheus-style ke storage/logs/progress-metrics.log (§Fase A Monitoring).';

    public function handle(): int
    {
        $now = CarbonImmutable::now('UTC');
        $windowStart = $now->subMinute();

        $eventsInserted = DB::table('learning_progress_events')
            ->where('received_at', '>=', $windowStart->toDateTimeString())
            ->count();

        $sessionsOpen = LearningProgressSession::query()->whereNull('ended_at')->count();

        $path = (string) config('learning_progress.monitoring.log_path', storage_path('logs/progress-metrics.log'));
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $ts = $now->toIso8601String();
        $line = sprintf(
            "%s events_inserted_total{window=\"1m\"}=%d sessions_open_gauge=%d\n",
            $ts,
            $eventsInserted,
            $sessionsOpen,
        );

        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);

        // Workhours warning: events=0 selama jam kerja Asia/Jakarta hari kerja.
        $local = CarbonImmutable::now('Asia/Jakarta');
        $isWorkhour = $local->isWeekday() && $local->hour >= 7 && $local->hour < 17;
        if ($isWorkhour && $eventsInserted === 0) {
            $this->warn("[progress-metrics] events_inserted_total=0 dalam 1 menit terakhir saat jam kerja ({$local}).");
        }

        return self::SUCCESS;
    }
}
