<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

/**
 * Admin dashboard (M4-T5).
 *
 * Surfaces operational counts (users / cards / templates / upload storage)
 * plus the 20 most recent audit-log rows so an admin can spot abuse,
 * pending moderation work, or storage growth at a glance. Access is gated
 * the same way as the rest of the `Admin` prefix — anonymous hits get
 * redirected to `/login` by the AuthenticationMiddleware, authenticated
 * non-admins receive a 403.
 */
class DashboardController extends AppController
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
     * /login). Only authenticated-but-not-admin users need the explicit 403.
     *
     * @param \Cake\Event\EventInterface $event The before-filter event.
     * @return void
     * @throws \Cake\Http\Exception\ForbiddenException When the authenticated user is not an admin.
     */
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);

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
     * Render the admin overview dashboard.
     *
     * Aggregates live operational counts (users, cards, templates, storage) and
     * the 20 most recent audit-log entries. All queries are lightweight counts/
     * sums — no heavy joins. Read-only; no mutations here.
     *
     * @return void
     */
    public function index(): void
    {
        $users = $this->fetchTable('Users');
        $cards = $this->fetchTable('Cards');
        $templates = $this->fetchTable('Templates');
        $uploads = $this->fetchTable('CardBackgrounds');
        $audit = $this->fetchTable('AuditLogs');

        $stats = [
            'users_total' => $users->find()->where(['deleted_at IS' => null])->count(),
            'users_admin' => $users->find()->where(['role' => 'admin', 'deleted_at IS' => null])->count(),
            'cards_total' => $cards->find('active')->count(),
            'cards_guest' => $cards->find('active')->where(['guest_visit_id IS NOT' => null])->count(),
            'templates_total' => $templates->find()->where(['deleted_at IS' => null])->count(),
            'templates_pending' => $templates->find()->where([
                'is_public' => true, 'is_approved' => false, 'deleted_at IS' => null,
            ])->count(),
            'uploads_total' => $uploads->find()->where(['deleted_at IS' => null])->count(),
        ];

        // Storage MB — sum of file_size_bytes from non-deleted uploads. The
        // null-coalescing guard handles an empty uploads table where SUM()
        // returns NULL rather than 0.
        $uploadsBytes = (int)($uploads->find()
            ->where(['deleted_at IS' => null])
            ->select(['s' => $uploads->find()->func()->sum('file_size_bytes')])
            ->all()->first()?->s ?? 0);
        $stats['storage_mb_uploads'] = round($uploadsBytes / 1024 / 1024, 1);

        // Qualify created_at with the table alias — `contain(['Users'])`
        // joins users which also has a `created_at`, so an unqualified
        // ORDER BY is ambiguous on SQLite/MySQL.
        $recentAudit = $audit->find()
            ->orderBy(['AuditLogs.created_at' => 'DESC'])
            ->limit(20)
            ->contain(['Users'])
            ->all();

        $this->set([
            'title' => 'Admin Dashboard',
            'stats' => $stats,
            'recentAudit' => $recentAudit,
        ]);
    }
}
