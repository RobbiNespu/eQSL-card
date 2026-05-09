<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\InstallationCheckMiddleware;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\Response;
use Psr\Http\Server\RequestHandlerInterface;

final class InstallationCheckMiddlewareTest extends TestCase
{
    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockFile = sys_get_temp_dir() . '/eqsl-installed-' . uniqid() . '.lock';
    }

    protected function tearDown(): void
    {
        @unlink($this->lockFile);
        parent::tearDown();
    }

    public function testRedirectsToInstallWhenLockMissing(): void
    {
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/some-page']);
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame('/install', $resp->getHeaderLine('Location'));
    }

    public function testAllowsInstallRoutesWhenLockMissing(): void
    {
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/install/database']);
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function testPassesThroughWhenLockExists(): void
    {
        touch($this->lockFile);
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/some-page']);
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(200, $resp->getStatusCode());
    }

    private function makeHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $r): \Psr\Http\Message\ResponseInterface
            {
                return new Response('php://memory', 200);
            }
        };
    }
}
