<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Cache\Cache;

/**
 * Admin upgrade runner (M2-T18).
 *
 * After uploading a new release zip via FTP, an admin visits this page to
 * apply any pending Phinx migrations and clear all configured cache engines
 * in a single click. This is the lightweight alternative to dropping into a
 * shell on shared hosting where SSH is not available.
 *
 * Access is gated by `beforeFilter()` — anonymous users get redirected to
 * `/login` by the Authentication middleware, and authenticated non-admins
 * receive a 403 Forbidden.
 */
class UpgradeController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Anonymous users: the AuthenticationComponent's `startup()` (which
        // runs AFTER beforeFilter) will throw `UnauthenticatedException` when
        // no identity is present, and the AuthenticationMiddleware turns that
        // into a redirect to `/login` per `unauthenticatedRedirect`. We only
        // gate authenticated-but-not-admin users here.
        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            return;
        }

        // The Session authenticator rehydrates the identity from the session
        // payload — which in tests / production is just `['id' => …]`. To make
        // the role check robust against either flavour (rich entity stashed in
        // the session vs. the bare id-only payload), look the user up fresh.
        $userId = $identity->getIdentifier();
        $user = $this->fetchTable('Users')->find()->where(['id' => $userId])->first();
        if (!$user || $user->role !== 'admin') {
            throw new \Cake\Http\Exception\ForbiddenException('Admin only.');
        }
    }

    public function index()
    {
        $migrationsResult = null;

        if ($this->request->is('post')) {
            $migrations = new \Migrations\Migrations(['connection' => 'default']);
            try {
                $migrations->migrate();
                Cache::clearAll();
                $migrationsResult = ['ok' => true, 'message' => 'Migrations applied and caches cleared.'];
            } catch (\Throwable $e) {
                $migrationsResult = ['ok' => false, 'message' => 'Failed: ' . $e->getMessage()];
            }
        }

        $migrations = new \Migrations\Migrations(['connection' => 'default']);
        $statusRows = $migrations->status();

        $this->set([
            'title' => 'Admin · Upgrade',
            'statusRows' => $statusRows,
            'migrationsResult' => $migrationsResult,
        ]);
    }
}
