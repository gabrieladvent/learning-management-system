<?php

namespace App\Console\Commands;

use App\Models\ExamSession;
use App\Services\ExamGrader;
use Illuminate\Console\Command;

class AutoSubmitExpiredExamSessionsCommand extends Command
{
    protected $signature = 'exam:auto-submit-expired';

    protected $description = 'Auto-submit ExamSession yang timer-nya sudah habis tapi belum di-submit siswa.';

    public function handle(ExamGrader $grader): int
    {
        $candidates = ExamSession::query()
            ->whereNull('submitted_at')
            ->whereNotNull('started_at')
            ->with('exam:id,duration_minutes')
            ->get();

        $submitted = 0;

        foreach ($candidates as $session) {
            $duration = (int) ($session->exam?->duration_minutes ?? 0);

            if ($duration <= 0) {
                continue;
            }

            $expiresAt = $session->started_at->copy()->addMinutes($duration);

            if (now()->lessThan($expiresAt)) {
                continue;
            }

            $session->submitted_at = $expiresAt;
            $session->submission_reason = 'auto_timeout';
            $session->save();

            $grader->grade($session);

            $submitted++;
        }

        $this->info("Auto-submitted {$submitted} expired exam session(s).");

        return self::SUCCESS;
    }
}
