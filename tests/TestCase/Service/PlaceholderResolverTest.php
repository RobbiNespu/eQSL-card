<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PlaceholderResolver;
use Cake\TestSuite\TestCase;

final class PlaceholderResolverTest extends TestCase
{
    public function testSimpleField(): void
    {
        $r = new PlaceholderResolver();
        $this->assertSame('W1AW', $r->resolve('{callsign}', ['callsign' => 'W1AW']));
    }

    public function testDateFormatting(): void
    {
        $r = new PlaceholderResolver();
        $out = $r->resolve('{qso_datetime_utc:Y-m-d}', ['qso_datetime_utc' => '2026-05-09T14:32:00Z']);
        $this->assertSame('2026-05-09', $out);
    }

    public function testMultipleFieldsInOneString(): void
    {
        $r = new PlaceholderResolver();
        $out = $r->resolve('{callsign} on {band}', ['callsign' => 'W1AW', 'band' => '20m']);
        $this->assertSame('W1AW on 20m', $out);
    }

    public function testMissingFieldRendersEmpty(): void
    {
        $r = new PlaceholderResolver();
        $this->assertSame('', (new PlaceholderResolver())->resolve('{nope}', []));
    }

    public function testCustomLiteralPassesThrough(): void
    {
        $r = new PlaceholderResolver();
        $this->assertSame('Hello W1AW', $r->resolve('Hello {callsign}', ['callsign' => 'W1AW']));
    }
}
