<?php

namespace App\Console\Commands;

use App\Models\ExamSession;
use App\Notifications\TeacherSubmissionAlert;
use App\Services\ExamGrader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AutoSubmitExpiredExamSessionsCommand extends Command
{
    protected $signature = 'exam:auto-submit-expired';

    protected $description = 'Auto-submit ExamSession yang timer-nya sudah habis tapi belum di-submit siswa.';

    public function handle(ExamGrader $grader): int
    {
        $submitted = 0;

        $failed = 0;

        ExamSession::query()
            ->whereNull('submitted_at')
            ->whereNotNull('started_at')
            ->select('id')
            ->cursor()
            ->each(function (ExamSession $ref) use ($grader, &$submitted, &$failed): void {
                try {
                    $graded = $this->autoSubmitOne($ref->id, $grader);

                    if ($graded !== null) {

                        TeacherSubmissionAlert::forExamSession(
                            $graded->load('exam.material.classroomSubject.teacher', 'student')
                        );

                        $submitted++;
                    }
                } catch (Throwable $e) {
                    $failed++;

                    report($e);

                    $this->error("Gagal auto-submit session {$ref->id}: {$e->getMessage()}");
                }
            });

        $this->info("Auto-submitted {$submitted} expired exam session(s). Gagal: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Finalize satu session secara atomik dengan lock. Return session yang sudah
     * digrade, atau null bila tidak jadi di-submit (sudah disubmit siswa / belum
     * expired / durasi invalid).
     */
    private function autoSubmitOne(string $sessionId, ExamGrader $grader): ?ExamSession
    {
        return DB::transaction(function () use ($sessionId, $grader): ?ExamSession {
            /** @var ?ExamSession $session */
            $session = ExamSession::query()
                ->whereKey($sessionId)
                ->with('exam:id,duration_minutes')
                ->lockForUpdate()
                ->first();

            if (! $session || $session->submitted_at || ! $session->started_at) {
                return null;
            }

            $duration = (int) ($session->exam?->duration_minutes ?? 0);
            if ($duration <= 0) {
                return null;
            }

            $expiresAt = $session->started_at->copy()->addMinutes($duration);
            if (now()->lessThan($expiresAt)) {
                return null;
            }

            $session->submitted_at = $expiresAt;
            $session->submission_reason = 'auto_timeout';
            $session->save();

            return $grader->grade($session);
        });
    }
}
