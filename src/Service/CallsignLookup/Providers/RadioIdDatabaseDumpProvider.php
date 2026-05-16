<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;
use Cake\Datasource\ConnectionManager;

/**
 * Lookup against the local mirror of RadioID.net's user registry.
 *
 * Replaces the previous per-callsign API provider. We now download the
 * full registry CSV (~16 MB) on a refresh schedule (admin button at
 * /admin/callsign-lookups/provider/radioid_database_dump) and serve
 * lookups from the local `radioid_registry` table. Trades a periodic
 * batch refresh for instant, zero-network, Cloudflare-free lookups.
 *
 * Schema source: see App\Service\CallsignLookup\RadioIdRegistryImporter
 * for the download + import pipeline.
 */
final class RadioIdDatabaseDumpProvider implements CallsignProviderInterface
{
    public function code(): string
    {
        return 'radioid_database_dump';
    }

    public function label(): string
    {
        return 'RadioID database dump (local mirror)';
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
