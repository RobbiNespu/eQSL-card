<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Background images. The unique sha256_hash constraint deduplicates the
 * physical file on disk: when two users upload the same image, only one
 * `uploads` row exists, owned by the first uploader. Subsequent users'
 * `cards` rows reference the same upload — `uploads.user_id` is "who
 * first introduced this background to the system", NOT "who has rights
 * to use it." On hard-delete of the first uploader, `user_id` becomes
 * NULL but the row remains so other users' cards keep rendering.
 */
final class CreateUploads extends AbstractMigration
{
    public function change(): void
    {
        $this->table('uploads')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('guest_visit_id', 'integer', ['null' => true])
            ->addColumn('original_filename', 'string', ['limit' => 255])
            ->addColumn('storage_path', 'string', ['limit' => 255])
            ->addColumn('mime_type', 'string', ['limit' => 60])
            ->addColumn('width_px', 'integer')
            ->addColumn('height_px', 'integer')
            ->addColumn('file_size_bytes', 'integer')
            ->addColumn('sha256_hash', 'char', ['limit' => 64])
            ->addColumn('created_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex('sha256_hash', ['unique' => true])
            ->addIndex('user_id')
            ->addIndex('guest_visit_id')
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('guest_visit_id', 'guest_visits', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
