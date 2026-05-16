<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateGuestVisits extends AbstractMigration
{
    public function change(): void
    {
        $this->table('guest_visits')
            ->addColumn('session_token', 'char', ['limit' => 43])
            ->addColumn('ip_hash', 'char', ['limit' => 64])
            ->addColumn('user_agent_hash', 'char', ['limit' => 64])
            ->addColumn('created_at', 'datetime')
            ->addColumn('last_seen_at', 'datetime')
            ->addIndex('session_token', ['unique' => true])
            ->addIndex('ip_hash')
            ->create();
    }
}
