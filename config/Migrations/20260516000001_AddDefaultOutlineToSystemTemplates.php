<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Apply a white 1px outline to every field in the bundled system templates.
 *
 * The default backgrounds shipped with the installer often have darker
 * regions where the navy `#0b1d3a` text becomes hard to read; a thin
 * white outline lifts the glyphs off any background without changing
 * the visual identity of the template. The seed JSON and the
 * SeedNetCheckInTemplate migration have been updated to include the
 * outline on new installs, but installs that already ran those need a
 * retrofit — that's what this migration is for.
 *
 * Strategy: for each system template, load its layout_json, walk every
 * field in `fields[]`, force outline_color = '#ffffff' and
 * outline_width = 1 unconditionally. Idempotent — running twice writes
 * the same values. User-owned templates are left untouched; only rows
 * with is_system = 1 are mutated.
 */
final class AddDefaultOutlineToSystemTemplates extends AbstractMigration
{
    public function up(): void
    {
        $conn = \Cake\Datasource\ConnectionManager::get('default');
        $rows = $conn->execute(
            "SELECT id, layout_json FROM templates WHERE is_system = 1"
        )->fetchAll('assoc');

        foreach ($rows as $row) {
            $layout = json_decode((string)$row['layout_json'], true);
            if (!is_array($layout) || !isset($layout['fields']) || !is_array($layout['fields'])) {
                continue;
            }
            foreach ($layout['fields'] as &$field) {
                if (!is_array($field)) {
                    continue;
                }
                $field['outline_color'] = '#ffffff';
                $field['outline_width'] = 1;
            }
            unset($field);

            $conn->update(
                'templates',
                ['layout_json' => json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
                ['id' => (int)$row['id']]
            );
        }
    }

    public function down(): void
    {
        // Reverse the change by setting both outline fields back to defaults
        // (width 0 = no outline). We intentionally don't drop the keys, since
        // the designer + renderer treat width 0 as "no outline" anyway and the
        // shape stays consistent with newly-added fields.
        $conn = \Cake\Datasource\ConnectionManager::get('default');
        $rows = $conn->execute(
            "SELECT id, layout_json FROM templates WHERE is_system = 1"
        )->fetchAll('assoc');

        foreach ($rows as $row) {
            $layout = json_decode((string)$row['layout_json'], true);
            if (!is_array($layout) || !isset($layout['fields']) || !is_array($layout['fields'])) {
                continue;
            }
            foreach ($layout['fields'] as &$field) {
                if (!is_array($field)) {
                    continue;
                }
                $field['outline_color'] = '#000000';
                $field['outline_width'] = 0;
            }
            unset($field);

            $conn->update(
                'templates',
                ['layout_json' => json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
                ['id' => (int)$row['id']]
            );
        }
    }
}
