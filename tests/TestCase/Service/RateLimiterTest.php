<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\RateLimiter;
use Cake\TestSuite\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/eqsl-rl-' . uniqid();
        mkdir($this->dir, 0o775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testAllowsUnderLimitDeniesOver(): void
    {
        $rl = new RateLimiter($this->dir);
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($rl->hit('test', 'abc', limit: 3, windowSeconds: 60));
        }
        $this->assertFalse($rl->hit('test', 'abc', limit: 3, windowSeconds: 60));
    }

    public function testWindowResetsAfterTimeout(): void
    {
        $rl = new RateLimiter($this->dir, clock: fn() => 1000);
        $this->assertTrue($rl->hit('a', 'k', 1, 60));
        $this->assertFalse($rl->hit('a', 'k', 1, 60));
        $rl2 = new RateLimiter($this->dir, clock: fn() => 1100); // +100s
        $this->assertTrue($rl2->hit('a', 'k', 1, 60));
    }
}
