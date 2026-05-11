<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupException;
use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;

/**
 * QRZ.com — worldwide callsign registry.
 *
 * DEFERRED. Live probing showed anonymous /db/{CALL} pages don't surface
 * operator details — only OG meta tags (callsign, page title, avatar URL).
 * Name / QTH / country / grid all sit behind QRZ's login wall. To actually
 * resolve a callsign through QRZ we need ONE of:
 *
 *   1. A paid XML logbook subscription + credentials in app_settings, OR
 *   2. An authenticated session cookie maintained by the app (fragile;
 *      against QRZ TOS for unattended/automated logins).
 *
 * Until that's wired, this provider's `supports()` returns false so the
 * orchestrator skips it entirely — no wasted HTTP, no log noise. The class
 * stays here as the obvious extension point for a future "QRZ XML key"
 * admin setting.
 *
 * If/when we ship the XML API path:
 *   - Endpoint: https://xmldata.qrz.com/xml/current/?username=...&password=...
 *   - Get a session key, then `&s={key}&callsign={CALL}`
 *   - QRZ TOS forbids redistributing scraped data. Keep cache strictly
 *     private + clarify with operators that the lookup happens.
 */
final class QrzProvider implements CallsignProviderInterface
{
    public function code(): string
    {
        return 'qrz';
    }

    public function label(): string
    {
        return 'QRZ.com (requires paid XML key — disabled until configured)';
    }

    public function supports(string $callsign): bool
    {
        // Anonymous scrape produces no useful data, so the orchestrator
        // should never bother invoking lookup() until QRZ credentials are
        // wired. supports() is the right gate — returning false is faster
        // than returning true + null from lookup().
        return false;
    }

    public function lookup(string $callsign): ?CallsignLookupResult
    {
        return null;
    }
}
