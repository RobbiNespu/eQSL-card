<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Rename `uploads` to `card_backgrounds` to make the surface name
 * match what it actually stores — background images for QSL cards.
 *
 * Foreign keys from `cards.upload_id` and `templates.background_upload_id`
 * continue to reference the renamed table; Phinx (and the underlying
 * ALTER TABLE RENAME on MySQL/MariaDB) updates the FK target identifier
 * atomically. The column names on the referencing tables stay put
 * because they're descriptive in their own right and renaming them
 * would touch a lot of application code for no naming gain.
 *
 * No data migration — same rows, same columns, just a new label on the
 * outside of the table.
 */
final class RenameUploadsToCardBackgrounds extends AbstractMigration
{
    public function up(): void
    {
        $this->table('uploads')->rename('card_backgrounds')->update();
    }

    public function down(): void
    {
        $this->table('card_backgrounds')->rename('uploads')->update();
    }
}
