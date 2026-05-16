<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Standardise the bundled system templates on a single typeface.
 *
 * The Classic and Net check-in templates previously mixed Cinzel, Inter
 * Regular, and JetBrains Mono across their fields. The bundled look
 * collapses into a single weight (Inter Bold) so the templates read as
 * one cohesive system rather than three competing typefaces fighting
 * for attention. The seed JSON and the Net check-in seed migration are
 * updated alongside this so fresh installs ship the new look, and this
 * migration retrofits existing installs.
 *
 * Strategy: for each `is_system = 1` template, parse layout_json, walk
 * every field, force `font = 'Inter-Bold.ttf'` unconditionally. Same
 * approach the earlier outline-on-system-templates migration used.
 * Idempotent — re-running writes the same value. User-owned templates
 * are intentionally left untouched.
 */
final class ForceInterBoldOnSystemTemplates extends AbstractMigration
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
                $field['font'] = 'Inter-Bold.ttf';
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
        // Reverse is best-effort: the original mix-and-match font choices
        // per field can't be reconstructed without re-importing the seed,
        // so we set every field back to Inter Regular (visually the most
        // neutral fallback) rather than guess at which one had Cinzel /
        // JetBrains Mono originally. Operators who need the exact pre-
        // migration faces should restore from a DB backup.
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
                $field['font'] = 'Inter-Regular.ttf';
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
