<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;

/**
 * Indonesia RAPI (Radio Antar Penduduk Indonesia) — citizen-band style
 * callsign registry for Indonesia.
 *
 * STUB. Implementation deferred.
 *
 * Strategy when implementing:
 *  - URL: rapi.or.id publishes member lists per region (provincial). The
 *    site uses PHP-rendered pages; structure is regional.
 *  - Coverage is YB / YC / YD / YE / YF / YG / YH amateur prefixes plus
 *    the RAPI-specific JZ prefix for non-amateur callsign holders.
 *    supports() can prefix-filter on these. Confirm the official prefix
 *    list before shipping.
 *  - Pre-import + cron is the right architecture here too — live scraping
 *    per-query would hammer the regional pages.
 */
final class RapiProvider implements CallsignProviderInterface
{
    public function code(): string
    {
        return 'rapi';
    }

    public function label(): string
    {
        return 'Indonesia RAPI';
    }

    public function supports(string $callsign): bool
    {
        $base = preg_replace('/\/[A-Z0-9]+$/', '', $callsign);
        // Indonesia amateur prefixes Y[B-H]; RAPI's own non-amateur prefix
        // JZ. Adjust this list as the user confirms which prefix space
        // RAPI actually covers in its registry.
        return (bool)preg_match('/^(Y[BCDEFGH]|JZ)/', (string)$base);
    }

    public function lookup(string $callsign): ?CallsignLookupResult
    {
        // TODO: scrape / pre-import RAPI regional member lists.
        return null;
    }
}
