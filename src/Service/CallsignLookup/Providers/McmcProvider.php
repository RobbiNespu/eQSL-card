<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;

/**
 * MCMC Malaysia — official amateur radio callsign registry.
 *
 * DEFERRED. Live probing showed MCMC's site has reorganised; the historical
 * amateur-radio pages now 404. When data IS published, it's in PDF format
 * (e.g. AR_CallSign.pdf) which isn't suitable for live per-query scraping —
 * the right architecture is admin-uploaded CSV → LocalDirectoryProvider.
 *
 * Coverage is still 9M / 9W prefixes when populated; supports() keeps the
 * prefix filter so the orchestrator never asks this provider about
 * non-Malaysian callsigns even if a future revision implements live lookup.
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
