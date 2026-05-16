<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M5 T21 — qsos.client_uuid for offline-sync idempotency.
 *
 * When the operator logs a QSO offline, the IndexedDB queue assigns
 * it a client-side UUID. When the network comes back, the sync
 * engine POSTs each queued row with that UUID. If the same row gets
 * POSTed twice (network flapped mid-sync, or the user manually
 * retried before the first response landed), the server-side dedup
 * key is (user_id, client_uuid) — second POST returns the existing
 * row instead of creating a duplicate.
 *
 * Nullable so historic QSOs (and any future direct API/import
 * flows) don't need a UUID. UNIQUE INDEX on (user_id, client_uuid)
 * with NULL allowed — MySQL treats NULL as distinct so multiple
 * NULL rows per user are fine.
 */
final class AddClientUuidToQsos extends AbstractMigration
{
    public function up(): void
    {
        $this->table('qsos')
            ->addColumn('client_uuid', 'string', [
                'limit' => 36,
                'null' => true,
                'after' => 'notes',
                'comment' => 'Client-generated UUID for offline-sync idempotency. NULL for QSOs created directly via the server (manual form, ADIF import).',
            ])
            ->addIndex(['user_id', 'client_uuid'], [
                'name' => 'idx_qsos_user_client_uuid',
                'unique' => true,
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('qsos')
            ->removeIndexByName('idx_qsos_user_client_uuid')
            ->removeColumn('client_uuid')
            ->update();
    }
}
