<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M7 A4 — `net_session_removals` tombstone table.
 *
 * Records each check-in (qso) deletion within a net session so the
 * delta feed can surface a `removed[]` list to live viewers within
 * one poll, instead of the deletion only being visible after a full
 * page refresh. Tombstones are pruned by CleanupController after a
 * short retention window (the live consumers don't need old ones).
 */
final class CreateNetSessionRemovals extends AbstractMigration
{
    public function up(): void
    {
        $this->table('net_session_removals')
            ->addColumn('net_session_id', 'integer', ['null' => false])
            ->addColumn('qso_id', 'integer', ['null' => false])
            ->addColumn('removed_at', 'datetime', ['null' => false])
            ->addIndex(['net_session_id', 'removed_at'])
            ->create();
    }

    public function down(): void
    {
        $this->table('net_session_removals')->drop()->save();
    }
}
