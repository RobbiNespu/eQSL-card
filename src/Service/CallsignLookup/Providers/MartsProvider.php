<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;

/**
 * MARTS — Malaysia Amateur Radio Transmitters Society member directory.
 *
 * STUB. Implementation deferred.
 *
 * Strategy when implementing:
 *  - URL: marts.org.my publishes a member list (subset of MCMC) with
 *    additional fields like club affiliation and home QTH. Confirm exact
 *    URL + structure before scraping; this site is small and any change
 *    in their HTML will break the provider.
 *  - Likely a "members" page or a downloadable list. May require periodic
 *    pre-import like the MCMC strategy rather than per-query scrape.
 *  - Coverage: MARTS members only (subset of 9M/9W). supports() can be the
 *    same prefix filter as McmcProvider.
 */
final class MartsProvider implements CallsignProviderInterface
{
    public function code(): string
    {
        return 'marts';
    }

    public function label(): string
    {
        return 'MARTS member directory';
    }

    public function supports(string $callsign): bool
    {
        $base = preg_replace('/\/[A-Z0-9]+$/', '', $callsign);
        return (bool)preg_match('/^9[MW]/', (string)$base);
    }

    public function lookup(string $callsign): ?CallsignLookupResult
    {
        // TODO: scrape / pre-import the MARTS member directory.
        return null;
    }
}
