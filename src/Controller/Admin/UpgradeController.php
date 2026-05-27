<?php
declare(strict_types=1);

namespace App\Controller\Admin;

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
class UpgradeController extends AdminController
{

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
