<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/** M6 — co-logger membership for a net session. */
final class CreateNetSessionLoggers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('net_session_loggers')
            ->addColumn('net_session_id', 'integer', ['null' => false])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('added_via', 'string', ['limit' => 10, 'null' => false, 'default' => 'owner'])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['net_session_id', 'user_id'], ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('net_session_loggers')->drop()->save();
    }
}
