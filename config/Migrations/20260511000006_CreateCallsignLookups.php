<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Callsign auto-complete cache.
 *
 * When a user types a callsign in the QSO add form, the app can look it up
 * against external sources (QRZ.com, MCMC Malaysia, MARTS, RadioID,
 * Indonesia RAPI). Each successful resolution lands here keyed on the
 * uppercase callsign so subsequent typings of the same callsign — by anyone,
 * any time — short-circuit the external fetch.
 *
 * Schema choices:
 *  - `callsign` is the natural key; uppercased on write by the service.
 *    Indexed unique so we don't accumulate duplicate-source rows for the
 *    same call. If a provider chain ever needs to record alternate sources
 *    for the same callsign, that becomes a separate audit table; this row
 *    represents the "current best answer".
 *  - `expires_at` lets each provider set its own TTL (e.g. RadioID can
 *    refresh weekly; scraped HTML weekly-to-monthly). NULL means "never
 *    auto-refresh" — the admin clears the cache explicitly.
 *  - `raw_payload` keeps the full provider response for debugging /
 *    future field extraction without re-fetching.
 */
final class CreateCallsignLookups extends AbstractMigration
{
    public function change(): void
    {
        $this->table('callsign_lookups')
            ->addColumn('callsign', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('qth', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('country', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->addColumn('grid_square', 'string', ['limit' => 10, 'null' => true, 'default' => null])
            ->addColumn('license_class', 'string', ['limit' => 40, 'null' => true, 'default' => null])
            ->addColumn('source', 'string', ['limit' => 20, 'null' => false,
                'comment' => 'Provider code: qrz | mcmc | radioid | marts | rapi | …'])
            ->addColumn('source_url', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('raw_payload', 'text', ['null' => true, 'default' => null])
            ->addColumn('fetched_at', 'datetime', ['null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['callsign'], ['unique' => true, 'name' => 'idx_callsign_lookups_callsign'])
            ->addIndex(['expires_at'], ['name' => 'idx_callsign_lookups_expires_at'])
            ->create();
    }
}
