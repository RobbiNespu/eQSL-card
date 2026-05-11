<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupException;
use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;

/**
 * QRZ.com — worldwide callsign registry.
 *
 * STUB. Implementation deferred.
 *
 * Strategy when implementing:
 *  - URL: https://www.qrz.com/db/{CALL}
 *  - QRZ TOS prohibits redistribution; cache hits are strictly per-user.
 *    The orchestrator's `callsign_lookups` cache is shared across users on
 *    this install — verify with the user / clarify privacy posture before
 *    enabling this provider for shared deployments.
 *  - Anonymous page renders most fields (name, QTH, country, grid). Detail
 *    fields (bio, photo, club, license expiry) require login. We only need
 *    the anonymous subset.
 *  - HTML parsing: use DOMDocument + XPath. The bio block lives under
 *    `<dl id="biodata">` (subject to change).
 *  - User-Agent: send a polite UA identifying this app + a contact URL.
 *
 * Until then, `lookup()` returns null so the orchestrator falls through to
 * the next provider. Admin can disable this code from settings to skip the
 * (no-op) call entirely.
 */
final class QrzProvider implements CallsignProviderInterface
{
    public function code(): string
    {
        return 'qrz';
    }

    public function label(): string
    {
        return 'QRZ.com';
    }

    public function supports(string $callsign): bool
    {
        return (bool)preg_match('/^[A-Z0-9\/]{3,15}$/', $callsign);
    }

    public function lookup(string $callsign): ?CallsignLookupResult
    {
        // TODO: implement HTML scrape of https://www.qrz.com/db/{call}
        return null;
    }
}
