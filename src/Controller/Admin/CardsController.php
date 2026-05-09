<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

/**
 * Admin all-cards browser (M4-T7).
 *
 * Cross-tenant view of every card in the system — both user-owned and
 * guest-rendered. Filters cover the dimensions an admin actually needs to
 * triage abuse: kind (user vs guest), creation-date range, and an opt-in
 * toggle to surface soft-deleted rows (which the user-facing
 * `/cards` listing hides via `find('active')`). Pagination defaults to 30
 * rows since admins typically scroll-read these listings rather than
 * thumbnail-grid them.
 *
 * Access control mirrors the rest of `App\Controller\Admin\*`: anonymous
 * hits go through the AuthenticationMiddleware → redirect to /login,
 * authenticated non-admins get a 403 from `beforeFilter()`.
 */
class CardsController extends AppController
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
     * Paginated, filterable list of every card in the system.
     *
     * @return void
     */
    public function index(): void
    {
        $cards = $this->fetchTable('Cards');
        $query = $cards->find()
            ->contain(['Users', 'GuestVisits', 'Templates'])
            ->orderBy(['Cards.created_at' => 'DESC']);

        // Soft-deleted cards are hidden by default — opt in via
        // `?include_deleted=1` for forensics or storage cleanup work.
        $includeDeleted = (bool)$this->request->getQuery('include_deleted', false);
        if (!$includeDeleted) {
            $query->where(['Cards.deleted_at IS' => null]);
        }

        $kind = (string)$this->request->getQuery('kind', '');
        if ($kind === 'guest') {
            $query->where(['Cards.guest_visit_id IS NOT' => null]);
        } elseif ($kind === 'user') {
            $query->where(['Cards.user_id IS NOT' => null]);
        }

        // Date filters are deliberately validated by regex rather than
        // `DateTime::createFromFormat` because we want to silently ignore
        // garbage (e.g. `?from=foo`) instead of 500ing on the admin surface.
        $from = (string)$this->request->getQuery('from', '');
        $to = (string)$this->request->getQuery('to', '');
        if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $query->where(['Cards.created_at >=' => $from . ' 00:00:00']);
        }
        if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $query->where(['Cards.created_at <=' => $to . ' 23:59:59']);
        }

        $cards = $this->paginate($query, ['limit' => 30]);
        $this->set([
            'cards' => $cards,
            'filters' => compact('includeDeleted', 'kind', 'from', 'to'),
            'title' => 'Admin · All cards',
        ]);
    }
}
