<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M6 — `net_sessions` table. A first-class net (NCS) session that groups
 * its check-ins via qsos.net_session_id. Mirrors the activations pattern.
 */
final class CreateNetSessions extends AbstractMigration
{
    public function up(): void
    {
        $this->table('net_sessions')
            ->addColumn('owner_id', 'integer', ['null' => false])
            ->addColumn('net_title', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('net_organisation', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('frequency_mhz', 'decimal', ['precision' => 10, 'scale' => 5, 'null' => true])
            ->addColumn('band', 'string', ['limit' => 8, 'null' => true])
            ->addColumn('mode', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 12, 'null' => false, 'default' => 'scheduled'])
            ->addColumn('public_slug', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('is_public', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('logger_token', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('ended_at', 'datetime', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['owner_id', 'status'])
            ->addIndex(['public_slug'], ['unique' => true])
            ->addIndex(['owner_id', 'net_title'])
            ->create();
    }

    public function down(): void
    {
        $this->table('net_sessions')->drop()->save();
    }
}
