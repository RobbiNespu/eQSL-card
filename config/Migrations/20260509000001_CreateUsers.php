<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateUsers extends AbstractMigration
{
    public function change(): void
    {
        // SQLite (used in tests) does not support Phinx's `enum` type, so fall
        // back to a short string there. The 'admin'/'user' invariant is
        // enforced at the ORM layer in T5. MariaDB still gets a real ENUM.
        $isSqlite = $this->getAdapter()->getAdapterType() === 'sqlite';
        $roleColumn = $isSqlite
            ? ['type' => 'string', 'options' => ['limit' => 16, 'default' => 'user']]
            : ['type' => 'enum', 'options' => ['values' => ['admin', 'user'], 'default' => 'user']];

        $this->table('users')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('role', $roleColumn['type'], $roleColumn['options'])
            ->addColumn('callsign', 'string', ['limit' => 20])
            ->addColumn('qth', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('grid_square', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('bio', 'text', ['null' => true])
            ->addColumn('email_verified_at', 'datetime', ['null' => true])
            ->addColumn('last_login_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex('email', ['unique' => true])
            ->addIndex('callsign')
            ->create();
    }
}
