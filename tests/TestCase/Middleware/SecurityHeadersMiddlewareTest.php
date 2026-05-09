<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\SecurityHeadersMiddleware;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\Response;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testSetsExpectedHeaders(): void
    {
        $mw = new SecurityHeadersMiddleware();
        $req = ServerRequestFactory::fromGlobals();
        $handler = new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $r): \Psr\Http\Message\ResponseInterface
            { return new Response(); }
        };
        $resp = $mw->process($req, $handler);
        $this->assertSame('DENY', $resp->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $resp->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $resp->getHeaderLine('Referrer-Policy'));
        $this->assertNotEmpty($resp->getHeaderLine('Content-Security-Policy'));
    }
}
