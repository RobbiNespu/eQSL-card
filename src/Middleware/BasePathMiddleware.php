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
    /**
     * Rewrite absolute-path URLs in the response to include the deploy base path.
     *
     * Rewrites HTML attribute values (href, action, src, formaction, data-url)
     * and bare fetch() calls whose paths start with `/` but not `//`. Also
     * rewrites the Location header on 3xx responses. No-op when webroot is `/`
     * (root deploy) or when the response is non-HTML.
     *
     * @param \Psr\Http\Message\ServerRequestInterface  $request Incoming request.
     * @param \Psr\Http\Server\RequestHandlerInterface  $handler Next middleware handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $prefix = trim((string)$request->getAttribute('webroot', '/'), '/');
        if ($prefix === '') {
            return $response;
        }

        // ----- Redirect Location header (3xx responses) -----
        // CakePHP's Controller::redirect('/foo') sends a bare /foo in the
        // Location header without prefixing App.base. Under a subfolder
        // deploy that lands the browser at the wrong path. Rewrite it
        // here. Skip protocol-relative //host and fully-qualified URLs.
        $status = $response->getStatusCode();
        if ($status >= 300 && $status < 400 && $response->hasHeader('Location')) {
            $loc = $response->getHeaderLine('Location');
            $needsPrefix = $loc !== ''
                && $loc[0] === '/'
                && !str_starts_with($loc, '//')
                && !str_starts_with($loc, '/' . $prefix . '/')
                && $loc !== '/' . $prefix;
            if ($needsPrefix) {
                $response = $response->withHeader('Location', '/' . $prefix . $loc);
            }
        }

        // ----- HTML body rewrites (text/html responses) -----
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
