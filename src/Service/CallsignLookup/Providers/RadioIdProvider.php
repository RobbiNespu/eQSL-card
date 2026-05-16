<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupException;
use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;
use Cake\Http\Client;

/**
 * RadioID.net — public DMR ID registry with a free JSON API.
 *
 * Endpoint: https://radioid.net/api/dmr/user/?callsign={CALL}
 *  - Returns {count, results: [...]} where each result has fields like
 *    fname, surname, city, state, country, callsign, id (DMR ID).
 *  - Worldwide coverage (anyone who registered a DMR ID), so we don't
 *    restrict `supports()` by prefix.
 *  - No auth required, no rate-limit headers documented; we treat it as
 *    polite-rate-limit and let the cache absorb the bulk of repeat requests.
 *
 * This is the canonical "simplest provider" — JSON in, DTO out — and serves
 * as the reference shape for the scraper providers that follow.
 */
final class RadioIdProvider implements CallsignProviderInterface
{
    private const ENDPOINT = 'https://radioid.net/api/dmr/user/';

    public function __construct(private ?Client $http = null)
    {
        $this->http ??= new Client(['timeout' => 5, 'redirect' => 3]);
    }

    public function code(): string
    {
        return 'radioid';
    }

    public function label(): string
    {
        return 'RadioID.net';
    }

    public function supports(string $callsign): bool
    {
        // Worldwide DMR registry. Cheap sanity check: must be alphanumeric +
        // forward slash (some calls have /P, /M suffixes) and at least 3 chars.
        return (bool)preg_match('/^[A-Z0-9\/]{3,15}$/', $callsign);
    }

    public function lookup(string $callsign): ?CallsignLookupResult
    {
        try {
            $resp = $this->http->get(self::ENDPOINT, ['callsign' => $callsign]);
        } catch (\Throwable $e) {
            throw new CallsignLookupException('RadioID HTTP failed: ' . $e->getMessage(), 0, $e);
        }
        if (!$resp->isOk()) {
            throw new CallsignLookupException('RadioID HTTP ' . $resp->getStatusCode());
        }
        $body = $resp->getStringBody();
        $parsed = json_decode($body, true);
        if (!is_array($parsed) || !isset($parsed['results']) || !is_array($parsed['results'])) {
            // 200 OK with empty / unexpected body — treat as "no record".
            return null;
        }
        // Pick the first exact-callsign match (the API may return suffixed
        // variants — /P, /M — for the same operator).
        $hit = null;
        foreach ($parsed['results'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (strtoupper((string)($row['callsign'] ?? '')) === $callsign) {
                $hit = $row;
                break;
            }
        }
        $hit ??= $parsed['results'][0] ?? null;
        if (!is_array($hit)) {
            return null;
        }

        $fname = trim((string)($hit['fname'] ?? ''));
        $surname = trim((string)($hit['surname'] ?? ''));
        $name = trim($fname . ' ' . $surname);

        $city = trim((string)($hit['city'] ?? ''));
        $state = trim((string)($hit['state'] ?? ''));
        $qth = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);

        $country = trim((string)($hit['country'] ?? ''));

        $result = new CallsignLookupResult(
            callsign: $callsign,
            source: $this->code(),
            name: $name !== '' ? $name : null,
            qth: $qth !== '' ? $qth : null,
            country: $country !== '' ? $country : null,
            gridSquare: null,           // RadioID doesn't carry grids
            licenseClass: null,
            sourceUrl: self::ENDPOINT . '?callsign=' . urlencode($callsign),
            rawPayload: $hit,
        );
        return $result->hasUsefulFields() ? $result : null;
    }
}
