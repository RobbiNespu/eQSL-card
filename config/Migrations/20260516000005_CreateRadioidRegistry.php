<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Backing table for the RadioID callsign lookup cache.
 *
 * Populated from the RadioID user registry that ships as a downloadable
 * CSV. The import is admin-triggered and infrequent (weekly is fine),
 * in keeping with RadioID's API use policy
 * (https://radioid.net/api_use_policy) — we don't redistribute the
 * dataset, we cache it locally to keep our QSO form's per-submit
 * lookups instant and to avoid pinging the upstream on every keystroke.
 *
 *   - `callsign` is the natural lookup key, indexed unique so the
 *     importer can replace the cache wholesale without worrying about
 *     duplicates (the upstream itself has one row per callsign).
 *   - `radio_id` (DMR ID) preserved so the lookup result can link back
 *     to the canonical upstream record for the operator's reference.
 *   - `imported_at` per row records the sync batch each entry belongs
 *     to — handy when debugging cache freshness.
 *
 * Storage budget at typical sizes: ~250k rows × ~150 bytes/row ≈ 38 MB
 * on InnoDB including indexes. Negligible for any host that can run PHP.
 */
final class CreateRadioidRegistry extends AbstractMigration
{
    public function change(): void
    {
        $this->table('radioid_registry')
            ->addColumn('radio_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('callsign', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('first_name', 'string', ['limit' => 80, 'null' => true, 'default' => null])
            ->addColumn('last_name', 'string', ['limit' => 80, 'null' => true, 'default' => null])
            ->addColumn('city', 'string', ['limit' => 80, 'null' => true, 'default' => null])
            ->addColumn('state', 'string', ['limit' => 80, 'null' => true, 'default' => null])
            ->addColumn('country', 'string', ['limit' => 80, 'null' => true, 'default' => null])
            ->addColumn('imported_at', 'datetime', ['null' => false])
            ->addIndex(['callsign'], ['unique' => true, 'name' => 'idx_radioid_registry_callsign'])
            ->create();
    }
}
