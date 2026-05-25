<?php

namespace App\Console\Commands;

use App\Models\AssignmentSubmission;
use App\Models\ExamAnswer;
use App\Models\ExamSession;
use App\Models\ExamSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ResearchResetCommand extends Command
{
    protected $signature = 'research:reset {--force : skip confirmation}';

    protected $description = 'Truncate semua data submission siswa (assignments, exams, sessions, answers, notifications, activity log) untuk siklus penelitian baru. Tidak menghapus students, classrooms, materials, atau questions.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('research:reset NEVER runs in production. Aborting.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Ini akan menghapus SEMUA submission/session/answer + notifikasi + activity log. Lanjut?')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            $this->line('Truncating...');

            // Hapus turunan paling dalam dulu (FK cascade harusnya sudah handle, tapi explicit lebih aman).
            ExamAnswer::query()->delete();
            ExamSession::query()->delete();
            ExamSubmission::query()->withTrashed()->forceDelete();
            AssignmentSubmission::query()->withTrashed()->forceDelete();

            // Activity log + notifikasi
            Activity::query()->delete();
            DB::table('notifications')->delete();

            // Queue jobs pending (kalau ada)
            DB::table('jobs')->delete();
            DB::table('failed_jobs')->delete();
        });

        // Print summary
        $this->info('Done. State sekarang:');
        $this->line('  AssignmentSubmissions: '.AssignmentSubmission::withTrashed()->count());
        $this->line('  ExamSessions: '.ExamSession::count());
        $this->line('  ExamAnswers: '.ExamAnswer::count());
        $this->line('  Activity logs: '.Activity::count());
        $this->line('  Notifications: '.DB::table('notifications')->count());
        $this->line('  Jobs queue: '.DB::table('jobs')->count());

        $this->info('Re-run `php artisan db:seed --class=LoadTestSeeder` kalau butuh dummy submission untuk siklus baru.');

        return self::SUCCESS;
    }
}
