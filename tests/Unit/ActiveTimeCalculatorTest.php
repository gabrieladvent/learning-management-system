<?php

namespace Tests\Unit;

use App\Models\Enums\LearningProgressEventType;
use App\Support\ActiveTimeCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit test MURNI (tanpa DB) untuk algoritma waktu aktif/idle yang diekstrak dari
 * RecordLearningProgress. Inilah manfaat pemecahan God-class: matematika inti bisa
 * diuji cepat & terisolasi.
 */
class ActiveTimeCalculatorTest extends TestCase
{
    private function event(string $type, CarbonImmutable $at): array
    {
        return ['event' => LearningProgressEventType::from($type), 'occurred_at' => $at];
    }

    public function test_active_gap_between_two_active_events_counts_as_active(): void
    {
        $t0 = CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC');
        $sorted = [
            $this->event('focus', $t0),
            $this->event('heartbeat', $t0->addSeconds(5)),
        ];

        [$active, $idle] = (new ActiveTimeCalculator)->computeDelta(null, $sorted, 30_000);

        $this->assertEqualsWithDelta(5_000, $active, 0.5);
        $this->assertEqualsWithDelta(0, $idle, 0.5);
    }

    public function test_blur_then_focus_separates_active_and_idle(): void
    {
        $t0 = CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC');
        $sorted = [
            $this->event('focus', $t0),
            $this->event('blur', $t0->addSeconds(3)),   // 3s aktif sebelum blur
            $this->event('focus', $t0->addSeconds(8)),  // 5s idle saat blur
        ];

        [$active, $idle] = (new ActiveTimeCalculator)->computeDelta(null, $sorted, 30_000);

        $this->assertEqualsWithDelta(3_000, $active, 0.5);
        $this->assertEqualsWithDelta(5_000, $idle, 0.5);
    }

    public function test_gap_is_capped_at_max_gap(): void
    {
        $t0 = CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC');
        $sorted = [
            $this->event('focus', $t0),
            $this->event('heartbeat', $t0->addMinutes(10)), // 600s, tapi di-cap
        ];

        [$active] = (new ActiveTimeCalculator)->computeDelta(null, $sorted, 30_000);

        $this->assertEqualsWithDelta(30_000, $active, 0.5, 'gap panjang harus di-cap ke maxGapMs');
    }

    public function test_anchor_continues_prior_state(): void
    {
        $t0 = CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC');
        $anchor = ['occurred_at' => $t0, 'state' => 'idle'];
        $sorted = [$this->event('focus', $t0->addSeconds(4))]; // 4s idle dari anchor sampai focus

        [$active, $idle] = (new ActiveTimeCalculator)->computeDelta($anchor, $sorted, 30_000);

        $this->assertEqualsWithDelta(0, $active, 0.5);
        $this->assertEqualsWithDelta(4_000, $idle, 0.5);
    }

    public function test_has_close_detects_close_event(): void
    {
        $t0 = CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC');
        $calc = new ActiveTimeCalculator;

        $this->assertTrue($calc->hasClose([$this->event('close', $t0)]));
        $this->assertFalse($calc->hasClose([$this->event('heartbeat', $t0)]));
    }
}
