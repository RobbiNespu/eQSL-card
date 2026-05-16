<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Each template now declares which kind of QSO it's for: a 1:1
 * contact card or a net check-in card. The render-from-QSO flow uses
 * this to filter the picker to only templates that match the QSO's
 * own qso_type so users never accidentally produce a contact-shaped
 * card for a net check-in (or vice versa).
 *
 * Schema:
 *   - NOT NULL, default 'contact' — the overwhelmingly common case.
 *   - String column rather than ENUM so adding a third type later is
 *     a no-op schema change (we just validate at the app layer).
 *
 * Backfill: the bundled "Net check-in" system template gets 'net';
 * everything else (the bundled Classic + any user-created templates
 * up to now) stays at 'contact'. Done in raw SQL with a name-and-
 * is_system match so the migration is idempotent and safe even if the
 * Net check-in seed was customised after install.
 */
final class AddQsoTypeToTemplates extends AbstractMigration
{
    public function up(): void
    {
        $this->table('templates')
            ->addColumn('qso_type', 'string', [
                'limit' => 16,
                'null' => false,
                'default' => 'contact',
                'comment' => 'contact | net — which QSO type this template is designed for',
            ])
            ->addIndex('qso_type')
            ->update();

        $conn = \Cake\Datasource\ConnectionManager::get('default');
        $conn->update(
            'templates',
            ['qso_type' => 'net'],
            ['name' => 'Net check-in', 'is_system' => 1]
        );
    }

    public function down(): void
    {
        $this->table('templates')
            ->removeIndexByName('templates_qso_type')
            ->removeColumn('qso_type')
            ->update();
    }
}
