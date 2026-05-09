<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateAuditLogs extends AbstractMigration
{
    public function change(): void
    {
        $this->table('audit_logs')
            ->addColumn('actor_user_id', 'integer', ['null' => true])
            ->addColumn('actor_guest_visit_id', 'integer', ['null' => true])
            ->addColumn('event', 'string', ['limit' => 80])
            ->addColumn('target_type', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('target_id', 'integer', ['null' => true])
            ->addColumn('metadata_json', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex('event')
            ->addIndex('actor_user_id')
            ->addIndex(['target_type', 'target_id'])
            ->addIndex('created_at')
            ->addForeignKey('actor_user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('actor_guest_visit_id', 'guest_visits', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
