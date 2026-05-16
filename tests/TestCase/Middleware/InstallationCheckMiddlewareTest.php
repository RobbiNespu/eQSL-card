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

    public function testRedirectsInstallPrefixLookalike(): void
    {
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/install-evil']);
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame('/install', $resp->getHeaderLine('Location'));
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

    public function testInstallRoutes404AfterLockExists(): void
    {
        touch($this->lockFile);
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/install/admin']);
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testInstallIndexAlso404sAfterLock(): void
    {
        touch($this->lockFile);
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/install']);
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(404, $resp->getStatusCode());
    }

    /**
     * Subfolder-deploy regression tests. The middleware previously
     * compared raw URI paths against bare "/install" strings without
     * accounting for the deploy base, so on a /qsl subfolder deploy
     * /qsl/install was NOT recognised as an install path — install
     * page POSTs were redirected to /install (no prefix) and bounced
     * to the parent host root.
     */

    public function testSubfolderRecognisesInstallPathFromBase(): void
    {
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/qsl/install/database'])
            ->withAttribute('webroot', '/qsl/');
        $handler = $this->makeHandler();

        // Lock missing + install path → should NOT redirect, should
        // pass through to the handler.
        $resp = $mw->process($req, $handler);
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function testSubfolderRedirectIncludesBaseInLocation(): void
    {
        $mw = new InstallationCheckMiddleware($this->lockFile);
        // Lock missing, navigating to a non-install path on subfolder.
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/qsl/dashboard'])
            ->withAttribute('webroot', '/qsl/');
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(302, $resp->getStatusCode());
        // Without the fix, this would be "/install" — bouncing to the
        // parent host root.
        $this->assertSame('/qsl/install', $resp->getHeaderLine('Location'));
    }

    public function testSubfolderInstallPaths404AfterLock(): void
    {
        touch($this->lockFile);
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/qsl/install/admin'])
            ->withAttribute('webroot', '/qsl/');
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testSubfolderHealthCheckPassesThrough(): void
    {
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/qsl/health'])
            ->withAttribute('webroot', '/qsl/');
        $handler = $this->makeHandler();

        // Lock missing + health endpoint → pass through (used for
        // deployment monitoring before install completes).
        $resp = $mw->process($req, $handler);
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function testRootDeployBehaviourUnchanged(): void
    {
        // Sanity: webroot='/' (or absent) keeps the original behaviour.
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/dashboard'])
            ->withAttribute('webroot', '/');
        $handler = $this->makeHandler();
        $resp = $mw->process($req, $handler);
        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame('/install', $resp->getHeaderLine('Location'));
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
