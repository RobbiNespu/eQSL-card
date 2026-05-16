<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * M5 T18 — PWA manifest served via Cake controller (not as a static file).
 *
 * Why dynamic? On a subfolder deploy (e.g. tools.example.com/qsl/) the
 * static manifest's "/img/icon-192.png" / "/qsos/quick" URLs resolve at
 * the wrong host root. BasePathMiddleware rewrites HTML responses but
 * not application/manifest+json, so we generate the manifest in PHP
 * where $this->request->getAttribute('webroot') has the correct prefix.
 *
 * Route: GET /manifest.webmanifest → manifest()
 * (Declared in config/routes.php inside the auth-required scope so
 *  the auth middleware is loaded — but the action itself is open to
 *  guests, see beforeFilter.)
 */
class PwaController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        // Manifest must load before login — browsers fetch it without
        // credentials when deciding installability.
        $this->Authentication->allowUnauthenticated(['manifest']);
    }

    public function manifest(): \Cake\Http\Response
    {
        $webroot = rtrim((string)$this->request->getAttribute('webroot', '/'), '/');
        // webroot is '/' for root deploys → '' here; '/qsl' for subfolder.
        // start_url, scope, and icon src all need the prefix prepended.

        $payload = [
            'name'             => 'eQSL Card',
            'short_name'       => 'eQSL',
            'description'      => 'Generate and share electronic QSL cards from your QSO log. Built for amateur radio operators.',
            'start_url'        => $webroot . '/qsos/quick',
            'scope'            => $webroot . '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#ffffff',
            'theme_color'      => '#059669',
            'lang'             => 'en',
            'icons'            => [
                [
                    'src'     => $webroot . '/img/icon-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src'     => $webroot . '/img/icon-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ];

        return $this->response
            ->withType('application/manifest+json')
            ->withStringBody(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
