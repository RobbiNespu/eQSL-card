<?php
declare(strict_types=1);

use Migrations\AbstractMigration;
use Migrations\Db\Adapter\MysqlAdapter;

final class CreateTemplates extends AbstractMigration
{
    public function change(): void
    {
        $this->table('templates')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('canvas_width', 'integer')
            ->addColumn('canvas_height', 'integer')
            ->addColumn('layout_json', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('thumbnail_path', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('is_public', 'boolean', ['default' => false])
            ->addColumn('is_approved', 'boolean', ['default' => false])
            ->addColumn('is_system', 'boolean', ['default' => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex('user_id')
            ->addIndex(['is_public', 'is_approved'])
            ->addIndex('is_system')
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
