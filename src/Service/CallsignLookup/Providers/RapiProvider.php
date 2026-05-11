<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;

/**
 * Indonesia RAPI (Radio Antar Penduduk Indonesia) — citizen-band style
 * callsign registry for Indonesia.
 *
 * DEFERRED. RAPI's member directories are split across regional pages
 * (per-province) and published as PDF / Excel lists rather than a single
 * queryable API. Live per-query scraping would hammer the regional pages
 * even when the data isn't on the page the user typed.
 *
 * Same admin-uploaded CSV strategy applies: download official regional
 * lists, convert to CSV, upload via /admin/callsign-directory, and let
 * LocalDirectoryProvider serve them.
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
