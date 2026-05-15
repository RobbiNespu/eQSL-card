<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\AppSettings;
use App\Service\CallsignLookup\CallsignLookupService;
use Cake\Http\Exception\ForbiddenException;

/**
 * Admin surface for the `callsign_lookups` cache (the rows that the
 * auto-complete chain writes when an external provider returns useful
 * data for a callsign). Distinct from /admin/callsign-lookups/provider/local, which
 * manages the admin-curated CSV the LocalDirectoryProvider reads.
 *
 * This controller is read/edit/delete-only — adding new rows here would
 * blur the line with the directory. Operators who want a callsign to
 * always resolve should put it in the directory; this page is for
 * curating what the cache happens to have collected so far.
 *
 * IMPORTANT: edits and deletes here NEVER touch the `qsos` table. The
 * cache row is independent of the user's logged QSO history; mutating
 * a cache row only changes what the auto-complete UI suggests next time,
 * not any historic contact data.
 */
class CallsignLookupsController extends AppController
{
    /**
     * Provider codes the chain knows about, keyed by code() with a short
     * human description. Kept in sync with the order in CallsignController
     * so the admin sees the same set the runtime uses.
     */
    private const PROVIDER_MAP = [
        'local'   => 'Local directory — admin-imported CSV (recommended FIRST)',
        'radioid' => 'RadioID.net — worldwide DMR registry, JSON API',
        'qrz'     => 'QRZ.com — requires paid XML key, currently disabled',
        'mcmc'    => 'MCMC Malaysia — live scrape (9M / 9W)',
        'marts'   => 'MARTS Malaysia — use local directory; site unstable',
        'rapi'    => 'Indonesia RAPI — use local directory; PDF-only sources',
    ];

    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Anonymous requests fall through; AuthenticationMiddleware redirects
        // them to /login on its own. Only authenticated-but-non-admin hits
        // need the explicit 403 here.
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return;
        }
        $user = $this->fetchTable('Users')->get($identity->getIdentifier());
        if ($user->role !== 'admin') {
            throw new ForbiddenException('Admin only.');
        }
    }

    /**
     * Source-of-data settings only. The browse / search / per-row CRUD
     * surface moved to /admin/callsign-lookups/all (combined view) so this
     * page can stay focused on the chain configuration.
     */
    public function index(): void
    {
        $settings = new AppSettings();
        $enabled = (bool)$settings->get('callsign_lookup_enabled', false);
        $providerCsv = (string)$settings->get('callsign_lookup_providers', '');
        $enabledProviders = array_values(array_filter(array_map('trim', explode(',', $providerCsv))));

        $this->set([
            'title'            => 'Callsign auto-complete',
            'callsignEnabled'  => $enabled,
            'providerMap'      => self::PROVIDER_MAP,
            'enabledProviders' => $enabledProviders,
        ]);
    }

    /**
     * Unified view across both data sources.
     *
     * Joins the admin-curated `callsign_directory` (CSV-uploaded) with the
     * opportunistic `callsign_lookups` cache (auto-fetched by the chain) so
     * the operator can see every callsign the install knows about in one
     * place. Dedupes by callsign — directory wins for the row shown, but a
     * cached counterpart is flagged via `also_cached` so the admin can spot
     * stale cache entries that have since been superseded by a CSV upload.
     *
     * Done as a PHP-side merge (not SQL UNION) because the two tables have
     * different columns and we want a tidy `source_type`/`source_detail`
     * shape in the view; the typical install has thousands of rows at most,
     * well within PHP memory budgets. Pagination is also in-memory for the
     * same reason — when it stops scaling, swap to a UNION query.
     */
    public function all(): void
    {
        $q = trim((string)$this->request->getQuery('q', ''));

        $dirQuery = $this->fetchTable('CallsignDirectory')->find()->disableHydration();
        $cacheQuery = $this->fetchTable('CallsignLookups')->find()->disableHydration();
        if ($q !== '') {
            $upper = '%' . strtoupper($q) . '%';
            $dirQuery->where(['callsign LIKE' => $upper]);
            $cacheQuery->where(['callsign LIKE' => $upper]);
        }
        $directoryRows = $dirQuery->all()->toList();
        $cacheRows = $cacheQuery->all()->toList();

        // Directory first: it's the authoritative source the chain consults
        // ahead of the cache, so its values should win when both stores hold
        // the same callsign.
        $byCall = [];
        foreach ($directoryRows as $row) {
            $byCall[$row['callsign']] = [
                'callsign'      => $row['callsign'],
                'name'          => $row['name'],
                'qth'           => $row['qth'],
                'country'       => $row['country'],
                'grid_square'   => $row['grid_square'],
                'license_class' => $row['license_class'],
                'source_type'   => 'directory',
                'source_detail' => $row['source_label'] ?? null,
                'updated_at'    => $row['imported_at'] ?? null,
                'id'            => $row['id'],
                'cache_id'      => null,
                'also_cached'   => false,
            ];
        }
        foreach ($cacheRows as $row) {
            $call = $row['callsign'];
            if (isset($byCall[$call])) {
                // Same callsign in both stores. Keep the directory row; just
                // flag that a cache counterpart exists so the admin sees it
                // and can clean up.
                $byCall[$call]['also_cached'] = true;
                $byCall[$call]['cache_id']    = $row['id'];
                continue;
            }
            $byCall[$call] = [
                'callsign'      => $row['callsign'],
                'name'          => $row['name'],
                'qth'           => $row['qth'],
                'country'       => $row['country'],
                'grid_square'   => $row['grid_square'],
                'license_class' => $row['license_class'],
                'source_type'   => 'cache',
                'source_detail' => $row['source'] ?? null,
                'updated_at'    => $row['fetched_at'] ?? null,
                'id'            => $row['id'],
                'cache_id'      => $row['id'],
                'also_cached'   => false,
            ];
        }
        ksort($byCall);
        $combined = array_values($byCall);

        // In-memory pagination — 50/page is generous enough that most installs
        // fit on one page, while keeping the rendered HTML bounded.
        $perPage    = 50;
        $totalRows  = count($combined);
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page       = max(1, min($totalPages, (int)$this->request->getQuery('page', 1)));
        $pageRows   = array_slice($combined, ($page - 1) * $perPage, $perPage);

        $this->set([
            'title'          => 'All known callsigns',
            'q'              => $q,
            'callsigns'      => $pageRows,
            'directoryCount' => count($directoryRows),
            'cacheCount'     => count($cacheRows),
            'uniqueCount'    => $totalRows,
            'page'           => $page,
            'totalPages'     => $totalPages,
        ]);
    }

    /**
     * Edit a single cache row. The natural key `callsign` is immutable
     * here — changing it would create a duplicate (UNIQUE constraint) or
     * orphan whatever the user typed. They can delete + let the chain
     * re-fetch if a renaming is needed.
     */
    public function edit(int $id): ?\Cake\Http\Response
    {
        $table = $this->fetchTable('CallsignLookups');
        $entity = $table->find()->where(['id' => $id])->firstOrFail();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $table->patchEntity($entity, [
                'name'          => trim((string)($data['name'] ?? '')) ?: null,
                'qth'           => trim((string)($data['qth'] ?? '')) ?: null,
                'country'       => trim((string)($data['country'] ?? '')) ?: null,
                'grid_square'   => trim((string)($data['grid_square'] ?? '')) ?: null,
                'license_class' => trim((string)($data['license_class'] ?? '')) ?: null,
                'source_url'    => trim((string)($data['source_url'] ?? '')) ?: null,
            ]);
            if ($table->save($entity)) {
                $this->Flash->success('Cached lookup updated.');

                return $this->redirect('/admin/callsign-lookups');
            }
            $this->Flash->error('Could not save the changes.');
        }

        $this->set([
            'entity' => $entity,
            'title'  => 'Edit cached callsign — ' . $entity->callsign,
        ]);

        return null;
    }

    /**
     * Hard-delete one cache row. The chain will re-fetch on the next
     * lookup; no QSO data is touched.
     */
    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');

        $table = $this->fetchTable('CallsignLookups');
        $entity = $table->find()->where(['id' => $id])->firstOrFail();
        $call = $entity->callsign;
        $table->deleteOrFail($entity);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'callsign_lookup.deleted',
                actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                metadata: ['callsign' => $call],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success("Cached lookup for {$call} removed.");

        return $this->redirect('/admin/callsign-lookups');
    }

    /**
     * Write the enabled flag + provider order to app_settings. Mirrors
     * the same keys the /admin/settings page edits, so both surfaces stay
     * in sync — settings.php still works, this is just a focused entry
     * point next to the cache UI.
     */
    public function saveSettings(): \Cake\Http\Response
    {
        $this->request->allowMethod('post');

        $data = $this->request->getData();
        $update = [];
        // Enable toggle: hidden 0 + checkbox 1 pattern, same as /admin/settings.
        $update['callsign_lookup_enabled'] = (bool)($data['callsign_lookup_enabled'] ?? false);

        // Provider checkboxes arrive as `callsign_provider[code]=1`. Preserve
        // the order they were rendered in (the order admin sees on the page).
        $enabledCodes = [];
        if (isset($data['callsign_provider']) && is_array($data['callsign_provider'])) {
            foreach ($data['callsign_provider'] as $code => $on) {
                if ($on && isset(self::PROVIDER_MAP[$code])) {
                    $enabledCodes[] = $code;
                }
            }
        }
        $update['callsign_lookup_providers'] = implode(',', $enabledCodes);

        (new AppSettings())->setMany($update);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'settings.updated',
                actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                metadata: ['keys' => array_keys($update), 'via' => 'callsign-lookups'],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success('Callsign auto-complete settings saved.');

        return $this->redirect('/admin/callsign-lookups');
    }

    /**
     * Per-provider settings / status page.
     *
     * Local-directory routing is mounted on CallsignDirectoryController in
     * routes.php (so the existing CSV upload UI is reused) — this method
     * only handles the remote-provider codes (qrz, radioid, mcmc, marts,
     * rapi). They don't have configurable settings today (each is either
     * a stub waiting for a scraper or a fixed-endpoint API with no auth),
     * so the page mostly explains current state and counts how many cache
     * rows that provider has produced. Future API-key fields land here.
     */
    public function provider(string $code): void
    {
        $known = [
            'qrz'     => 'QRZ.com',
            'radioid' => 'RadioID.net',
            'mcmc'    => 'MCMC Malaysia',
            'marts'   => 'MARTS Malaysia',
            'rapi'    => 'Indonesia RAPI',
        ];
        if (!isset($known[$code])) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $rowCount = $this->fetchTable('CallsignLookups')
            ->find()
            ->where(['source' => $code])
            ->count();

        $settings = new AppSettings();
        $enabledCsv = (string)$settings->get('callsign_lookup_providers', '');
        $enabledList = array_filter(array_map('trim', explode(',', $enabledCsv)));
        $isEnabled = in_array($code, $enabledList, true);

        $this->set([
            'title'      => $known[$code] . ' — provider settings',
            'code'       => $code,
            'label'      => $known[$code],
            'rowCount'   => $rowCount,
            'isEnabled'  => $isEnabled,
            'description'=> self::PROVIDER_MAP[$code] ?? '',
        ]);
    }

    /**
     * Wipe the entire cache. Re-uses the service-layer clear so audit
     * counting matches whatever the cleanup page would produce.
     */
    public function clear(): \Cake\Http\Response
    {
        $this->request->allowMethod('post');

        $service = new CallsignLookupService(
            providers: [],
            settings: new AppSettings(),
        );
        $count = $service->clearCache();

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'cleanup.callsign_cache_cleared',
                actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                metadata: ['rows_deleted' => $count, 'via' => 'callsign-lookups'],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success("Cleared {$count} cached callsign lookups.");

        return $this->redirect('/admin/callsign-lookups');
    }
}
