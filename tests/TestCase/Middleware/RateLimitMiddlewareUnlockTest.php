<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\RateLimitMiddleware;
use App\Service\RateLimiter;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\Response;

final class RateLimitMiddlewareUnlockTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir() . '/eqsl-rl-unlock-' . uniqid();
        mkdir($this->cacheDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->cacheDir . '/*') ?: []);
        @rmdir($this->cacheDir);
        parent::tearDown();
    }

    private function makeMiddleware(): RateLimitMiddleware
    {
        return new RateLimitMiddleware(new RateLimiter($this->cacheDir));
    }

    private function makeRequest(string $method, string $path): \Psr\Http\Message\ServerRequestInterface
    {
        return ServerRequestFactory::fromGlobals(['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '203.0.113.5']);
    }

    private function passThruHandler(): \Psr\Http\Server\RequestHandlerInterface
    {
        return new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $r): \Psr\Http\Message\ResponseInterface
            { return new Response('php://memory', 200); }
        };
    }

    public function testFiveAttemptsAllowed(): void
    {
        $mw = $this->makeMiddleware();
        $slug = str_repeat('a', 43);
        for ($i = 0; $i < 5; $i++) {
            $resp = $mw->process($this->makeRequest('POST', "/qsl/{$slug}/unlock"), $this->passThruHandler());
            $this->assertSame(200, $resp->getStatusCode(), "attempt #{$i} should pass");
        }
    }

    public function testSixthAttemptIs429(): void
    {
        $mw = $this->makeMiddleware();
        $slug = str_repeat('b', 43);
        for ($i = 0; $i < 5; $i++) {
            $mw->process($this->makeRequest('POST', "/qsl/{$slug}/unlock"), $this->passThruHandler());
        }
        $resp = $mw->process($this->makeRequest('POST', "/qsl/{$slug}/unlock"), $this->passThruHandler());
        $this->assertSame(429, $resp->getStatusCode());
    }

    public function testDifferentSlugsHaveSeparateBuckets(): void
    {
        $mw = $this->makeMiddleware();
        $slugA = str_repeat('a', 43);
        $slugB = str_repeat('b', 43);
        for ($i = 0; $i < 5; $i++) {
            $mw->process($this->makeRequest('POST', "/qsl/{$slugA}/unlock"), $this->passThruHandler());
        }
        $resp = $mw->process($this->makeRequest('POST', "/qsl/{$slugB}/unlock"), $this->passThruHandler());
        $this->assertSame(200, $resp->getStatusCode(), 'separate slug should have its own counter');
    }

    public function testGetIsNotRateLimited(): void
    {
        $mw = $this->makeMiddleware();
        $slug = str_repeat('c', 43);
        for ($i = 0; $i < 100; $i++) {
            $resp = $mw->process($this->makeRequest('GET', "/qsl/{$slug}/unlock"), $this->passThruHandler());
            $this->assertSame(200, $resp->getStatusCode());
        }
    }
}
