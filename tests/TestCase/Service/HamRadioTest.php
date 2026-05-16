<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\HamRadio;
use PHPUnit\Framework\TestCase;

/**
 * Covers the pure helpers on App\Service\HamRadio. No DB / Cake bootstrap
 * needed — these are static utilities.
 */
final class HamRadioTest extends TestCase
{
    public function testBandForFrequencyHitsCommonHfBands(): void
    {
        $this->assertSame('80m',  HamRadio::bandForFrequency(3.700));
        $this->assertSame('40m',  HamRadio::bandForFrequency(7.074));   // FT8 freq
        $this->assertSame('20m',  HamRadio::bandForFrequency(14.225));
        $this->assertSame('17m',  HamRadio::bandForFrequency(18.100));
        $this->assertSame('15m',  HamRadio::bandForFrequency(21.350));
        $this->assertSame('10m',  HamRadio::bandForFrequency(28.500));
    }

    public function testBandForFrequencyHitsVhfUhfBands(): void
    {
        $this->assertSame('6m',   HamRadio::bandForFrequency(50.150));
        $this->assertSame('2m',   HamRadio::bandForFrequency(144.300));
        $this->assertSame('2m',   HamRadio::bandForFrequency(147.500));
        $this->assertSame('70cm', HamRadio::bandForFrequency(433.500));
        // 446 MHz is allocated to amateur use in some regions (e.g. USA's
        // 70cm runs 420-450) but falls outside the Malaysian 430-440
        // window we resolve against, so the lookup correctly misses.
        $this->assertNull(HamRadio::bandForFrequency(446.000));
    }

    public function testBandForFrequencyHandlesEdges(): void
    {
        // Inclusive low edge
        $this->assertSame('20m', HamRadio::bandForFrequency(14.0));
        // Inclusive high edge
        $this->assertSame('20m', HamRadio::bandForFrequency(14.35));
        // Just below the band — should miss
        $this->assertNull(HamRadio::bandForFrequency(13.999));
        // Between bands — should miss
        $this->assertNull(HamRadio::bandForFrequency(16.0));
    }

    public function testBandForFrequencyHandlesBadInputGracefully(): void
    {
        $this->assertNull(HamRadio::bandForFrequency(null));
        $this->assertNull(HamRadio::bandForFrequency(''));
        $this->assertNull(HamRadio::bandForFrequency(0));
        $this->assertNull(HamRadio::bandForFrequency(-1.0));
        // Beyond the highest band defined
        $this->assertNull(HamRadio::bandForFrequency(99999.0));
    }

    public function testBandForFrequencyAcceptsStringInput(): void
    {
        // Form posts arrive as strings — the helper must accept them
        // without the caller having to pre-cast.
        $this->assertSame('40m', HamRadio::bandForFrequency('7.074'));
        $this->assertSame('20m', HamRadio::bandForFrequency('14.225'));
    }
}
