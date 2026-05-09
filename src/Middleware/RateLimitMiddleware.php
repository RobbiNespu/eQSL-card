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
        $ip = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');

        $rules = [
            '/generate' => ['limit' => 10, 'window' => 3600, 'method' => 'POST'],
            '/login'    => ['limit' => 5,  'window' => 900,  'method' => 'POST'],
        ];

        if (isset($rules[$path]) && strtoupper($request->getMethod()) === $rules[$path]['method']) {
            $rule = $rules[$path];
            if (!$this->limiter->hit($path, hash('sha256', $ip), $rule['limit'], $rule['window'])) {
                return (new Response())->withStatus(429)->withStringBody('Too many requests. Try again later.');
            }
        }

        return $handler->handle($request);
    }
}
