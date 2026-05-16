<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Rewrite the `callsign_lookup_providers` app_setting so any installs
 * that previously enabled the per-callsign `radioid` provider keep
 * working under the new code name `radioid_database_dump`.
 *
 * The CSV-shaped value is a comma-separated list — we splice the codes
 * apart, swap in-place, dedupe, and rejoin so nothing else in the
 * chain order is disturbed.
 */
final class RenameRadioidProviderSetting extends AbstractMigration
{
    public function up(): void
    {
        $this->rewrite(
            fn (string $code): string => $code === 'radioid' ? 'radioid_database_dump' : $code
        );
    }

    public function down(): void
    {
        $this->rewrite(
            fn (string $code): string => $code === 'radioid_database_dump' ? 'radioid' : $code
        );
    }

    /**
     * Apply $rewriter to each comma-separated code in the
     * callsign_lookup_providers app_setting, dedupe, write back. The
     * app_settings table is keyed on the `key` column (no `id`), so
     * the WHERE/update both target it directly.
     */
    private function rewrite(callable $rewriter): void
    {
        $conn = \Cake\Datasource\ConnectionManager::get('default');
        $row = $conn->execute(
            "SELECT `key`, value FROM app_settings WHERE `key` = 'callsign_lookup_providers' LIMIT 1"
        )->fetch('assoc');
        if (!$row) {
            return;
        }
        $codes = array_values(array_filter(array_map('trim', explode(',', (string)$row['value']))));
        $out = [];
        foreach ($codes as $code) {
            $rewritten = $rewriter($code);
            if (!in_array($rewritten, $out, true)) {
                $out[] = $rewritten;
            }
        }
        $newValue = implode(',', $out);
        if ($newValue !== (string)$row['value']) {
            $conn->update(
                'app_settings',
                ['value' => $newValue],
                ['key' => 'callsign_lookup_providers']
            );
        }
    }
}
