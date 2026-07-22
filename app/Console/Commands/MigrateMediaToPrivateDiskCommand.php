<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pindahkan file media sensitif dari disk `public` ke disk privat (default
 * media-library, mis. `local`). File material/soal/jawaban tidak boleh berada
 * di disk publik yang bisa diunduh tanpa auth.
 *
 * Avatar SENGAJA dilewati (tetap di disk `public`).
 *
 * Idempoten & aman: pakai --dry-run untuk melihat dulu; hanya memproses media
 * yang masih `disk = public`.
 */
class MigrateMediaToPrivateDiskCommand extends Command
{
    protected $signature = 'media:migrate-to-private {--dry-run : Tampilkan rencana tanpa memindahkan}';

    protected $description = 'Pindahkan file media sensitif dari disk public ke disk privat.';

    private const SENSITIVE_COLLECTIONS = [
        'material_files',
        'assignment_attachments',
        'submission_files',
        'question_files',
    ];

    public function handle(): int
    {
        $targetDisk = config('media-library.disk_name');

        if ($targetDisk === 'public') {
            $this->error('media-library.disk_name masih "public". Set MEDIA_DISK=local dulu.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk('public');
        $target = Storage::disk($targetDisk);

        $mediaRows = Media::query()
            ->whereIn('collection_name', self::SENSITIVE_COLLECTIONS)
            ->where('disk', 'public')
            ->get();

        if ($mediaRows->isEmpty()) {
            $this->info('Tidak ada media di disk public yang perlu dipindah. Selesai.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$mediaRows->count()} media untuk dipindah ke disk \"{$targetDisk}\".");

        $moved = 0;
        $failed = 0;

        foreach ($mediaRows as $media) {
            $dir = (string) $media->getKey();
            $files = $public->allFiles($dir);

            $this->line(($dryRun ? '[dry-run] ' : '')."#{$media->getKey()} ({$media->collection_name}) → {$dir} [".count($files).' file]');

            if ($dryRun) {
                continue;
            }

            try {
                foreach ($files as $file) {
                    $stream = $public->readStream($file);
                    if ($stream === null) {
                        throw new \RuntimeException("Gagal membaca {$file}");
                    }
                    $target->writeStream($file, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $media->disk = $targetDisk;
                if ($media->conversions_disk === 'public') {
                    $media->conversions_disk = $targetDisk;
                }
                $media->save();

                $public->deleteDirectory($dir);
                $moved++;
            } catch (\Throwable $e) {
                $failed++;
                report($e);
                $this->error("Gagal memindah media #{$media->getKey()}: {$e->getMessage()}");
            }
        }

        if ($dryRun) {
            $this->info('Dry-run selesai. Jalankan tanpa --dry-run untuk memindah.');

            return self::SUCCESS;
        }

        $this->info("Selesai. Dipindah: {$moved}, gagal: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
