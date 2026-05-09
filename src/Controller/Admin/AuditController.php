<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

/**
 * Admin audit log viewer (M4-T8).
 *
 * Read-only window onto the `audit_logs` table. Admins can filter by event
 * name (drop-down sourced from distinct values currently in the table — so
 * the filter list grows organically as new event types are instrumented)
 * and by actor user id. The recent-activity widget on the dashboard
 * (M4-T5) shows the top-20; this surface is the paginated drill-down.
 *
 * Access control mirrors the rest of `App\Controller\Admin\*`: anonymous
 * hits go through the AuthenticationMiddleware → redirect to /login,
 * authenticated non-admins get a 403 from `beforeFilter()`.
 */
class AuditController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Anonymous: AuthenticationComponent::startup() (runs after
        // beforeFilter) throws UnauthenticatedException → middleware redirect
        // to /login. We only gate authenticated-but-not-admin users here.
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return;
        }
        $user = $this->fetchTable('Users')->get($identity->getIdentifier());
        if ($user->role !== 'admin') {
            throw new \Cake\Http\Exception\ForbiddenException('Admin only.');
        }
    }

    /**
     * Paginated, filterable list of audit-log rows.
     *
     * @return void
     */
    public function index(): void
    {
        $audit = $this->fetchTable('AuditLogs');
        // Qualify created_at with the table alias — `contain(['Users'])`
        // joins users which also has a `created_at`, so an unqualified
        // ORDER BY is ambiguous on SQLite/MySQL.
        $query = $audit->find()
            ->contain(['Users'])
            ->orderBy(['AuditLogs.created_at' => 'DESC']);

        $event = (string)$this->request->getQuery('event', '');
        if ($event !== '') {
            $query->where(['AuditLogs.event' => $event]);
        }

        $actorId = (int)$this->request->getQuery('actor_id', 0);
        if ($actorId > 0) {
            $query->where(['AuditLogs.actor_user_id' => $actorId]);
        }

        // Pull distinct events for the filter drop-down. Cheap on SQLite
        // (audit_logs is indexed on `event`) and the dropdown is the only
        // way an admin can discover what event names exist in this DB.
        $eventTypes = $audit->find()
            ->select(['event'])
            ->distinct(['event'])
            ->orderBy(['event' => 'ASC'])
            ->all()
            ->extract('event')
            ->toList();

        $logs = $this->paginate($query, ['limit' => 50]);
        $this->set([
            'logs' => $logs,
            'eventTypes' => $eventTypes,
            'filters' => compact('event', 'actorId'),
            'title' => 'Admin · Audit log',
        ]);
    }
}
