<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Service\OperationLog;
use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Gate the app behind the install wizard until config/installed.lock
 * exists. Once installed, /install/* returns 404 (wizard is dead).
 *
 * Webroot-aware: reads the request's `webroot` attribute so subfolder
 * deploys (e.g. /qsl) correctly recognise `/qsl/install` as an install
 * path AND emit the right Location URL on redirect.
 */
final class InstallationCheckMiddleware implements MiddlewareInterface
{
    /**
     * @param string $lockFilePath Absolute path to the installation lock file
     *                             (e.g. CONFIG . 'installed.lock').
     */
    public function __construct(private string $lockFilePath)
    {
    }

    /**
     * Gate the request against the installation lock file.
     *
     * - Not installed + non-install path → 302 redirect to /install.
     * - Not installed + install/health path → pass through.
     * - Installed + install path → 404.
     * - Installed + any other path → pass through.
     *
     * @param \Psr\Http\Message\ServerRequestInterface  $request Incoming request.
     * @param \Psr\Http\Server\RequestHandlerInterface  $handler Next middleware handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Strip the deploy base path so the comparison works on both
        // root deploys (webroot='/') and subfolder deploys (webroot='/qsl/').
        // The webroot attribute is set by Cake's ServerRequestFactory
        // before any middleware runs, so it's safe to read here even
        // though RoutingMiddleware hasn't fired yet.
        $base = rtrim((string)$request->getAttribute('webroot', '/'), '/');
        $rawPath = $request->getUri()->getPath();
        $scopedPath = ($base !== '' && str_starts_with($rawPath, $base))
            ? substr($rawPath, strlen($base))
            : $rawPath;
        if ($scopedPath === '') {
            $scopedPath = '/';
        }

        $installed = file_exists($this->lockFilePath);
        $isInstallPath = $scopedPath === '/install' || str_starts_with($scopedPath, '/install/');

        if ($installed) {
            if ($isInstallPath) {
                return (new Response())->withStatus(404);
            }
            return $handler->handle($request);
        }

        // Not installed
        if ($isInstallPath || $scopedPath === '/health') {
            return $handler->handle($request);
        }
        // Redirect target uses the deploy base so subfolder users land
        // at /qsl/install instead of bouncing to the parent host root.
        OperationLog::event('install.redirect', ['path' => $rawPath, 'target' => $base . '/install']);
        return (new Response())->withStatus(302)->withHeader('Location', $base . '/install');
    }
}
