<?php

namespace App\Support;

use App\Models\Enums\LearningProgressEventType;
use Carbon\CarbonImmutable;

/**
 * Algoritma waktu aktif/idle untuk learning-progress — MURNI (tanpa DB / state).
 *
 * Diekstrak dari RecordLearningProgress (God-class) supaya matematika penghitungan
 * durasi bisa diuji terisolasi. Diberi anchor (state terakhir dari session yang
 * sama) + batch event terurut, menghitung delta_active_ms & delta_idle_ms.
 */
class ActiveTimeCalculator
{
    /**
     * @param  array{occurred_at:CarbonImmutable, state:string}|null  $anchor  state sebelumnya untuk kontinuasi (null = session baru)
     * @param  array<int,array<string,mixed>>  $sorted  batch baru, terurut ASC
     * @return array{0:int,1:int} [activeMs, idleMs]
     */
    public function computeDelta(?array $anchor, array $sorted, int $maxGapMs): array
    {
        $activeMs = 0;
        $idleMs = 0;

        if ($anchor !== null) {
            $prevTime = $anchor['occurred_at'];
            $prevState = $anchor['state'];
        } else {
            $prevTime = null;
            $prevState = 'active';
        }

        foreach ($sorted as $event) {
            /** @var CarbonImmutable $currTime */
            $currTime = $event['occurred_at'];
            /** @var LearningProgressEventType $type */
            $type = $event['event'];

            if ($prevTime !== null) {
                $deltaMs = $prevTime->diffInMilliseconds($currTime);
                if ($deltaMs < 0) {
                    $deltaMs = 0;
                }
                $deltaMs = min($maxGapMs, $deltaMs);

                if ($prevState === 'active') {
                    $activeMs += $deltaMs;
                } elseif ($prevState === 'idle') {
                    $idleMs += $deltaMs;
                }
            }

            if ($type->isActiveState()) {
                $prevState = 'active';
            } elseif ($type->isIdleState()) {
                $prevState = 'idle';
            }
            // heartbeat & close inherit prior state.

            $prevTime = $currTime;
        }

        return [$activeMs, $idleMs];
    }

    /**
     * @param  array<int,array<string,mixed>>  $sorted
     */
    public function hasClose(array $sorted): bool
    {
        foreach ($sorted as $event) {
            if ($event['event'] === LearningProgressEventType::Close) {
                return true;
            }
        }

        return false;
    }
}
