<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;

/**
 * MARTS — Malaysia Amateur Radio Transmitters Society member directory.
 *
 * DEFERRED. Live probing showed marts.org.my returns HTTP 508 (resource
 * limit exceeded — shared hosting under load) often enough that live
 * scraping isn't viable. The same pre-import / admin-CSV-upload strategy
 * as MCMC fits here: download the official member list, upload via
 * /admin/callsign-lookups/provider/local, and let LocalDirectoryProvider serve it.
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
