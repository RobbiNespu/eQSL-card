<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\BasePathMiddleware;
use Cake\Http\ServerRequest;
use Cake\Http\Response;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class BasePathMiddlewareTest extends TestCase
{
    private function dispatch(string $webroot, string $body, string $contentType = 'text/html; charset=utf-8'): string
    {
        $request = (new ServerRequest(['url' => '/foo']))->withAttribute('webroot', $webroot);
        $handler = new class($body, $contentType) implements RequestHandlerInterface {
            public function __construct(private string $body, private string $ctype) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())
                    ->withHeader('Content-Type', $this->ctype)
                    ->withStringBody($this->body);
            }
        };
        $response = (new BasePathMiddleware())->process($request, $handler);
        return (string)$response->getBody();
    }

    public function testRootDeployIsNoOp(): void
    {
        $html = '<a href="/dashboard">D</a>';
        $this->assertSame($html, $this->dispatch('/', $html));
    }

    public function testEmptyWebrootIsNoOp(): void
    {
        $html = '<a href="/dashboard">D</a>';
        $this->assertSame($html, $this->dispatch('', $html));
    }

    public function testRewritesHrefAndActionAndSrc(): void
    {
        $html = '<a href="/dashboard">D</a><form action="/login"><img src="/logo.png"></form>';
        $out = $this->dispatch('/qsl/', $html);
        $this->assertStringContainsString('href="/qsl/dashboard"', $out);
        $this->assertStringContainsString('action="/qsl/login"', $out);
        $this->assertStringContainsString('src="/qsl/logo.png"', $out);
    }

    public function testRewritesFormactionAndDataUrl(): void
    {
        $html = '<button formaction="/save">S</button><div data-url="/api/x"></div>';
        $out = $this->dispatch('/qsl/', $html);
        $this->assertStringContainsString('formaction="/qsl/save"', $out);
        $this->assertStringContainsString('data-url="/qsl/api/x"', $out);
    }

    public function testRewritesSingleQuotedAttributes(): void
    {
        $html = "<a href='/dashboard'>D</a>";
        $out = $this->dispatch('/qsl/', $html);
        $this->assertStringContainsString("href='/qsl/dashboard'", $out);
    }

    public function testRewritesFetchCalls(): void
    {
        $html = "<script>fetch('/api/sync'); fetch(\"/api/x\")</script>";
        $out = $this->dispatch('/qsl/', $html);
        $this->assertStringContainsString("fetch('/qsl/api/sync')", $out);
        $this->assertStringContainsString('fetch("/qsl/api/x")', $out);
    }

    public function testDoesNotRewriteProtocolRelativeUrls(): void
    {
        $html = '<a href="//cdn.example.com/x.js">CDN</a>';
        $this->assertSame($html, $this->dispatch('/qsl/', $html));
    }

    public function testDoesNotRewriteAbsoluteUrls(): void
    {
        $html = '<a href="https://example.com/x">X</a>';
        $this->assertSame($html, $this->dispatch('/qsl/', $html));
    }

    public function testDoesNotRewriteFragmentsOrSchemeUris(): void
    {
        $html = '<a href="#top">Top</a> <a href="mailto:a@b.com">M</a>';
        $this->assertSame($html, $this->dispatch('/qsl/', $html));
    }

    public function testIdempotentOnAlreadyPrefixedPaths(): void
    {
        $html = '<a href="/qsl/already">A</a>';
        $this->assertSame($html, $this->dispatch('/qsl/', $html));
    }

    public function testSkipsNonHtmlResponses(): void
    {
        $json = '{"redirect": "/dashboard"}';
        $this->assertSame($json, $this->dispatch('/qsl/', $json, 'application/json'));
    }

    public function testEmptyBodyIsNoOp(): void
    {
        $this->assertSame('', $this->dispatch('/qsl/', ''));
    }

    /**
     * Helper: dispatch with a custom-built 302 response so we can assert
     * the Location-header rewrite branch added for the InstallController
     * `return $this->redirect('/install/migrate')` case.
     */
    private function dispatchRedirect(string $webroot, string $location, int $status = 302): ResponseInterface
    {
        $request = (new ServerRequest(['url' => '/foo']))->withAttribute('webroot', $webroot);
        $handler = new class($status, $location) implements RequestHandlerInterface {
            public function __construct(private int $status, private string $location) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())
                    ->withStatus($this->status)
                    ->withHeader('Location', $this->location);
            }
        };
        return (new BasePathMiddleware())->process($request, $handler);
    }

    public function testRewritesLocationHeaderOnRedirect(): void
    {
        $resp = $this->dispatchRedirect('/qsl/', '/install/migrate');
        $this->assertSame('/qsl/install/migrate', $resp->getHeaderLine('Location'));
    }

    public function testLocationHeaderRewriteIsIdempotent(): void
    {
        $resp = $this->dispatchRedirect('/qsl/', '/qsl/install/migrate');
        $this->assertSame('/qsl/install/migrate', $resp->getHeaderLine('Location'));
    }

    public function testLocationHeaderRewriteSkipsAbsoluteUrls(): void
    {
        $resp = $this->dispatchRedirect('/qsl/', 'https://other.example.com/x');
        $this->assertSame('https://other.example.com/x', $resp->getHeaderLine('Location'));
    }

    public function testLocationHeaderRewriteSkipsProtocolRelative(): void
    {
        $resp = $this->dispatchRedirect('/qsl/', '//cdn.example.com/x');
        $this->assertSame('//cdn.example.com/x', $resp->getHeaderLine('Location'));
    }

    public function testLocationHeaderRewriteNoOpAtRootDeploy(): void
    {
        $resp = $this->dispatchRedirect('/', '/install/migrate');
        $this->assertSame('/install/migrate', $resp->getHeaderLine('Location'));
    }
}
