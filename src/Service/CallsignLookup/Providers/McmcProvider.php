<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;

/**
 * MCMC Malaysia — official amateur radio callsign registry.
 *
 * STUB. Implementation deferred.
 *
 * Strategy when implementing:
 *  - URL: MCMC publishes the list at https://www.mcmc.gov.my/ — exact path
 *    moves periodically. Last known live page is a downloadable PDF /
 *    table. The robust approach is to fetch + parse periodically (cron),
 *    seed a local lookup table, and have this provider read from that table
 *    rather than scraping live per-query.
 *  - Coverage is 9M (Malaysia) and 9W (West Malaysia) prefixes only —
 *    supports() narrows by prefix to avoid wasted calls for non-Malaysian
 *    callsigns.
 *  - Likely fields: callsign, name, license class (A/B), state.
 *
 * Until implementation, `lookup()` returns null and the chain falls through.
 */
final class McmcProvider implements CallsignProviderInterface
{
    public function code(): string
    {
        return 'mcmc';
    }

    public function label(): string
    {
        return 'MCMC Malaysia';
    }

    public function supports(string $callsign): bool
    {
        // 9M-, 9W- only. Strip optional /P /M /MM suffix before checking.
        $base = preg_replace('/\/[A-Z0-9]+$/', '', $callsign);
        return (bool)preg_match('/^9[MW]/', (string)$base);
    }

    public function lookup(string $callsign): ?CallsignLookupResult
    {
        // TODO: scrape / pre-import the MCMC public callsign list.
        return null;
    }
}
