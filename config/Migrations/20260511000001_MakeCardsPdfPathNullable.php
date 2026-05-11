<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Storage saver: pre-rendered PDFs were just FPDF wrappers around the PNG,
 * doubling per-card disk usage. We now generate the PDF on demand from the
 * rendered card image when the user clicks "Download PDF" instead of writing
 * one to disk at render time. `pdf_path` becomes optional — old rows keep
 * their pre-rendered file paths so existing downloads keep working; new rows
 * persist with NULL and the download endpoint streams the PDF on request.
 */
final class MakeCardsPdfPathNullable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('cards')
            ->changeColumn('pdf_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->update();
    }
}
