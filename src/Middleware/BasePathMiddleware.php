<?php
declare(strict_types=1);

namespace App\Middleware;

use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rewrites absolute-path URLs in HTML responses so the app survives
 * being deployed under a subfolder (e.g. https://tools.example.com/qsl).
 *
 * Templates throughout the codebase use raw `href="/dashboard"` etc.
 * instead of `$this->Url->build('/dashboard')`. At a root deploy this
 * works fine — the browser resolves `/dashboard` against the host. Under
 * a subfolder deploy, `/dashboard` would bypass the subfolder entirely
 * and either 404 or hit a different app at `example.com/dashboard`.
 *
 * Instead of touching ~78 template files this middleware reads the
 * effective webroot (auto-detected by RoutingMiddleware from SCRIPT_NAME,
 * or explicitly set via `App.base` in `config/app_local.php`) and
 * rewrites the response body once on the way out. When the webroot is
 * `/` (root deploy), the middleware is a no-op — single equality check,
 * no regex run.
 *
 * What gets rewritten:
 *   href="/foo"      →  href="/qsl/foo"
 *   action="/foo"    →  action="/qsl/foo"
 *   src="/foo"       →  src="/qsl/foo"
 *   formaction="/x"  →  formaction="/qsl/x"
 *   data-url="/x"    →  data-url="/qsl/x"   (Alpine.js convention)
 *   fetch('/api')    →  fetch('/qsl/api')   (inline JS)
 *
 * Skipped:
 *   - href="//host/x"      protocol-relative — already pinned to a host
 *   - href="http(s)://..." fully-qualified — already correct
 *   - href="#anchor"       fragment-only
 *   - href="mailto:/tel:"  scheme URIs (don't start with /)
 *   - Non-text/html responses (JSON, images, PDFs) — Content-Type guard
 *   - Paths already starting with the prefix (idempotent re-runs)
 */
class BasePathMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $prefix = trim((string)$request->getAttribute('webroot', '/'), '/');
        if ($prefix === '') {
            return $response;
        }

        $ctype = strtolower($response->getHeaderLine('Content-Type'));
        if ($ctype !== '' && !str_contains($ctype, 'text/html')) {
            return $response;
        }

        $body = (string)$response->getBody();
        if ($body === '') {
            return $response;
        }

        // Skip paths that already start with the prefix so the rewrite
        // is idempotent (e.g. if a template DID use $this->Url->build).
        $skip = '(?!' . preg_quote($prefix, '~') . '/)';

        // attr="/x" (double-quoted, attribute names case-insensitive)
        $body = (string)preg_replace(
            '~\b(href|action|src|formaction|data-url)="/(?!/)' . $skip . '~i',
            '$1="/' . $prefix . '/',
            $body
        );
        // attr='/x' (single-quoted)
        $body = (string)preg_replace(
            "~\\b(href|action|src|formaction|data-url)='/(?!/)" . $skip . "~i",
            '$1=\'/' . $prefix . '/',
            $body
        );
        // fetch('/x') / fetch("/x")
        $body = (string)preg_replace(
            '~\bfetch\(([\"\'])/(?!/)' . $skip . '~',
            'fetch($1/' . $prefix . '/',
            $body
        );

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $body);
        rewind($stream);

        return $response->withBody(new Stream($stream));
    }
}
