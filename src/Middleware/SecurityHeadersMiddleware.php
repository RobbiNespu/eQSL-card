<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Skip the framing-related headers for DebugKit's own routes.
        // The toolbar embeds itself in the page via a same-origin iframe;
        // X-Frame-Options: DENY and CSP frame-ancestors 'none' both block
        // that, leaving DebugKit invisible in dev. DebugKit URLs only
        // exist when debug=true (see Application.php's conditional load),
        // so this exception cannot widen production attack surface.
        $path = $request->getUri()->getPath();
        $isDebugKit = str_starts_with($path, '/debug-kit/') || str_starts_with($path, '/debug_kit/');

        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (!$isDebugKit) {
            $response = $response
                ->withHeader('X-Frame-Options', 'DENY')
                ->withHeader('Content-Security-Policy', $this->csp());
        }

        return $response;
    }

    private function csp(): string
    {
        // Two pragmatic CSP relaxations on script-src:
        //
        //   'unsafe-inline' — required by CakePHP's Form->postLink() helper,
        //     which emits inline onclick handlers (used in Cards/view, admin
        //     templates, etc.).
        //   'unsafe-eval'   — required by Alpine.js v3, which uses
        //     new Function() to evaluate directive expressions (x-show,
        //     @click, x-text, ...). Without it, every Alpine page is dead.
        //
        // Backlog: switching to Alpine's CSP-friendly build (alpinejs/csp)
        // and rewriting postLink call sites as explicit <form> + <button>
        // would let us drop both. Tracked but not blocking v1.
        // 'unsafe-inline' on style-src is still required for the
        // handful of inline style attrs in templates. 'unsafe-inline' +
        // 'unsafe-eval' on script-src are required by CakePHP
        // Form->postLink (inline onclick) and Alpine (new Function
        // evaluation) respectively. cdn.jsdelivr.net stays for Inter +
        // Geist Mono web fonts and Alpine itself.
        return implode('; ', [
            "default-src 'self'",
            "img-src 'self' data: blob:",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net",
            "font-src 'self' https://cdn.jsdelivr.net",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
