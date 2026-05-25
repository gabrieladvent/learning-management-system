<?php

namespace App\Console\Commands;

use App\Models\LearningProgressSession;
use Illuminate\Console\Command;

class ProgressCloseStaleSessionsCommand extends Command
{
    protected $signature = 'progress:close-stale-sessions';

    protected $description = 'Tutup sesi learning_progress yang last_seen_at < now - session.timeout_minutes (§3.2).';

    public function handle(): int
    {
        $timeoutMinutes = (int) config('learning_progress.session.timeout_minutes', 5);
        $cutoff = now()->subMinutes($timeoutMinutes);

        $closed = 0;

        LearningProgressSession::query()
            ->whereNull('ended_at')
            ->where('last_seen_at', '<', $cutoff)
            ->chunkById(500, function ($sessions) use (&$closed) {
                foreach ($sessions as $session) {
                    $session->ended_at = $session->last_seen_at;
                    $session->end_reason = 'timeout';
                    $session->save();
                    $closed++;
                }
            });

        $this->info("Closed {$closed} stale session(s).");

        return self::SUCCESS;
    }
}
