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
        return $response
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Content-Security-Policy', $this->csp());
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
        // Tailwind Play CDN (cdn.tailwindcss.com) runs the Tailwind JIT
        // compiler in the browser — it loads as a script AND injects
        // generated CSS into a <style> tag on the fly. Both its
        // script-src and the inline style-src allowance are needed until
        // we move to a pre-compiled production CSS build (Node build
        // off the dev machine; ship the dist .css alongside the app).
        return implode('; ', [
            "default-src 'self'",
            "img-src 'self' data: blob:",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.tailwindcss.com",
            "font-src 'self' https://cdn.jsdelivr.net",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
