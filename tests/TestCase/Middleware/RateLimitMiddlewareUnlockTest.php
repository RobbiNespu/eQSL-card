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

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonPublicIpProvider(): iterable
    {
        yield 'IPv4 loopback' => ['127.0.0.1'];
        yield 'IPv6 loopback' => ['::1'];
        yield 'Docker bridge gateway' => ['172.20.0.1'];
        yield 'RFC1918 10/8' => ['10.0.0.42'];
        yield 'RFC1918 192.168/16' => ['192.168.1.100'];
        yield 'IPv6 link-local' => ['fe80::1'];
        yield 'invalid empty' => [''];
        yield 'invalid garbage' => ['not-an-ip'];
    }

    /**
     * @dataProvider nonPublicIpProvider
     */
    public function testNonPublicIpsBypassLoginRateLimit(string $ip): void
    {
        $mw = $this->makeMiddleware();
        // /login is configured at 5/15min — without the bypass, the 11th hit
        // would 429. With the bypass, every hit from a non-public IP passes.
        for ($i = 0; $i < 11; $i++) {
            $req = ServerRequestFactory::fromGlobals([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/login',
                'REMOTE_ADDR' => $ip,
            ]);
            $resp = $mw->process($req, $this->passThruHandler());
            $this->assertSame(200, $resp->getStatusCode(), "non-public IP $ip attempt #$i should not be limited");
        }
    }

    public function testPublicIpStillRateLimitedOnLogin(): void
    {
        $mw = $this->makeMiddleware();
        for ($i = 0; $i < 5; $i++) {
            $req = ServerRequestFactory::fromGlobals([
                'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login', 'REMOTE_ADDR' => '8.8.8.8',
            ]);
            $resp = $mw->process($req, $this->passThruHandler());
            $this->assertSame(200, $resp->getStatusCode());
        }
        $req = ServerRequestFactory::fromGlobals([
            'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login', 'REMOTE_ADDR' => '8.8.8.8',
        ]);
        $resp = $mw->process($req, $this->passThruHandler());
        $this->assertSame(429, $resp->getStatusCode());
    }

    public function testPrivateIpBypassDisabledViaSettings(): void
    {
        // With the toggle OFF, private IPs are throttled like public ones.
        $mw = new \App\Middleware\RateLimitMiddleware(
            new \App\Service\RateLimiter($this->cacheDir),
            static fn(): bool => false,
        );

        for ($i = 0; $i < 5; $i++) {
            $req = ServerRequestFactory::fromGlobals([
                'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login', 'REMOTE_ADDR' => '127.0.0.1',
            ]);
            $resp = $mw->process($req, $this->passThruHandler());
            $this->assertSame(200, $resp->getStatusCode(), "private-IP attempt #$i should pass under the limit");
        }
        $req = ServerRequestFactory::fromGlobals([
            'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login', 'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $resp = $mw->process($req, $this->passThruHandler());
        $this->assertSame(429, $resp->getStatusCode(), '6th private-IP attempt should trip when bypass is OFF');
    }

    public function testSettingsThrowingDefaultsToBypassEnabled(): void
    {
        // If the toggle closure blows up (DB unreachable, missing table
        // during install), the middleware MUST NOT 500 — it falls back to
        // bypass=ON so the admin can recover via the UI / SQL.
        $mw = new \App\Middleware\RateLimitMiddleware(
            new \App\Service\RateLimiter($this->cacheDir),
            static function (): bool { throw new \RuntimeException('simulated DB outage'); },
        );

        for ($i = 0; $i < 11; $i++) {
            $req = ServerRequestFactory::fromGlobals([
                'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login', 'REMOTE_ADDR' => '127.0.0.1',
            ]);
            $resp = $mw->process($req, $this->passThruHandler());
            $this->assertSame(200, $resp->getStatusCode(), "outage fallback should pass attempt #$i");
        }
    }
}
