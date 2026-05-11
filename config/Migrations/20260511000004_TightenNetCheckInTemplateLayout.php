<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * The initial seed had ~400px of dead space between the header block and
 * the body, which looked barren on the demo background. Tighten the layout:
 *  - Pull the body group (Confirming…, time, band, RST) up to start ~y=480.
 *  - Insert a "Notes" line beneath the organisation so net leaders can
 *    surface the net agenda / topic without needing a custom template.
 *  - Keep the credit footer area free (drawn by CardRenderer's footer band).
 *
 * Idempotent: only updates the row if the existing layout still matches the
 * initial seed signature. If the operator hand-edited the template through
 * the designer, we leave it alone.
 */
final class TightenNetCheckInTemplateLayout extends AbstractMigration
{
    public function up(): void
    {
        $conn = \Cake\Datasource\ConnectionManager::get('default');
        $row = $conn->execute(
            "SELECT id, layout_json FROM templates WHERE name = 'Net check-in' AND is_system = 1 LIMIT 1"
        )->fetch('assoc');
        if (!$row) {
            return;
        }
        $parsed = json_decode((string)$row['layout_json'], true) ?: [];
        // Signature check: the initial seed had the "Confirming check-in by"
        // line at y=720. If the operator has moved it, leave the template
        // alone — we don't want to clobber their edits.
        $hadSeedSignature = false;
        foreach ($parsed['fields'] ?? [] as $f) {
            if (($f['placeholder'] ?? '') === 'Confirming check-in by {callsign}' && (int)($f['y'] ?? 0) === 720) {
                $hadSeedSignature = true;
                break;
            }
        }
        if (!$hadSeedSignature) {
            return;
        }

        $layout = [
            'fields' => [
                // NCS callsign — big display, top-left.
                ['placeholder' => '{ncs_callsign}',           'x' => 80,  'y' => 110,
                 'font' => 'Cinzel-Regular.ttf', 'size' => 90, 'color' => '#0b1d3a', 'rotation' => 0],
                // Net title — bold subtitle under the NCS.
                ['placeholder' => '{net_title}',              'x' => 80,  'y' => 200,
                 'font' => 'Inter-Bold.ttf', 'size' => 44, 'color' => '#0b1d3a', 'rotation' => 0],
                // Organisation — secondary line. PlaceholderResolver returns
                // "" for missing keys so a net without an org renders blank.
                ['placeholder' => '{net_organisation}',       'x' => 80,  'y' => 250,
                 'font' => 'Inter-Regular.ttf', 'size' => 28, 'color' => '#374151', 'rotation' => 0],
                // Notes line — lets the NCS surface a net agenda / topic /
                // welcome message without forking the template.
                ['placeholder' => '{notes}',                  'x' => 80,  'y' => 360,
                 'font' => 'Inter-Regular.ttf', 'size' => 30, 'color' => '#374151', 'rotation' => 0],

                // Body block, pulled up from y=720 so the middle of the
                // canvas isn't empty.
                ['placeholder' => 'Confirming check-in by {callsign}',
                 'x' => 80,  'y' => 520,
                 'font' => 'Inter-Regular.ttf', 'size' => 40, 'color' => '#0b1d3a', 'rotation' => 0],
                ['placeholder' => 'On {qso_datetime_utc:Y-m-d H:i} UTC',
                 'x' => 80,  'y' => 590,
                 'font' => 'JetBrainsMono-Regular.ttf', 'size' => 26, 'color' => '#0b1d3a', 'rotation' => 0],
                ['placeholder' => 'Band: {band}  Mode: {mode}  Freq: {frequency_mhz} MHz',
                 'x' => 80,  'y' => 630,
                 'font' => 'JetBrainsMono-Regular.ttf', 'size' => 26, 'color' => '#0b1d3a', 'rotation' => 0],
                ['placeholder' => 'RST sent: {rst_sent}   RST recv: {rst_received}',
                 'x' => 80,  'y' => 670,
                 'font' => 'JetBrainsMono-Regular.ttf', 'size' => 26, 'color' => '#0b1d3a', 'rotation' => 0],
            ],
        ];

        $conn->update(
            'templates',
            ['layout_json' => json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
            ['id' => (int)$row['id']]
        );
    }

    public function down(): void
    {
        // No-op: reverting would require restoring the prior seed JSON exactly,
        // which gets messy when chained with the seed migration. If you need to
        // start over, rollback both this and the seed migration, then re-migrate.
    }
}
