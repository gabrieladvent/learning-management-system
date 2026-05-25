<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProgressPruneOldEventsCommand extends Command
{
    protected $signature = 'progress:prune-old-events';

    protected $description = 'Hapus learning_progress_events lebih tua dari retention.events_days (§5.3).';

    public function handle(): int
    {
        $days = (int) config('learning_progress.retention.events_days', 90);
        $cutoff = now()->subDays($days);

        $totalDeleted = 0;

        do {
            $deleted = DB::table('learning_progress_events')
                ->where('received_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            $totalDeleted += $deleted;
        } while ($deleted > 0);

        $this->info("Pruned {$totalDeleted} event row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
