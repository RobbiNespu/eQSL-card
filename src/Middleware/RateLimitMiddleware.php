<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Service\RateLimiter;
use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var (\Closure(): bool)|null */
    private ?\Closure $bypassEnabled;

    /**
     * `$bypassEnabled` is an optional closure that reports whether the
     * private-IP bypass is currently enabled. Production wiring
     * (Application::middleware) passes a closure that reads the
     * `rate_limit_private_ip_bypass` app_setting. Existing tests + the
     * install-time middleware order pass `null` and the bypass defaults
     * to ON — which is the safe fallback while app_settings isn't readable
     * (early /install/* requests before the table exists).
     *
     * Why a closure and not an AppSettings instance: AppSettings is `final`
     * so it can't be subclassed for stubs, and a closure makes the seam
     * narrow + easy to fake.
     */
    public function __construct(
        private RateLimiter $limiter,
        ?callable $bypassEnabled = null,
    ) {
        $this->bypassEnabled = $bypassEnabled !== null
            ? \Closure::fromCallable($bypassEnabled)
            : null;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $method = strtoupper($request->getMethod());
        $ip = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');

        // Non-public IP bypass. Covers:
        //   - loopback (127.0.0.0/8, ::1)
        //   - private RFC1918 (10/8, 172.16/12, 192.168/16)
        //   - IPv6 ULA + link-local (fc00::/7, fe80::/10)
        // The user-visible request comes from the host bridge interface in
        // Docker (typically 172.x.x.x — NOT 127.0.0.1), so a literal-string
        // whitelist would miss the dev case the user actually has. Any
        // attacker reaching this endpoint from a non-public IP is already
        // inside the trust boundary; in production deployments REMOTE_ADDR
        // is the public client IP and the rule fires normally.
        //
        // The bypass can be turned OFF from /admin/settings (or by SQL —
        // `UPDATE app_settings SET value='false' WHERE \`key\`=
        // 'rate_limit_private_ip_bypass';`) if a deployment doesn't want
        // private-IP traffic to skip throttling.
        if (!self::isPublicIp($ip) && $this->privateIpBypassEnabled()) {
            return $handler->handle($request);
        }

        // Exact-match rules
        $exactRules = [
            '/generate' => ['limit' => 10, 'window' => 3600, 'method' => 'POST'],
            '/login'    => ['limit' => 5,  'window' => 900,  'method' => 'POST'],
        ];
        if (isset($exactRules[$path]) && $method === $exactRules[$path]['method']) {
            $rule = $exactRules[$path];
            if (!$this->limiter->hit($path, hash('sha256', $ip), $rule['limit'], $rule['window'])) {
                return (new Response())->withStatus(429)->withStringBody('Too many requests. Try again later.');
            }
        }

        // Regex-match rules. Key is the action name, value is regex + limit.
        // Each rule carries its own `message` so callers get context-appropriate
        // 429 text instead of a single hardcoded string for all rules.
        // `identifier`: share_unlock uses capture group 1 (the token); net_live_feed
        // has no capture group, so it intentionally falls through to an IP hash —
        // scraper throttling is per-IP, not per-slug.
        $regexRules = [
            'share_unlock' => [
                'pattern' => '#^/qsl/([A-Za-z0-9_\-]{43})/unlock$#',
                'limit' => 5, 'window' => 900, 'method' => 'POST',
                'message' => 'Too many unlock attempts for this share. Try again in 15 minutes.',
            ],
            // M6 T16 — public net feed. Keyed by IP hash to throttle scrapers.
            // 60 GETs / minute is generous for a 4-second polling interval but
            // blocks bulk scrapers hitting the endpoint without the JS poller.
            'net_live_feed' => [
                'pattern' => '#^/net/[A-Za-z0-9_\-]+/live(?:\.json)?$#',
                'limit' => 60, 'window' => 60, 'method' => 'GET',
                'message' => 'Too many requests. Please slow down.',
            ],
        ];
        foreach ($regexRules as $action => $rule) {
            if ($method === $rule['method'] && preg_match($rule['pattern'], $path, $m)) {
                $identifier = $m[1] ?? hash('sha256', $ip);
                if (!$this->limiter->hit($action, $identifier, $rule['limit'], $rule['window'])) {
                    return (new Response())->withStatus(429)->withStringBody($rule['message']);
                }
            }
        }

        return $handler->handle($request);
    }

    /**
     * True iff `$ip` is a valid, globally-routable IP address. Anything that
     * fails `FILTER_VALIDATE_IP` with the public-only flags (private +
     * reserved excluded) — including 127.*, ::1, 10/8, 172.16/12, 192.168/16,
     * fc00::/7, fe80::/10 — returns false. Invalid strings (empty, "0.0.0.0",
     * garbage) also return false; we treat unparseable IPs as non-public,
     * which is the safer default for the whitelist branch.
     */
    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * Invoke the injected toggle closure. Defaults to true (bypass enabled)
     * when no closure is wired (e.g. legacy test construction) or when the
     * closure throws — if the DB is unreachable or the table doesn't exist
     * yet (pre-install), we fall back to the default rather than 500'ing
     * every incoming request.
     */
    private function privateIpBypassEnabled(): bool
    {
        if ($this->bypassEnabled === null) {
            return true;
        }
        try {
            return (bool)($this->bypassEnabled)();
        } catch (\Throwable) {
            return true;
        }
    }
}
