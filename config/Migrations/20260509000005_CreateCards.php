<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateCards extends AbstractMigration
{
    public function change(): void
    {
        $this->table('cards')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('guest_visit_id', 'integer', ['null' => true])
            ->addColumn('qso_id', 'integer', ['null' => true]) // populated in M2
            ->addColumn('template_id', 'integer')
            ->addColumn('upload_id', 'integer')
            ->addColumn('qso_data_json', 'text')
            ->addColumn('png_path', 'string', ['limit' => 255])
            ->addColumn('pdf_path', 'string', ['limit' => 255])
            ->addColumn('share_slug', 'char', ['limit' => 43, 'null' => true])
            ->addColumn('share_password_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('share_revoked_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex('user_id')
            ->addIndex('guest_visit_id')
            ->addIndex('template_id')
            ->addIndex('upload_id')
            ->addIndex('share_slug', ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('guest_visit_id', 'guest_visits', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('template_id', 'templates', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('upload_id', 'uploads', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
