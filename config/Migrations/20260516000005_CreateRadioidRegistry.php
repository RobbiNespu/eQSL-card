<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Local mirror of the RadioID.net user database.
 *
 * RadioID publishes the full user registry as a single CSV at
 * https://radioid.net/static/user.csv (~16 MB, ~250k rows). Periodically
 * downloading the whole thing and serving lookups locally is much
 * friendlier than hitting their per-callsign API endpoint on every form
 * submit — no Cloudflare friction, no rate limits, instant lookups, and
 * the dataset is small enough that a refresh fits in seconds.
 *
 * The CSV columns map straight onto this table; we don't normalise the
 * geography fields because the format is consistent enough to use as-is.
 *
 *   - `callsign` is the natural lookup key, indexed unique so the
 *     importer can TRUNCATE+INSERT without worrying about duplicates
 *     (the CSV itself has unique callsigns per row).
 *   - `radio_id` (DMR ID) preserved for callers that want to display it
 *     alongside the name/QTH.
 *   - `imported_at` per row so a partial import is recoverable — operator
 *     can see which rows belong to the latest batch.
 *
 * Storage estimate: 250k rows × ~150 bytes/row ≈ 38 MB on InnoDB with
 * the indexes. Trivial for any host that can run PHP.
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
