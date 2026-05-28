<?php

namespace App\Support;

use App\Models\Material;

/**
 * Aturan klasifikasi & "selesai" material (docs/11 §7.1).
 * Dipakai bersama oleh Level 2 (batch, ViewCourseProgress) & Level 3 (StudentProgressReport).
 *
 * Prioritas klasifikasi: file > link > text.
 * Aturan selesai:
 *   - file : siswa men-download (file_counts_as_done = true)
 *   - link : active_seconds >= minimum_seconds
 *   - text : active_seconds >= active_ratio × estimated_read_seconds
 */
class MaterialCompletion
{
    public static function classify(Material $material): string
    {
        if ($material->getMedia('material_files')->isNotEmpty()) {
            return 'file';
        }
        if (! empty($material->link_url)) {
            return 'link';
        }

        return 'text';
    }

    /**
     * Estimasi detik baca untuk material teks (dipakai threshold completion).
     */
    public static function estimatedReadSeconds(Material $material): int
    {
        $minSeconds = (int) config('learning_progress.material_completion.minimum_seconds', 60);
        $wpm = (int) config('learning_progress.material_completion.words_per_minute', 210);

        $plain = trim(strip_tags((string) $material->content));
        $wordCount = $plain === '' ? 0 : count(preg_split('/\s+/', $plain));

        return max($minSeconds, (int) round($wordCount / max(1, $wpm / 60)));
    }

    /**
     * @param  string  $type  hasil classify()
     * @param  int  $activeSeconds  total active_seconds siswa di material ini
     * @param  bool  $downloaded  apakah siswa sudah download (untuk type=file)
     */
    public static function isCompleted(Material $material, string $type, int $activeSeconds, bool $downloaded): bool
    {
        $minSeconds = (int) config('learning_progress.material_completion.minimum_seconds', 60);
        $activeRatio = (float) config('learning_progress.material_completion.active_ratio', 0.80);
        $fileCountsAsDone = (bool) config('learning_progress.material_completion.file_counts_as_done', true);

        return match ($type) {
            'file' => $fileCountsAsDone && $downloaded,
            'link' => $activeSeconds >= $minSeconds,
            default => $activeSeconds >= ($activeRatio * self::estimatedReadSeconds($material)),
        };
    }
}
