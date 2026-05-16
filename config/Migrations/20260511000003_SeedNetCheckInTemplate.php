<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Seed a system template tuned for NCS-issued net check-in cards.
 *
 * Layout reflects the perspective the operator actually has when running a
 * net: the NCS callsign and net title go top (this is who the card is FROM),
 * the participant callsign is the main body subject, and the time/band/mode
 * footer matches the existing "Classic" template so a logbook entry feels
 * consistent across templates. Organisation drops in under the title when
 * present and is invisible when empty (PlaceholderResolver returns "" for
 * missing keys).
 *
 * Idempotent — if a row with name='Net check-in' + is_system=true already
 * exists, the migration leaves it alone. This lets fresh installs get the
 * template while existing installs that already ran a previous attempt are
 * not duplicated.
 */
final class SeedNetCheckInTemplate extends AbstractMigration
{
    public function up(): void
    {
        // Use the application's ORM to insert via parameterized binding so
        // the JSON literal can't break out of the statement. Going through
        // `\Cake\Datasource\ConnectionManager` (rather than the migration
        // adapter's PDO) also picks up the same `quoteIdentifiers` config the
        // app uses everywhere else.
        $conn = \Cake\Datasource\ConnectionManager::get('default');
        $existing = $conn->execute(
            "SELECT id FROM templates WHERE name = 'Net check-in' AND is_system = 1 LIMIT 1"
        )->fetch('assoc');
        if ($existing) {
            return;
        }

        $layout = [
            'fields' => [
                // NCS callsign — big, top-left, in the same display face the
                // Classic template uses for {operator_callsign}.
                ['placeholder' => '{ncs_callsign}',           'x' => 80,  'y' => 110,
                 'font' => 'Inter-Bold.ttf', 'size' => 90, 'color' => '#0b1d3a', 'rotation' => 0, 'outline_color' => '#ffffff', 'outline_width' => 1],
                // Net title sits as the subtitle under NCS.
                ['placeholder' => '{net_title}',              'x' => 80,  'y' => 200,
                 'font' => 'Inter-Bold.ttf', 'size' => 44, 'color' => '#0b1d3a', 'rotation' => 0, 'outline_color' => '#ffffff', 'outline_width' => 1],
                // Organisation — light secondary line. Empty renders to "".
                ['placeholder' => '{net_organisation}',       'x' => 80,  'y' => 250,
                 'font' => 'Inter-Bold.ttf', 'size' => 28, 'color' => '#374151', 'rotation' => 0, 'outline_color' => '#ffffff', 'outline_width' => 1],

                // Body: confirms the participant. Empty {callsign} on a net
                // row would be unusual, since the form requires it.
                ['placeholder' => 'Confirming check-in by {callsign}',
                 'x' => 80,  'y' => 720,
                 'font' => 'Inter-Bold.ttf', 'size' => 40, 'color' => '#0b1d3a', 'rotation' => 0, 'outline_color' => '#ffffff', 'outline_width' => 1],

                // Footer matches Classic's mono row so the look stays cohesive
                // across templates.
                ['placeholder' => 'On {qso_datetime_utc:Y-m-d H:i} UTC',
                 'x' => 80,  'y' => 790,
                 'font' => 'Inter-Bold.ttf', 'size' => 26, 'color' => '#0b1d3a', 'rotation' => 0, 'outline_color' => '#ffffff', 'outline_width' => 1],
                ['placeholder' => 'Band: {band}  Mode: {mode}  Freq: {frequency_mhz} MHz',
                 'x' => 80,  'y' => 830,
                 'font' => 'Inter-Bold.ttf', 'size' => 26, 'color' => '#0b1d3a', 'rotation' => 0, 'outline_color' => '#ffffff', 'outline_width' => 1],
                ['placeholder' => 'RST sent: {rst_sent}   RST recv: {rst_received}',
                 'x' => 80,  'y' => 870,
                 'font' => 'Inter-Bold.ttf', 'size' => 26, 'color' => '#0b1d3a', 'rotation' => 0, 'outline_color' => '#ffffff', 'outline_width' => 1],
            ],
        ];

        $now = date('Y-m-d H:i:s');
        // qso_type intentionally NOT set here — this migration predates the
        // templates.qso_type column. On fresh installs the row is inserted
        // first, and the later migration 20260516000003_AddQsoTypeToTemplates
        // both adds the column AND backfills this row to 'net' via a
        // name-and-is_system match. Touching the column here would fail
        // because the column doesn't exist yet at this point in the
        // migration timeline.
        $conn->insert('templates', [
            'name'             => 'Net check-in',
            'description'      => 'System template for NCS-issued net check-in cards. Uses {ncs_callsign}, {net_title}, {net_organisation} placeholders.',
            'canvas_width'     => 1500,
            'canvas_height'    => 1000,
            'layout_json'      => json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'is_system'        => 1,
            'is_public'        => 1,
            'is_approved'      => 1,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
    }

    public function down(): void
    {
        \Cake\Datasource\ConnectionManager::get('default')
            ->execute("DELETE FROM templates WHERE name = 'Net check-in' AND is_system = 1");
    }
}
