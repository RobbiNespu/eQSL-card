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
        'local'                 => 'Local directory — admin-imported CSV (recommended FIRST)',
        'radioid_database_dump' => 'RadioID registry — periodic stream into a local lookup cache; respects radioid.net/api_use_policy',
        'radioid_api'           => 'RadioID API (users) — broader users endpoint; behind Cloudflare',
        'qrz'                   => 'QRZ.com — requires paid XML key, currently disabled',
        'mcmc'                  => 'MCMC Malaysia — live scrape (9M / 9W)',
        'marts'                 => 'MARTS Malaysia — use local directory; site unstable',
        'rapi'                  => 'Indonesia RAPI — use local directory; PDF-only sources',
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
     * UNION ALL across `callsign_directory` (CSV-uploaded) and
     * `callsign_lookups` (auto-fetched cache), projecting both into a
     * uniform shape (literal `source_type` discriminator, aliased
     * `source_detail` and `updated_at`). UNION ALL keeps duplicates — when
     * a callsign exists in both tables it shows up twice with different
     * source badges, which is exactly what the operator wants to see.
     *
     * Done as raw SQL because the two tables have different columns and
     * mapping that through the ORM's union() with literal expressions is
     * uglier than the SQL itself. Counts and pagination are computed
     * server-side; the connection-level execute() returns plain arrays
     * which match the existing view's expectations.
     */
    public function all(): void
    {
        $q = trim((string)$this->request->getQuery('q', ''));
        $dirTable = $this->fetchTable('CallsignDirectory');
        $cacheTable = $this->fetchTable('CallsignLookups');

        // Counts via the ORM — same filter applied to both tables.
        $dirCountQ = $dirTable->find();
        $cacheCountQ = $cacheTable->find();
        if ($q !== '') {
            $like = '%' . strtoupper($q) . '%';
            $dirCountQ->where(['callsign LIKE' => $like]);
            $cacheCountQ->where(['callsign LIKE' => $like]);
        }
        $directoryCount = $dirCountQ->count();
        $cacheCount = $cacheCountQ->count();
        $totalRows = $directoryCount + $cacheCount;

        // Pagination
        $perPage = 50;
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = max(1, min($totalPages, (int)$this->request->getQuery('page', 1)));
        $offset = ($page - 1) * $perPage;

        // Build the UNION ALL. Each SELECT picks the same column order and
        // names so the union is well-formed; the literal 'directory' /
        // 'cache' string becomes the source_type discriminator the view
        // uses to render the right badge and action button per row.
        $where1 = $q !== '' ? 'WHERE UPPER(callsign) LIKE :pat1' : '';
        $where2 = $q !== '' ? 'WHERE UPPER(callsign) LIKE :pat2' : '';
        $sql = "
            SELECT
                id, callsign, name, qth, country, grid_square, license_class,
                'directory' AS source_type,
                source_label AS source_detail,
                imported_at AS updated_at
            FROM callsign_directory
            {$where1}
            UNION ALL
            SELECT
                id, callsign, name, qth, country, grid_square, license_class,
                'cache' AS source_type,
                source AS source_detail,
                fetched_at AS updated_at
            FROM callsign_lookups
            {$where2}
            ORDER BY callsign ASC, source_type ASC
            LIMIT :lim OFFSET :off
        ";

        // Distinct placeholder names per occurrence — strict PDO mode
        // rejects re-using `:pat` across the two halves of the union.
        $params = ['lim' => $perPage, 'off' => $offset];
        $types  = ['lim' => 'integer', 'off' => 'integer'];
        if ($q !== '') {
            $like = '%' . strtoupper($q) . '%';
            $params['pat1'] = $like;
            $params['pat2'] = $like;
        }

        $stmt = $cacheTable->getConnection()->execute($sql, $params, $types);
        $rows = $stmt->fetchAll('assoc') ?: [];

        // The connection returns datetimes as raw strings. Cast them back
        // so the view's `->format('Y-m-d')` calls keep working without
        // special-casing.
        foreach ($rows as &$row) {
            if (!empty($row['updated_at'])) {
                try {
                    $row['updated_at'] = new \DateTimeImmutable((string)$row['updated_at']);
                } catch (\Throwable $e) {
                    $row['updated_at'] = null;
                }
            }
            $row['id'] = (int)$row['id'];
        }
        unset($row);

        $this->set([
            'title'          => 'All known callsigns',
            'q'              => $q,
            'callsigns'      => $rows,
            'directoryCount' => $directoryCount,
            'cacheCount'     => $cacheCount,
            'totalCount'     => $totalRows,
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
            'qrz'                   => 'QRZ.com',
            'radioid_database_dump' => 'RadioID database dump',
            'radioid_api'           => 'RadioID API (users)',
            'mcmc'                  => 'MCMC Malaysia',
            'marts'                 => 'MARTS Malaysia',
            'rapi'                  => 'Indonesia RAPI',
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

        // Extra context for the RadioID provider: how many rows are
        // currently in the local lookup cache and when it was last
        // refreshed. The view uses these to show a "Refresh now" button
        // alongside the freshness summary.
        $registryCount = null;
        $registryLastImport = null;
        if ($code === 'radioid_database_dump') {
            $row = $this->fetchTable('CallsignLookups')->getConnection()->execute(
                'SELECT COUNT(*) AS c, MAX(imported_at) AS last_import FROM radioid_registry'
            )->fetch('assoc');
            $registryCount = (int)($row['c'] ?? 0);
            $registryLastImport = $row['last_import'] ?? null;
        }

        $this->set([
            'title'              => $known[$code] . ' — provider settings',
            'code'               => $code,
            'label'              => $known[$code],
            'rowCount'           => $rowCount,
            'isEnabled'          => $isEnabled,
            'description'        => self::PROVIDER_MAP[$code] ?? '',
            'registryCount'      => $registryCount,
            'registryLastImport' => $registryLastImport,
        ]);
    }

    /**
     * Sync the RadioID local lookup cache — admin-triggered, sync-flow
     * (the import completes in seconds; no queue needed). Use sparingly:
     * RadioID's API use policy expects polite, low-frequency fetches,
     * not per-request polling. A weekly or on-demand cadence is plenty.
     */
    public function refreshRadioIdDump(): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $importer = new \App\Service\CallsignLookup\RadioIdRegistryImporter();
        $started = microtime(true);
        try {
            $count = $importer->refresh();
        } catch (\Throwable $e) {
            $this->Flash->error('Sync failed: ' . $e->getMessage());
            return $this->redirect('/admin/callsign-lookups/provider/radioid_database_dump');
        }
        $elapsed = number_format(microtime(true) - $started, 1);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'callsign.radioid_cache_synced',
                actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                metadata: ['rows' => $count, 'seconds' => $elapsed],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success("RadioID lookup cache synced — {$count} rows in {$elapsed}s.");
        return $this->redirect('/admin/callsign-lookups/provider/radioid_database_dump');
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
