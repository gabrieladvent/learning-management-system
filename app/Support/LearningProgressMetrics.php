<?php

namespace App\Support;

/**
 * Helper format & threshold untuk dashboard learning progress.
 * Dipakai oleh CourseProgressResource & widgets.
 */
class LearningProgressMetrics
{
    /**
     * Format detik → "2j 14m" / "45m" / "30d".
     */
    public static function formatDuration(?int $seconds): string
    {
        $s = (int) ($seconds ?? 0);
        if ($s <= 0) {
            return '0';
        }

        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);

        if ($h > 0) {
            return $m > 0 ? "{$h}j {$m}m" : "{$h}j";
        }
        if ($m > 0) {
            return "{$m}m";
        }

        return "{$s}d";
    }

    /**
     * Hitung risk status — 🔴 berisiko / ⚠️ pantau / ✅ aman — sesuai §7.2.
     *
     * @param  int  $overdueCount  jumlah assignment overdue (deadline lewat & belum submit)
     * @param  int  $materialSeconds  total durasi material siswa pada window
     * @param  float  $classAvgMaterialSeconds  rata-rata kelas
     */
    public static function riskStatus(int $overdueCount, int $materialSeconds, float $classAvgMaterialSeconds): string
    {
        $criticalCount = (int) config('learning_progress.risk_thresholds.overdue_critical_count', 2);
        $lowPct = (int) config('learning_progress.risk_thresholds.class_avg_low_pct', 50);
        $criticalPct = (int) config('learning_progress.risk_thresholds.class_avg_critical_pct', 25);

        $criticalThreshold = $classAvgMaterialSeconds * ($criticalPct / 100);
        $lowThreshold = $classAvgMaterialSeconds * ($lowPct / 100);

        if ($overdueCount >= $criticalCount && $materialSeconds < $criticalThreshold) {
            return 'berisiko';
        }

        if ($overdueCount >= 1 || $materialSeconds < $lowThreshold) {
            return 'pantau';
        }

        return 'aman';
    }

    public static function riskBadgeColor(string $status): string
    {
        return match ($status) {
            'berisiko' => 'danger',
            'pantau' => 'warning',
            default => 'success',
        };
    }

    public static function riskLabel(string $status): string
    {
        return match ($status) {
            'berisiko' => 'Berisiko',
            'pantau' => 'Pantau',
            default => 'Aman',
        };
    }
}
