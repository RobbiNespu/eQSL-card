<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\OperationLog;
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

        $uploads = $this->fetchTable('CardBackgrounds');
        // Orphaned uploads: no `cards` row references them. `notMatching`
        // produces a LEFT JOIN + IS NULL filter against the `Uploads.hasMany
        // Cards` association declared on UploadsTable.
        $orphanQuery = $uploads->find()
            ->where(['CardBackgrounds.created_at <' => $cutoff])
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
                ->where(['CardBackgrounds.created_at <' => $cutoff])
                ->notMatching('Cards', function ($q) {
                    return $q;
                })
                ->orderBy(['CardBackgrounds.created_at' => 'ASC'])
                ->limit(5)
                ->all(),
            // Retention preview — how many user cards would soft-delete on
            // the next "Expire old cards" run. NULL retention means feature
            // is off and we render the panel as informational only.
            'cardRetentionDays' => (int)(new \App\Service\AppSettings())->get('card_retention_days', 0),
            'cardsToExpire' => (int)(new \App\Service\AppSettings())->get('card_retention_days', 0) > 0
                ? $this->fetchTable('Cards')->find()
                    ->where([
                        'Cards.user_id IS NOT' => null,
                        'Cards.deleted_at IS' => null,
                        'Cards.created_at <' => DateTime::now()->subDays((int)(new \App\Service\AppSettings())->get('card_retention_days', 0)),
                    ])->count()
                : 0,
            'callsignCacheCount' => $this->fetchTable('CallsignLookups')->find()->count(),
            'cacheStats' => $this->dirStats([TMP . 'cache']),
            'logStats' => $this->dirStats([LOGS], ['log']),
            'sessionStats' => $this->dirStats([TMP . 'sessions']),
            'title' => 'Admin · Cleanup',
        ]);
    }

    /**
     * Walk all files under the supplied directories. Yields each file path,
     * skipping `.gitkeep` markers and dotfiles, optionally filtered by file
     * extension. Recursion is required because Cake nests caches inside
     * `tmp/cache/<engine>/...` subdirs (and our own RateLimiter parks files
     * under `tmp/cache/rate_limits/`) — a non-recursive sweep misses those
     * and silently leaves stale state behind.
     *
     * @param string[] $dirs Absolute directory paths.
     * @param string[]|null $extensionsAllow When supplied, only files with
     *        one of these extensions are yielded (e.g. `['log']`).
     * @return \Generator<int, string>
     */
    private function walkFiles(array $dirs, ?array $extensionsAllow = null): \Generator
    {
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $dir,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS,
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ($iter as $entry) {
                /** @var \SplFileInfo $entry */
                if (!$entry->isFile()) {
                    continue;
                }
                $base = $entry->getFilename();
                if ($base === '.gitkeep') {
                    continue;
                }
                if ($extensionsAllow !== null) {
                    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                    if (!in_array($ext, $extensionsAllow, true)) {
                        continue;
                    }
                }
                yield $entry->getPathname();
            }
        }
    }

    /**
     * @param string[] $dirs
     * @param string[]|null $extensionsAllow
     * @return array{count:int, bytes:int}
     */
    private function dirStats(array $dirs, ?array $extensionsAllow = null): array
    {
        $count = 0;
        $bytes = 0;
        foreach ($this->walkFiles($dirs, $extensionsAllow) as $path) {
            $count++;
            $bytes += (int)@filesize($path);
        }
        return ['count' => $count, 'bytes' => $bytes];
    }

    /**
     * Delete every file yielded by {@see self::walkFiles()} and return the count.
     *
     * @param string[] $dirs Absolute directory paths to sweep.
     * @param string[]|null $extensionsAllow When supplied, only delete files with these extensions.
     * @return int Number of files successfully deleted.
     */
    private function deleteFilesUnder(array $dirs, ?array $extensionsAllow = null): int
    {
        $deleted = 0;
        foreach ($this->walkFiles($dirs, $extensionsAllow) as $path) {
            if (@unlink($path)) {
                $deleted++;
            }
        }
        return $deleted;
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
            // shouldn't block the row deletion itself. The thumbnail path
            // is derived from png_path (.thumb.<ext>) — also unlinked here.
            foreach ([$row->png_path, $row->pdf_path] as $relPath) {
                if ($relPath) {
                    @unlink(WWW_ROOT . $relPath);
                }
            }
            if ($row->png_path) {
                @unlink(WWW_ROOT . \App\Service\CardRenderer::thumbPathFor($row->png_path));
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

        OperationLog::event('admin.cleanup.guest_cards_purged', [
            'actor_user_id' => $userId,
            'days'          => $days,
            'count'         => $deleted,
        ]);

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

        $uploads = $this->fetchTable('CardBackgrounds');
        $rows = $uploads->find()
            ->where(['CardBackgrounds.created_at <' => $cutoff])
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

        OperationLog::event('admin.cleanup.orphan_uploads_pruned', [
            'actor_user_id' => $userId,
            'days'          => $days,
            'count'         => $deleted,
        ]);

        $this->Flash->success("Pruned {$deleted} orphaned uploads older than {$days} days.");

        return $this->redirect('/admin/cleanup');
    }

    /**
     * POST /admin/cleanup/expire-cards — soft-delete user cards older than the
     * `card_retention_days` admin setting. Storage is reclaimed on the next
     * orphan-uploads sweep (the cleanup action chain is purge-guests →
     * expire-cards → prune-uploads). When the retention setting is unset or
     * <= 0, the action is a no-op — operators have to opt in to retention.
     */
    public function expireCards()
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $retention = (int)(new \App\Service\AppSettings())->get('card_retention_days', 0);

        if ($retention <= 0) {
            $this->Flash->info('Card retention is disabled — set card_retention_days in Settings to enable.');
            return $this->redirect('/admin/cleanup');
        }

        $cutoff = DateTime::now()->subDays($retention);
        $cards = $this->fetchTable('Cards');
        // User-owned, non-deleted cards older than the cutoff. Guest cards are
        // handled by the existing purgeGuests action so this scope stays
        // unambiguous.
        $rows = $cards->find()
            ->where([
                'Cards.user_id IS NOT' => null,
                'Cards.deleted_at IS' => null,
                'Cards.created_at <' => $cutoff,
            ])->all();

        $soft = 0;
        foreach ($rows as $row) {
            $row->set('deleted_at', DateTime::now(), ['guard' => false]);
            if ($cards->save($row)) {
                $soft++;
            }
        }

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'cleanup.cards_expired',
                actorUserId: $userId,
                metadata: ['retention_days' => $retention, 'count' => $soft],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        OperationLog::event('admin.cleanup.cards_expired', [
            'actor_user_id'  => $userId,
            'retention_days' => $retention,
            'count'          => $soft,
        ]);

        $this->Flash->success("Soft-deleted {$soft} cards older than {$retention} days. Run 'Prune orphans' next to reclaim disk.");
        return $this->redirect('/admin/cleanup');
    }

    /**
     * POST /admin/cleanup/callsign-cache — wipe the callsign_lookups table.
     * Use when a provider's data has gone stale or you've enabled a new
     * (better) provider and want the chain to re-resolve.
     */
    public function callsignCache()
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();

        $service = new \App\Service\CallsignLookup\CallsignLookupService(
            providers: [], // No providers needed for cache clear
            settings: new \App\Service\AppSettings(),
        );
        $count = $service->clearCache();

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'cleanup.callsign_cache_cleared',
                actorUserId: $userId,
                metadata: ['rows_deleted' => $count],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        OperationLog::event('admin.cleanup.callsign_cache_cleared', [
            'actor_user_id' => $userId,
            'rows_deleted'  => $count,
        ]);

        $this->Flash->success("Cleared {$count} cached callsign lookups.");
        return $this->redirect('/admin/cleanup');
    }

    /**
     * POST /admin/cleanup/cache — wipe everything under `tmp/cache/*` (excluding
     * `.gitkeep` markers) and call CakePHP's Cache::clearAll() so any in-memory
     * caches drop too. Safe to run any time; CakePHP rebuilds caches on next
     * request. Useful when stale config/translations files become unreadable
     * (the classic root-vs-www-data uid bug surfaced as 'Permission denied').
     */
    public function cache()
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();

        // Recursive — covers all the engine subdirs (cake_, models, persistent,
        // views) plus our own RateLimiter buckets under `tmp/cache/rate_limits/`.
        // Without recursion, stale rate-limit files leak into a 'cache cleared'
        // run and produce surprise 429s on /login.
        $deleted = $this->deleteFilesUnder([TMP . 'cache']);

        try {
            \Cake\Cache\Cache::clearAll();
        } catch (\Throwable $e) {
            error_log('cache::clearAll: ' . $e->getMessage());
        }

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'cleanup.cache_cleared',
                actorUserId: $userId,
                metadata: ['files_removed' => $deleted],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        OperationLog::event('admin.cleanup.cache_cleared', [
            'actor_user_id' => $userId,
            'files_removed' => $deleted,
        ]);

        $this->Flash->success("Cache cleared ({$deleted} files removed).");
        return $this->redirect('/admin/cleanup');
    }

    /**
     * POST /admin/cleanup/logs — delete all .log files under LOGS/. Safe; the
     * file logger reopens its target on the next emit.
     */
    public function logs()
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();

        $deleted = $this->deleteFilesUnder([LOGS], ['log']);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'cleanup.logs_cleared',
                actorUserId: $userId,
                metadata: ['files_removed' => $deleted],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        OperationLog::event('admin.cleanup.logs_cleared', [
            'actor_user_id' => $userId,
            'files_removed' => $deleted,
        ]);

        $this->Flash->success("Logs cleared ({$deleted} files removed).");
        return $this->redirect('/admin/cleanup');
    }

    /**
     * Prune net-session removal tombstones older than 7 days. Tombstones
     * exist only to surface live deletions to polling clients; old ones
     * are noise.
     */
    public function netRemovalsSweep(): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $cutoff = DateTime::now()->subDays(7);
        $table = $this->fetchTable('NetSessionRemovals');
        $deleted = $table->deleteAll(['removed_at <' => $cutoff]);
        $this->Flash->success("Pruned {$deleted} old net-removal tombstones.");
        OperationLog::event('admin.cleanup.net_removals_pruned', ['count' => $deleted]);
        return $this->redirect(['action' => 'index']);
    }

    /**
     * POST /admin/cleanup/sessions — delete every active CakePHP file session
     * under tmp/sessions/. Forces a logout for every signed-in user, including
     * the admin running this. We re-auth ourselves explicitly via
     * `Authentication->logout()` so the redirect lands on /login cleanly.
     */
    public function sessions()
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();

        $deleted = $this->deleteFilesUnder([TMP . 'sessions']);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'cleanup.sessions_cleared',
                actorUserId: $userId,
                metadata: ['files_removed' => $deleted],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        OperationLog::event('admin.cleanup.sessions_cleared', [
            'actor_user_id' => $userId,
            'files_removed' => $deleted,
        ]);

        // Audit row is already persisted; safe to drop our own session next.
        $this->Authentication->logout();

        // Can't Flash because the session we'd Flash into is gone — use a
        // query string instead, which the login page can show as a banner if
        // we add support; for now just redirect.
        return $this->redirect('/login?signed_out=cleanup');
    }
}
