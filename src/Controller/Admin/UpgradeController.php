<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\OperationLog;
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
    /** Load the Authentication component required by all Admin controllers. */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    /**
     * Gate access to admin-only actions.
     *
     * Anonymous requests are handled by AuthenticationComponent (redirects to
     * /login). The role check re-fetches the user row fresh — the Session
     * authenticator may have stashed only the bare id-only payload in the
     * session, so we can't rely on a role field being present on the identity.
     *
     * @param \Cake\Event\EventInterface $event The before-filter event.
     * @return void
     * @throws \Cake\Http\Exception\ForbiddenException When the authenticated user is not an admin.
     */
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);

        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            return;
        }

        $userId = $identity->getIdentifier();
        $user = $this->fetchTable('Users')->find()->where(['id' => $userId])->first();
        if (!$user || $user->role !== 'admin') {
            throw new \Cake\Http\Exception\ForbiddenException('Admin only.');
        }
    }

    /**
     * GET/POST /admin/upgrade — show pending migrations and apply them on POST.
     *
     * On POST runs Phinx migrate() and Cache::clearAll() in a single click,
     * covering the shared-hosting case where SSH is unavailable. The result
     * (success or error message) is passed back to the view via $migrationsResult
     * so the page re-renders in place rather than a separate PRG redirect.
     *
     * @return void
     */
    public function index()
    {
        $migrationsResult = null;

        if ($this->request->is('post')) {
            $actorId = $this->Authentication->getIdentity()->getIdentifier();
            $migrations = new \Migrations\Migrations(['connection' => 'default']);
            try {
                $migrations->migrate();
                Cache::clearAll();
                $migrationsResult = ['ok' => true, 'message' => 'Migrations applied and caches cleared.'];
                OperationLog::event('admin.upgrade.migrations_applied', [
                    'actor_user_id' => $actorId,
                ]);
            } catch (\Throwable $e) {
                $migrationsResult = ['ok' => false, 'message' => 'Failed: ' . $e->getMessage()];
                OperationLog::failure('admin.upgrade.migrations_failed', $e, [
                    'actor_user_id' => $actorId,
                ]);
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
