<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Maidenhead;
use Cake\TestSuite\TestCase;

final class MaidenheadTest extends TestCase
{
    public function testDecodes4CharGrid(): void
    {
        $ll = Maidenhead::toLatLon('OJ02');
        $this->assertNotNull($ll);
        // OJ02 is in West Malaysia; lat roughly 2-4N, lon roughly 100-102E.
        $this->assertGreaterThan(0, $ll['lat']);
        $this->assertLessThan(10, $ll['lat']);
        $this->assertGreaterThan(98, $ll['lon']);
        $this->assertLessThan(104, $ll['lon']);
    }

    public function testDecodes6CharGrid(): void
    {
        $ll = Maidenhead::toLatLon('OJ02wx');
        $this->assertNotNull($ll);
        $this->assertArrayHasKey('lat', $ll);
        $this->assertArrayHasKey('lon', $ll);
    }

    public function testNullForInvalid(): void
    {
        $this->assertNull(Maidenhead::toLatLon(''));
        $this->assertNull(Maidenhead::toLatLon('ZZ'));
        $this->assertNull(Maidenhead::toLatLon('nonsense'));
    }
}
