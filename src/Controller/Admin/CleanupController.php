<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\I18n\DateTime;

/**
 * Admin cleanup tools (M4-T9 + T10 + T11).
 *
 * Two destructive maintenance actions, each fronted by a dry-run preview:
 *
 *  1. Guest-card purge — deletes `cards` rows that belong to a `GuestVisit`
 *     (i.e. were generated through the unauthenticated public form) and were
 *     created more than N days ago, plus their PNG/PDF artifacts under
 *     WWW_ROOT.
 *  2. Orphan-upload prune — deletes `uploads` rows that no `cards` row
 *     references AND that are older than N days, plus their stored file.
 *
 * Both actions write a `cleanup.*` audit log row after committing. The cards
 * → uploads FK is `RESTRICT`, so we always purge the cards first; orphan
 * uploads are only those left over after the upstream cards have been
 * deleted (or were never linked to a card to begin with).
 *
 * Access control mirrors the rest of `App\Controller\Admin\*`: anonymous
 * hits go through AuthenticationMiddleware → /login redirect; authenticated
 * non-admins get 403 from `beforeFilter()`.
 */
class CleanupController extends AppController
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
     * Dry-run preview. GET /admin/cleanup?days=N
     *
     * Computes, but does NOT mutate: how many guest cards and orphan uploads
     * would be removed at the requested cutoff, plus a 5-row sample of each
     * (oldest first) so the operator can sanity-check before clicking the
     * red button. `days` is clamped to >= 1 to prevent an accidental
     * `?days=0` from blowing away today's rows.
     */
    public function index(): void
    {
        $days = max(1, (int)$this->request->getQuery('days', 30));
        $cutoff = DateTime::now()->subDays($days);

        $cards = $this->fetchTable('Cards');
        $guestCardsToPurge = $cards->find()
            ->where([
                'Cards.guest_visit_id IS NOT' => null,
                'Cards.created_at <' => $cutoff,
            ]);

        $uploads = $this->fetchTable('Uploads');
        // Orphaned uploads: no `cards` row references them. `notMatching`
        // produces a LEFT JOIN + IS NULL filter against the `Uploads.hasMany
        // Cards` association declared on UploadsTable.
        $orphanQuery = $uploads->find()
            ->where(['Uploads.created_at <' => $cutoff])
            ->notMatching('Cards', function ($q) {
                return $q;
            });

        $this->set([
            'days' => $days,
            'guestCardsCount' => $guestCardsToPurge->count(),
            'guestCardsSample' => $cards->find()
                ->where([
                    'Cards.guest_visit_id IS NOT' => null,
                    'Cards.created_at <' => $cutoff,
                ])
                ->orderBy(['Cards.created_at' => 'ASC'])
                ->limit(5)
                ->all(),
            'orphanUploadsCount' => $orphanQuery->count(),
            'orphanUploadsSample' => $uploads->find()
                ->where(['Uploads.created_at <' => $cutoff])
                ->notMatching('Cards', function ($q) {
                    return $q;
                })
                ->orderBy(['Uploads.created_at' => 'ASC'])
                ->limit(5)
                ->all(),
            'title' => 'Admin · Cleanup',
        ]);
    }

    /**
     * POST /admin/cleanup/purge-guests — delete guest-owned cards older than
     * `days` (default 30) along with their PNG/PDF files under WWW_ROOT.
     */
    public function purgeGuests()
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $days = max(1, (int)$this->request->getData('days', 30));
        $cutoff = DateTime::now()->subDays($days);

        $cards = $this->fetchTable('Cards');
        $rows = $cards->find()
            ->where([
                'Cards.guest_visit_id IS NOT' => null,
                'Cards.created_at <' => $cutoff,
            ])->all();

        $deleted = 0;
        foreach ($rows as $row) {
            // Best-effort file removal. The `@unlink` swallows ENOENT for
            // rows whose artifacts were already deleted out-of-band; that
            // shouldn't block the row deletion itself.
            foreach ([$row->png_path, $row->pdf_path] as $relPath) {
                if ($relPath) {
                    @unlink(WWW_ROOT . $relPath);
                }
            }
            $cards->delete($row);
            $deleted++;
        }

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'cleanup.guest_cards_purged',
                actorUserId: $userId,
                metadata: ['days' => $days, 'count' => $deleted],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success("Purged {$deleted} guest cards older than {$days} days.");

        return $this->redirect('/admin/cleanup');
    }

    /**
     * POST /admin/cleanup/prune-uploads — delete `uploads` rows that no
     * `cards` row references AND that are older than `days`, plus their
     * stored file under WWW_ROOT. Run AFTER `purgeGuests` so that newly
     * orphaned uploads (left behind by the just-deleted cards, since the
     * cards → uploads FK is `RESTRICT`) get swept up too.
     */
    public function pruneUploads()
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $days = max(1, (int)$this->request->getData('days', 30));
        $cutoff = DateTime::now()->subDays($days);

        $uploads = $this->fetchTable('Uploads');
        $rows = $uploads->find()
            ->where(['Uploads.created_at <' => $cutoff])
            ->notMatching('Cards', function ($q) {
                return $q;
            })
            ->all();

        $deleted = 0;
        foreach ($rows as $row) {
            if ($row->storage_path) {
                @unlink(WWW_ROOT . $row->storage_path);
            }
            $uploads->delete($row);
            $deleted++;
        }

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'cleanup.orphan_uploads_pruned',
                actorUserId: $userId,
                metadata: ['days' => $days, 'count' => $deleted],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success("Pruned {$deleted} orphaned uploads older than {$days} days.");

        return $this->redirect('/admin/cleanup');
    }
}
