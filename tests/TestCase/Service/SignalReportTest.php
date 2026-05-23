<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\SignalReport;
use Cake\TestSuite\TestCase;

final class SignalReportTest extends TestCase
{
    public function testParsesStrengthFromRst(): void
    {
        $this->assertSame(9, SignalReport::strength('59'));
        $this->assertSame(7, SignalReport::strength('57'));
    }

    public function testParsesRsAndRstn(): void
    {
        $this->assertSame(9, SignalReport::strength('599')); // CW RST
        $this->assertSame(5, SignalReport::strength('55'));  // phone RS
    }

    public function testNullForUnparseable(): void
    {
        $this->assertNull(SignalReport::strength(''));
        $this->assertNull(SignalReport::strength(null));
        $this->assertNull(SignalReport::strength('abc'));
    }

    public function testDistributionBuckets(): void
    {
        $dist = SignalReport::distribution(['59', '57', '57', 'xx']);
        $this->assertSame(2, $dist[7]);
        $this->assertSame(1, $dist[9]);
        $this->assertArrayHasKey('unknown', $dist);
        $this->assertSame(1, $dist['unknown']);
    }
}
