<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Admin-uploaded callsign directory.
 *
 * Companion to `callsign_lookups` (the auto-fetch cache). Where the cache
 * is filled by external providers and expires, the directory is operator-
 * curated data — typically imported from official sources that publish
 * lists in PDF / Excel rather than offering an API (MCMC Malaysia, MARTS,
 * Indonesia RAPI regional lists). The admin downloads, converts to CSV,
 * and uploads via /admin/callsign-directory.
 *
 * `LocalDirectoryProvider` reads from this table and slots into the
 * orchestrator's provider chain. It typically goes first — once a callsign
 * is in the directory, we don't need to hit any external source for it.
 *
 * Schema choices mirror `callsign_lookups` so the provider can hydrate a
 * `CallsignLookupResult` without field-name acrobatics. The deliberate
 * difference: this table has no `expires_at` — directory data is
 * authoritative until the admin re-imports.
 */
final class CreateCallsignDirectory extends AbstractMigration
{
    public function change(): void
    {
        $this->table('callsign_directory')
            ->addColumn('callsign', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('qth', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('country', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->addColumn('grid_square', 'string', ['limit' => 10, 'null' => true, 'default' => null])
            ->addColumn('license_class', 'string', ['limit' => 40, 'null' => true, 'default' => null])
            ->addColumn('source_label', 'string', ['limit' => 80, 'null' => true, 'default' => null,
                'comment' => 'Free-text label of where this row came from, e.g. "MCMC 2026-Q1" or "MARTS members"'])
            ->addColumn('imported_at', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['callsign'], ['unique' => true, 'name' => 'idx_callsign_directory_callsign'])
            ->create();
    }
}
