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
 *  1. migrate --force         → skema DB nyusul kode baru sebelum cache dibangun
 *  2. optimize:clear          → buang cache lama biar tidak nyangkut config basi
 *  3. config/route/view/event → cache ulang untuk performa production
 *  4. filament:optimize       → cache komponen + ikon Filament (panel teacher)
 *  5. storage:link            → pastikan symlink public/storage ada (medialibrary)
 *  6. queue:restart           → worker lama reload kode baru dengan mulus
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

        $steps = [];

        if (! $this->option('skip-migrations')) {
            $steps[] = ['migrate', ['--force' => true], 'Migrasi database'];

            if ($this->option('seed')) {
                $steps[] = ['db:seed', ['--force' => true], 'Seeding database'];
            }

        } else {
            $this->line('  ⏭  Migrasi dilewati (--skip-migrations).');
        }

        // Buang dulu seluruh cache lama, baru bangun ulang. optimize:clear
        // mencakup config/route/view/event/cache + compiled.
        $steps[] = ['optimize:clear', [], 'Membersihkan cache lama'];

        $steps[] = ['config:cache', [], 'Cache config'];

        $steps[] = ['route:cache', [], 'Cache route'];

        $steps[] = ['view:cache', [], 'Cache view'];

        $steps[] = ['event:cache', [], 'Cache event'];

        // Filament 3 punya cache sendiri (komponen Blade + ikon). Wajib di
        // production, jika tidak panel teacher bisa lambat / ikon hilang.
        $steps[] = ['filament:optimize', [], 'Optimize Filament'];

        // Symlink storage untuk spatie/medialibrary (avatar, materi, dsb).
        $steps[] = ['storage:link', [], 'Symlink storage'];

        // Worker queue (driver database) reload kode baru tanpa drop job.
        $steps[] = ['queue:restart', [], 'Restart queue worker'];

        $failed = 0;

        foreach ($steps as [$command, $args, $label]) {
            $this->line("  → {$label} (php artisan {$command})");

            try {
                $exit = $this->call($command, $args);

                if ($exit !== self::SUCCESS) {
                    $failed++;

                    $this->error("    ✗ {$label} keluar dengan kode {$exit}.");
                }

            } catch (Throwable $e) {
                $failed++;

                $this->error("    ✗ {$label} gagal: {$e->getMessage()}");
            }
        }

        if ($failed > 0) {
            $this->error("❌ Deployment hook selesai dengan {$failed} langkah gagal.");

            return self::FAILURE;
        }

        $this->info('✅ Deployment hook selesai tanpa error.');

        return self::SUCCESS;
    }
}
