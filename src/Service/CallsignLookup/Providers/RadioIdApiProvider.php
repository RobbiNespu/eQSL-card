<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupException;
use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;
use Cake\Http\Client;

/**
 * RadioID.net — general users JSON API (broader than the DMR-only sibling).
 *
 * Endpoint: https://radioid.net/api/users?callsign={CALL}
 *  - Returns the same {count, results:[...]} envelope as the DMR endpoint,
 *    with per-row fields {callsign, fname, surname, name, city, state,
 *    country, id, radio_id, ...}. Coverage is wider than the DMR-only
 *    sibling because it draws from the whole user registry, not just
 *    DMR-registered IDs.
 *  - Distinguished from RadioIdProvider (code `radioid`, /api/dmr/user/)
 *    by code (`radioid_api`) so both can sit in the chain at once — the
 *    DMR endpoint usually has tighter data for DMR ops, the users
 *    endpoint catches the long tail of non-DMR registrations.
 *
 * Cloudflare caveat: the production site occasionally fronts requests
 * with Cloudflare's bot challenge. We send browser-like headers (UA,
 * Accept, Accept-Language, X-Requested-With) which clears the basic
 * "no UA / fetch lib" heuristic; if CF still serves a 403/503/CAPTCHA
 * page, we surface a clear exception so the chain logs it and moves on
 * rather than trying to scrape an HTML interstitial. No cookie jar,
 * no JS — solving real CF challenges server-side is out of scope.
 */
final class RadioIdApiProvider implements CallsignProviderInterface
{
    private const ENDPOINT = 'https://radioid.net/api/users';

    /**
     * Browser-shaped header set. Matches what a real Firefox session
     * sends to the same XHR endpoint — enough to pass casual bot
     * filters without pretending to be a logged-in user.
     */
    private const HEADERS = [
        'User-Agent'       => 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
        'Accept'           => 'application/json,*/*;q=0.9',
        'Accept-Language'  => 'en-US,en;q=0.9',
        'Referer'          => 'https://radioid.net/api/',
        'X-Requested-With' => 'XMLHttpRequest',
        'DNT'              => '1',
    ];

    public function __construct(private ?Client $http = null)
    {
        $this->http ??= new Client(['timeout' => 8, 'redirect' => 3]);
    }

    public function code(): string
    {
        return 'radioid_api';
    }

    public function label(): string
    {
        return 'RadioID API (users)';
    }

    public function supports(string $callsign): bool
    {
        // Worldwide registry. Same shape as RadioIdProvider — alphanumeric
        // + suffix slashes, 3–15 chars.
        return (bool)preg_match('/^[A-Z0-9\/]{3,15}$/', $callsign);
    }

    public function lookup(string $callsign): ?CallsignLookupResult
    {
        try {
            $resp = $this->http->get(
                self::ENDPOINT,
                ['callsign' => $callsign],
                ['headers' => self::HEADERS],
            );
        } catch (\Throwable $e) {
            throw new CallsignLookupException('radioid_api HTTP failed: ' . $e->getMessage(), 0, $e);
        }

        // 403 / 503 with a Cloudflare body is the typical CF block signature.
        // Surface a precise error so the chain logs it and falls through to
        // the next provider instead of trying to parse an HTML interstitial.
        if (!$resp->isOk()) {
            $cf = $resp->getHeader('cf-mitigated') ?: $resp->getHeader('cf-ray');
            $hint = !empty($cf) ? ' (Cloudflare blocked)' : '';
            throw new CallsignLookupException('radioid_api HTTP ' . $resp->getStatusCode() . $hint);
        }

        $body = $resp->getStringBody();
        $parsed = json_decode($body, true);
        if (!is_array($parsed) || !isset($parsed['results']) || !is_array($parsed['results'])) {
            // 200 with non-JSON body (HTML challenge page after CF lets it
            // through but redirects) — treat as miss; the chain continues.
            return null;
        }

        // Prefer an exact-callsign match; the registry may return suffixed
        // variants (/P, /M) for the same operator.
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

        // Name: prefer the pre-composed `name` field, fall back to
        // fname + surname when it's empty (some old rows have no `name`).
        $name = trim((string)($hit['name'] ?? ''));
        if ($name === '') {
            $fname   = trim((string)($hit['fname'] ?? ''));
            $surname = trim((string)($hit['surname'] ?? ''));
            $name    = trim($fname . ' ' . $surname);
        }

        $city  = trim((string)($hit['city'] ?? ''));
        $state = trim((string)($hit['state'] ?? ''));
        $qth   = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);

        $country = trim((string)($hit['country'] ?? ''));

        $result = new CallsignLookupResult(
            callsign: $callsign,
            source: $this->code(),
            name: $name !== '' ? $name : null,
            qth: $qth !== '' ? $qth : null,
            country: $country !== '' ? $country : null,
            gridSquare: null,           // /api/users doesn't carry grids
            licenseClass: null,
            sourceUrl: self::ENDPOINT . '?callsign=' . urlencode($callsign),
            rawPayload: $hit,
        );

        return $result->hasUsefulFields() ? $result : null;
    }
}
