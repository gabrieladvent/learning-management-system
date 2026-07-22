<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

/**
 * Post-deploy hook: langkah-langkah internal Laravel yang harus dijalankan
 * SETELAH kode baru ter-pull & dependency ter-install (composer/npm dihandle
 * oleh production.sh / CI). Aman dipanggil berulang (idempotent).
 *
 * Dipanggil dari production.sh, atau langsung sebagai webhook deploy:
 *   php artisan deploy:hook
 *
 * Urutan penting:
 *  1. migrate --force          -> skema DB nyusul kode baru sebelum cache dibangun.
 *     CRITICAL: kalau gagal, ABORT sebelum caching apa pun — jangan pernah cache
 *     kode baru di atas skema DB lama (bisa 500 di production).
 *  2. media:migrate-to-private -> pindahkan file media sensitif dari disk public ke
 *     privat (idempotent; hanya memproses yang masih di disk public).
 *  3. optimize:clear           -> buang cache lama biar tidak nyangkut config basi.
 *  4. config/route/view/event  -> cache ulang untuk performa production.
 *  5. filament:optimize        -> cache komponen + ikon Filament (panel teacher).
 *  6. storage:link             -> pastikan symlink public/storage ada (medialibrary).
 *  7. queue:restart            -> worker lama reload kode baru dengan mulus.
 */
class DeploymentHookCommand extends Command
{
    protected $signature = 'deploy:hook
        {--skip-migrations : Lewati php artisan migrate (kalau migrasi sudah dijalankan terpisah)}
        {--seed : Jalankan db:seed --force setelah migrate}';

    protected $description = 'Jalankan langkah post-deploy internal Laravel (migrate, cache, filament:optimize, queue:restart).';

    public function handle(): int
    {
        $this->info('🚀 Menjalankan deployment hook...');

        // Format step: [command, args, label, critical].
        // critical = true → kalau gagal, ABORT segera (jangan lanjut caching).
        $steps = [];

        if (! $this->option('skip-migrations')) {
            $steps[] = ['migrate', ['--force' => true], 'Migrasi database', true];

            if ($this->option('seed')) {
                $steps[] = ['db:seed', ['--force' => true], 'Seeding database', true];
            }
        } else {
            $this->line('  ⏭  Migrasi dilewati (--skip-migrations).');
        }

        // Pindahkan file media sensitif ke disk privat. Idempotent — kalau tidak
        // ada yang perlu dipindah, langsung selesai. Non-critical: kegagalan
        // dilaporkan tapi tidak memblok deploy (file tetap di public sementara).
        $steps[] = ['media:migrate-to-private', [], 'Migrasi media ke disk privat', false];

        // Generate permission Filament Shield untuk panel teacher. Idempotent.
        $steps[] = ['shield:generate', ['--all' => true, '--panel' => 'teacher'], 'Generate Shield (panel teacher)', false];

        // Buang dulu seluruh cache lama, baru bangun ulang.
        $steps[] = ['optimize:clear', [], 'Membersihkan cache lama', false];
        $steps[] = ['config:cache', [], 'Cache config', false];
        $steps[] = ['route:cache', [], 'Cache route', false];
        $steps[] = ['view:cache', [], 'Cache view', false];
        $steps[] = ['event:cache', [], 'Cache event', false];

        // Filament 3 punya cache sendiri (komponen Blade + ikon). Wajib di production.
        $steps[] = ['filament:optimize', [], 'Optimize Filament', false];

        // Symlink storage untuk spatie/medialibrary (avatar tetap di disk public).
        $steps[] = ['storage:link', [], 'Symlink storage', false];

        // Worker queue (driver database) reload kode baru tanpa drop job.
        $steps[] = ['queue:restart', [], 'Restart queue worker', false];

        $failed = 0;

        foreach ($steps as [$command, $args, $label, $critical]) {
            $this->line("  → {$label} (php artisan {$command})");

            try {
                $exit = $this->call($command, $args);

                if ($exit !== self::SUCCESS) {
                    $this->error("    ✗ {$label} keluar dengan kode {$exit}.");

                    if ($critical) {
                        $this->error('🛑 Langkah CRITICAL gagal — deploy dibatalkan SEBELUM caching. Skema DB & kode bisa tidak sinkron; perbaiki lalu ulangi.');

                        return self::FAILURE;
                    }

                    $failed++;
                }
            } catch (Throwable $e) {
                $this->error("    ✗ {$label} gagal: {$e->getMessage()}");

                if ($critical) {
                    $this->error('🛑 Langkah CRITICAL gagal — deploy dibatalkan SEBELUM caching.');

                    return self::FAILURE;
                }

                $failed++;
            }
        }

        if ($failed > 0) {
            $this->error("❌ Deployment hook selesai dengan {$failed} langkah (non-critical) gagal.");

            return self::FAILURE;
        }

        $this->info('✅ Deployment hook selesai tanpa error.');

        return self::SUCCESS;
    }
}
