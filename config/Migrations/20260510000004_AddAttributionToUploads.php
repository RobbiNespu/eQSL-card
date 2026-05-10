<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Adds attribution metadata to uploads.
 *
 * Every background image gets an `author_name` (free-text or NULL = "unknown
 * source") and a `license` (short code from App\Service\ImageLicense, NULL or
 * 'unknown' = "unknown license"). The CardRenderer draws this as a third
 * footer line on every card: "Background: <author> (<license>) — used by
 * <operator_callsign>".
 */
final class AddAttributionToUploads extends AbstractMigration
{
    public function change(): void
    {
        $this->table('uploads')
            ->addColumn('author_name', 'string', ['limit' => 120, 'null' => true, 'after' => 'sha256_hash'])
            ->addColumn('license', 'string', ['limit' => 40, 'null' => true, 'after' => 'author_name'])
            ->update();
    }
}
