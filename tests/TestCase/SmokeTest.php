<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use Cake\TestSuite\TestCase;

final class SmokeTest extends TestCase
{
    public function testFrameworkBoots(): void
    {
        $this->assertSame('8.1', substr(PHP_VERSION, 0, 3), 'PHP runtime must be 8.1.x');
        $this->assertTrue(extension_loaded('gd'), 'GD must be available');
        $this->assertTrue(extension_loaded('pdo_sqlite'), 'pdo_sqlite must be available for tests');
    }
}
