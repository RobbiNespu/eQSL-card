<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreatePasswordResets extends AbstractMigration
{
    public function change(): void
    {
        $this->table('password_resets')
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('token_hash', 'char', ['limit' => 64])
            ->addColumn('expires_at', 'datetime')
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex('email')
            ->addIndex('token_hash', ['unique' => true])
            ->create();
    }
}
