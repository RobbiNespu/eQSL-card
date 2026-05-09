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
    public function __construct(private RateLimiter $limiter)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $method = strtoupper($request->getMethod());
        $ip = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');

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
        $regexRules = [
            'share_unlock' => [
                'pattern' => '#^/qsl/([A-Za-z0-9_\-]{43})/unlock$#',
                'limit' => 5, 'window' => 900, 'method' => 'POST',
            ],
        ];
        foreach ($regexRules as $action => $rule) {
            if ($method === $rule['method'] && preg_match($rule['pattern'], $path, $m)) {
                $identifier = $m[1] ?? hash('sha256', $ip);
                if (!$this->limiter->hit($action, $identifier, $rule['limit'], $rule['window'])) {
                    return (new Response())->withStatus(429)->withStringBody('Too many unlock attempts for this share. Try again in 15 minutes.');
                }
            }
        }

        return $handler->handle($request);
    }
}
