<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Templates own their background.
 *
 * Previously every render flow asked the user to pick or upload a
 * background image, and the chosen file was stored on the card row only.
 * That made templates "background-agnostic" but produced two problems:
 *   - Operators accumulated near-duplicate uploads on every render that
 *     looked the same but hashed differently.
 *   - Templates couldn't communicate their intended look — designers
 *     could only define text placement, not what the image looked like
 *     under that text.
 *
 * This migration adds a nullable FK from templates → uploads. A null
 * value means "no specific background, render flows fall back to the
 * site-default image". ON DELETE SET NULL preserves the template when
 * the referenced upload row is removed (it'll silently fall back to the
 * site default rather than 500). The matching index keeps the FK
 * lookup cheap.
 */
final class AddBackgroundUploadIdToTemplates extends AbstractMigration
{
    public function change(): void
    {
        $this->table('templates')
            ->addColumn('background_upload_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex('background_upload_id')
            ->addForeignKey('background_upload_id', 'uploads', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->update();
    }
}
