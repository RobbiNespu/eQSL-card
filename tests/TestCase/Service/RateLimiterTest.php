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

    public function testWrittenFileIsWorldWritable(): void
    {
        // Regression: tests run as root inside docker leave root-owned bucket
        // files in the production rate_limits dir. The www-data php-fpm
        // worker then can't overwrite them and stale stamps throttle real
        // logins. `atomicWrite` chmod's its tmp file to 0o666 before the
        // rename so the next process (different uid) can replace it cleanly.
        $rl = new RateLimiter($this->dir);
        $rl->hit('test', 'abc', limit: 5, windowSeconds: 60);
        $file = $this->dir . '/' . hash('sha256', 'test:abc');
        $this->assertFileExists($file);
        $perm = fileperms($file) & 0o777;
        $this->assertSame(0o666, $perm, sprintf(
            'bucket file should be 0666 so a different-uid caller can overwrite, got %o',
            $perm
        ));
    }

    public function testCanOverwriteFileWithRestrictivePermissions(): void
    {
        // Simulate a bucket file written by a different uid (i.e. 0o644 with
        // only the owner allowed to write). Our atomicWrite path uses a temp
        // file + rename, so it should still be able to replace it.
        $file = $this->dir . '/' . hash('sha256', 'foo:bar');
        file_put_contents($file, '999');
        chmod($file, 0o644);

        $rl = new RateLimiter($this->dir, clock: fn() => 2000);
        $this->assertTrue($rl->hit('foo', 'bar', limit: 5, windowSeconds: 60));
        $this->assertSame('2000', file_get_contents($file));
    }
}
