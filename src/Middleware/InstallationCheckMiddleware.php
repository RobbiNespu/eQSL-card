<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class InstallationCheckMiddleware implements MiddlewareInterface
{
    public function __construct(private string $lockFilePath)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $installed = file_exists($this->lockFilePath);
        $isInstallPath = $path === '/install' || str_starts_with($path, '/install/');

        if ($installed) {
            if ($isInstallPath) {
                return (new Response())->withStatus(404);
            }
            return $handler->handle($request);
        }

        // Not installed
        if ($isInstallPath || $path === '/health') {
            return $handler->handle($request);
        }
        return (new Response())->withStatus(302)->withHeader('Location', '/install');
    }
}
