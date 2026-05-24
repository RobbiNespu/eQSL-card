<?php
declare(strict_types=1);

namespace App\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\CallsignLookupException;
use App\Service\CallsignLookup\CallsignLookupResult;
use App\Service\CallsignLookup\CallsignProviderInterface;
use Cake\Http\Client;

/**
 * MCMC Malaysia — Register of Apparatus Assignments search.
 *
 * Endpoint:
 *   https://www.mcmc.gov.my/en/legal/registers/register-of-apparatus-assignments-search
 *     ?keyword={callsign}&type=AARadio
 *
 * Returns a Kentico-rendered HTML page with the matching rows in a
 * <table class="table table-striped"> with the columns:
 *
 *   No. | Assignment Holder | Call Sign | Assign No | Expiry Date
 *
 * Field mapping:
 *   - name           ← Assignment Holder (uppercased on the registry; we
 *                       title-case before storing so cards don't shout)
 *   - country        ← always "Malaysia" — MCMC only assigns 9M/9W
 *   - license_class  ← inferred from prefix: 9M*=Class A, 9W*=Class B.
 *                       Conservative; if the prefix doesn't match a known
 *                       pattern we leave the field null.
 *   - source_url     ← the search URL for this callsign (provides a
 *                       click-through receipt for the operator)
 *
 * What we DON'T get from MCMC: QTH, grid square. The directory has
 * neither in this public view. Field stays null.
 *
 * `supports()` is restricted to 9M / 9W prefixes (optionally with a /P /M
 * suffix) so the orchestrator never wastes a request asking MCMC about
 * non-Malaysian callsigns.
 */
final class McmcProvider implements CallsignProviderInterface
{
    private const ENDPOINT = 'https://www.mcmc.gov.my/en/legal/registers/register-of-apparatus-assignments-search';

    /**
     * @param \Cake\Http\Client|null $http HTTP client; defaults to a 10-second timeout instance.
     */
    public function __construct(private ?Client $http = null)
    {
        $this->http ??= new Client(['timeout' => 10, 'redirect' => 3]);
    }

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
        $base = preg_replace('/\/[A-Z0-9]+$/', '', $callsign);
        return (bool)preg_match('/^9[MW]/', (string)$base);
    }

    /**
     * Fetch the MCMC registry page for `$callsign` and extract the exact-match row.
     *
     * @param string $callsign Normalised uppercase callsign (9M / 9W prefixed).
     * @return CallsignLookupResult|null Result on exact match, null when not found.
     * @throws \App\Service\CallsignLookup\CallsignLookupException On HTTP failure or non-200 response.
     */
    public function lookup(string $callsign): ?CallsignLookupResult
    {
        try {
            $resp = $this->http->get(self::ENDPOINT, [
                'keyword' => $callsign,
                'type' => 'AARadio',
            ]);
        } catch (\Throwable $e) {
            throw new CallsignLookupException('MCMC HTTP failed: ' . $e->getMessage(), 0, $e);
        }
        if (!$resp->isOk()) {
            throw new CallsignLookupException('MCMC HTTP ' . $resp->getStatusCode());
        }

        $row = $this->extractRow($resp->getStringBody(), $callsign);
        if ($row === null) {
            return null;
        }

        $sourceUrl = self::ENDPOINT . '?keyword=' . urlencode($callsign) . '&type=AARadio';
        $result = new CallsignLookupResult(
            callsign: $callsign,
            source: $this->code(),
            name: $row['name'] !== '' ? $row['name'] : null,
            qth: null,
            country: 'Malaysia',
            gridSquare: null,
            licenseClass: $this->licenseClassFor($callsign),
            sourceUrl: $sourceUrl,
            rawPayload: $row,
        );
        return $result->hasUsefulFields() ? $result : null;
    }

    /**
     * Pull the matching row out of the search result HTML.
     *
     * The search is substring-based, so a query for "9M2" returns dozens of
     * rows. We scan every row and return ONLY the one whose Call Sign column
     * exactly matches the request — anything else risks shipping a different
     * operator's name on a typo'd callsign.
     *
     * @return array{name:string,callsign:string,assign_no:string,expiry:string}|null
     */
    private function extractRow(string $html, string $callsign): ?array
    {
        // Quick reject: if the requested callsign doesn't appear in the
        // page at all, skip the regex work.
        if (stripos($html, $callsign) === false) {
            return null;
        }

        // The result block is a single `<table class="table table-striped">`.
        // We extract it then walk the data rows inside.
        if (!preg_match(
            '/<table class="table table-striped">(.+?)<\/table>/s',
            $html,
            $tableMatch
        )) {
            return null;
        }
        $tableHtml = $tableMatch[1];

        // Each data row is `<tr>...<td>N</td><td>NAME</td><td>CALL</td>
        // <td>ASSIGN</td><td>DATE</td>...</tr>` — the order is fixed by the
        // Kentico transformation. The regex tolerates whitespace.
        $count = preg_match_all(
            '/<tr>\s*<td>\s*(\d+)\s*<\/td>\s*'   // No.
            . '<td>\s*(.+?)\s*<\/td>\s*'         // Assignment Holder
            . '<td>\s*(.+?)\s*<\/td>\s*'         // Call Sign
            . '<td>\s*(.+?)\s*<\/td>\s*'         // Assign No
            . '<td>\s*(.+?)\s*<\/td>/s',         // Expiry Date
            $tableHtml,
            $rows,
            PREG_SET_ORDER
        );
        if ($count === 0) {
            return null;
        }

        foreach ($rows as $row) {
            $rowCallsign = strtoupper(trim(html_entity_decode(strip_tags($row[3]))));
            if ($rowCallsign === $callsign) {
                return [
                    'name' => $this->titleCase(html_entity_decode(strip_tags($row[2]))),
                    'callsign' => $rowCallsign,
                    'assign_no' => trim(strip_tags($row[4])),
                    'expiry' => trim(strip_tags($row[5])),
                ];
            }
        }
        return null;
    }

    /**
     * MCMC stores names in screaming-caps. Title-case so a card renders
     * "Robbi Nespu Bin Mohamad" instead of "ROBBI NESPU BIN MOHAMAD".
     * "@", "/", "-" and similar separators are preserved verbatim; words
     * shorter than 3 letters (Bin / @ / etc.) keep their case to match
     * Malay naming conventions.
     */
    private function titleCase(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        // mb_convert_case handles UTF-8 properly. Then re-uppercase
        // single-character separators after splitting on whitespace.
        return mb_convert_case(mb_strtolower($raw, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * 9M-prefix callsigns are Class A (full HF), 9W-prefix are Class B
     * (no HF). Inferred from prefix; the registry doesn't expose the
     * class directly in the public search view.
     */
    private function licenseClassFor(string $callsign): ?string
    {
        $base = preg_replace('/\/[A-Z0-9]+$/', '', $callsign);
        if (str_starts_with((string)$base, '9M')) {
            return 'Class A';
        }
        if (str_starts_with((string)$base, '9W')) {
            return 'Class B';
        }
        return null;
    }
}
