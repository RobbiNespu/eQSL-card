<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;
use Cake\Datasource\ConnectionManager;

/**
 * Resolve callsigns against the local RadioID lookup cache.
 *
 * Replaces the previous per-callsign API provider that hit RadioID on
 * every form submit. We now pull the upstream user registry only when
 * an admin clicks "Sync" (see the per-provider page at
 * /admin/callsign-lookups/provider/radioid_database_dump), populate the
 * local `radioid_registry` table, and answer every lookup from there.
 *
 * Designed to respect RadioID's API use policy
 * (https://radioid.net/api_use_policy): no per-QSO polling, no
 * redistribution of the dataset — the cache is internal to this
 * install only. The lookup result includes a source_url linking back to
 * the upstream record so operators always have a path to the canonical
 * RadioID page.
 *
 * Schema + sync logic: see
 * App\Service\CallsignLookup\RadioIdRegistryImporter.
 */
final class RadioIdDatabaseDumpProvider implements CallsignProviderInterface
{
    public function code(): string
    {
        return 'radioid_database_dump';
    }

    public function label(): string
    {
        return 'RadioID registry (local lookup cache)';
    }

    public function supports(string $callsign): bool
    {
        // Worldwide DMR registry — same shape constraint the old API
        // provider used (alphanumeric + suffix slashes, 3-15 chars).
        return (bool)preg_match('/^[A-Z0-9\/]{3,15}$/', $callsign);
    }

    public function lookup(string $callsign): ?CallsignLookupResult
    {
        $conn = ConnectionManager::get('default');
        $row = $conn->execute(
            'SELECT radio_id, callsign, first_name, last_name, city, state, country
             FROM radioid_registry
             WHERE callsign = ?
             LIMIT 1',
            [$callsign]
        )->fetch('assoc');

        if (!$row) {
            return null;
        }

        $name = trim(trim((string)($row['first_name'] ?? '')) . ' ' . trim((string)($row['last_name'] ?? '')));
        $city = trim((string)($row['city'] ?? ''));
        $state = trim((string)($row['state'] ?? ''));
        $qth = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);
        $country = trim((string)($row['country'] ?? ''));

        $result = new CallsignLookupResult(
            callsign: $callsign,
            source: $this->code(),
            name: $name !== '' ? $name : null,
            qth: $qth !== '' ? $qth : null,
            country: $country !== '' ? $country : null,
            gridSquare: null,
            licenseClass: null,
            sourceUrl: !empty($row['radio_id'])
                ? 'https://radioid.net/database/view?id=' . urlencode((string)$row['radio_id'])
                : null,
            rawPayload: $row,
        );
        return $result->hasUsefulFields() ? $result : null;
    }
}
