<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\OperationLog;
use Cake\I18n\DateTime;

/**
 * M5 T17 — ADIF exporter for activations.
 *
 * ADIF (Amateur Data Interchange Format) is the text-based standard
 * every awards portal accepts. Each QSO is a sequence of tagged fields
 * (`<CALL:5>W1AW`) terminated by `<EOR>`; an optional header with
 * `<ADIF_VER>` + `<PROGRAMID>` precedes them, terminated by `<EOH>`.
 *
 * Spec: https://www.adif.org/315/ADIF_315.htm
 *
 * Our output is ADIF 3.1.4 compatible. Field-list is the intersection
 * of what POTA, SOTA, IOTA, and LoTW care about for a portable
 * activation upload:
 *
 *   CALL              — worked callsign
 *   QSO_DATE          — YYYYMMDD (UTC)
 *   TIME_ON           — HHMMSS (UTC)
 *   BAND              — e.g. "20m"
 *   MODE              — e.g. "SSB", "CW", "FT8"
 *   FREQ              — MHz, decimal
 *   RST_SENT / RST_RCVD
 *   STATION_CALLSIGN  — the activator's callsign (from user profile)
 *   OPERATOR          — usually same as STATION_CALLSIGN
 *   MY_GRIDSQUARE     — from the activation (overrides any per-QSO value)
 *   GRIDSQUARE        — worked station's grid (if logged)
 *   NAME, QTH         — worked station details (if logged)
 *   NOTES             — free-text per-QSO notes
 *   MY_POTA_REF       — inferred from activation.code if "POTA-*"
 *   MY_SOTA_REF       — inferred from activation.code if "SOTA-*"
 *   MY_IOTA           — inferred from activation.code if "IOTA-*"
 *
 * Comments above the header carry the activation metadata for
 * human readers. POTA/SOTA portals ignore them.
 */
final class AdifExporter
{
    /**
     * Generate the ADIF document for the given activation + its QSOs.
     *
     * @param object $activation  An Activation entity (code, name, grid_square,
     *                            started_at, ended_at, notes)
     * @param iterable<object> $qsos  Iterable of Qso entities scoped to
     *                                this activation_id
     * @param string $stationCallsign The activator's own callsign for
     *                                STATION_CALLSIGN / OPERATOR fields
     * @return string The complete ADIF document as a UTF-8 string
     */
    public function export(object $activation, iterable $qsos, string $stationCallsign): string
    {
        $out = $this->renderHeader($activation, $stationCallsign);

        [$myPota, $mySota, $myIota] = $this->inferActivatorRefs((string)$activation->code);
        $myGrid = (string)($activation->grid_square ?? '');

        foreach ($qsos as $qso) {
            $out .= $this->renderQso($qso, $stationCallsign, $myGrid, $myPota, $mySota, $myIota);
        }

        OperationLog::event('adif.export', [
            'activation_code' => (string)$activation->code,
        ]);

        return $out;
    }

    /**
     * Build the ADIF file header block with comment metadata and mandatory tags.
     *
     * @param object $activation Activation entity (name, code, grid_square, started_at, ended_at).
     * @param string $stationCallsign Activator's callsign for the OPERATOR comment line.
     * @return string ADIF header ending with `<EOH>`.
     */
    private function renderHeader(object $activation, string $stationCallsign): string
    {
        $started = $activation->started_at instanceof \DateTimeInterface
            ? $activation->started_at->format('Y-m-d H:i:s')
            : (string)$activation->started_at;
        $ended = $activation->ended_at instanceof \DateTimeInterface
            ? $activation->ended_at->format('Y-m-d H:i:s')
            : ((string)($activation->ended_at ?? '') ?: '(still active)');

        // Comments before the first <ADIF_VER> tag are part of the ADIF
        // spec and ignored by parsers — safe place for human metadata.
        $h  = "## eQSL Card export\n";
        $h .= "## Activation: " . (string)$activation->name . " (" . (string)$activation->code . ")\n";
        if (!empty($activation->grid_square)) {
            $h .= "## Grid: " . (string)$activation->grid_square . "\n";
        }
        $h .= "## Operator: " . $stationCallsign . "\n";
        $h .= "## Started (UTC): " . $started . "\n";
        $h .= "## Ended   (UTC): " . $ended . "\n";
        $h .= "##\n";

        $h .= $this->tag('ADIF_VER', '3.1.4');
        $h .= $this->tag('PROGRAMID', 'eQSL Card');
        $h .= $this->tag('PROGRAMVERSION', '1.3.0');
        $h .= $this->tag('CREATED_TIMESTAMP', (new DateTime('now', 'UTC'))->format('Ymd His'));
        $h .= "<EOH>\n\n";

        return $h;
    }

    /**
     * Render a single QSO entity as a sequence of ADIF tagged fields terminated by `<EOR>`.
     *
     * @param object $qso            QSO entity (call_worked, qso_datetime_utc, band, mode, etc.).
     * @param string $stationCallsign Activator's callsign for STATION_CALLSIGN / OPERATOR.
     * @param string $myGrid         Activation grid square for MY_GRIDSQUARE (may be empty).
     * @param string $myPota         POTA reference string (may be empty).
     * @param string $mySota         SOTA reference string (may be empty).
     * @param string $myIota         IOTA reference string (may be empty).
     * @return string ADIF record ending with `<EOR>`.
     */
    private function renderQso(
        object $qso,
        string $stationCallsign,
        string $myGrid,
        string $myPota,
        string $mySota,
        string $myIota
    ): string {
        $r  = '';
        $r .= $this->tagIf('CALL', (string)($qso->call_worked ?? ''));
        $r .= $this->renderDateTime($qso->qso_datetime_utc ?? null);
        $r .= $this->tagIf('BAND', (string)($qso->band ?? ''));
        $r .= $this->tagIf('MODE', strtoupper((string)($qso->mode ?? '')));
        // FREQ is the second-most-strict field — POTA/SOTA reject "14.07415"
        // with extra precision sometimes. We trim trailing zeroes but keep
        // the decimal to make it valid ADIF (numeric type).
        if (!empty($qso->frequency_mhz)) {
            $r .= $this->tag('FREQ', $this->formatFrequency((string)$qso->frequency_mhz));
        }
        $r .= $this->tagIf('RST_SENT', (string)($qso->rst_sent ?? ''));
        $r .= $this->tagIf('RST_RCVD', (string)($qso->rst_received ?? ''));

        // Activator (us) details. MY_* fields are what awards portals key on.
        $r .= $this->tagIf('STATION_CALLSIGN', $stationCallsign);
        $r .= $this->tagIf('OPERATOR', $stationCallsign);
        $r .= $this->tagIf('MY_GRIDSQUARE', $myGrid);
        $r .= $this->tagIf('MY_POTA_REF', $myPota);
        $r .= $this->tagIf('MY_SOTA_REF', $mySota);
        $r .= $this->tagIf('MY_IOTA', $myIota);

        // Worked station details (if logged).
        $r .= $this->tagIf('GRIDSQUARE', (string)($qso->grid_square ?? ''));
        $r .= $this->tagIf('NAME', (string)($qso->operator_name ?? ''));
        $r .= $this->tagIf('QTH', (string)($qso->operator_qth ?? ''));
        $r .= $this->tagIf('NOTES', (string)($qso->notes ?? ''));

        $r .= "<EOR>\n\n";
        return $r;
    }

    /**
     * Render a tag with length-prefixed value: <TAGNAME:LENGTH>value.
     * ADIF uses byte length, not char length — but for ASCII fields
     * the two are identical. UTF-8 multibyte values: strlen() gives
     * the byte count which is the correct ADIF semantic.
     */
    private function tag(string $name, string $value): string
    {
        return '<' . $name . ':' . strlen($value) . '>' . $value . ' ';
    }

    /**
     * Emit a tag only when the value is non-empty; avoids `<FIELD:0>` noise.
     *
     * @param string $name  ADIF field name (uppercase).
     * @param string $value Field value.
     * @return string ADIF tag string, or empty string when value is blank.
     */
    private function tagIf(string $name, string $value): string
    {
        return $value !== '' ? $this->tag($name, $value) : '';
    }

    /**
     * Render QSO_DATE and TIME_ON tags from a datetime value.
     *
     * Accepts a DateTimeInterface, a parseable string, or null. Returns an
     * empty string on null or unparseable input so callers never emit a
     * malformed date tag.
     *
     * @param \DateTimeInterface|string|null $value The QSO datetime (UTC assumed).
     * @return string Two ADIF tags (`QSO_DATE` + `TIME_ON`), or empty string on failure.
     */
    private function renderDateTime($value): string
    {
        if ($value === null) return '';
        if ($value instanceof \DateTimeInterface) {
            $dt = $value;
        } else {
            try {
                $dt = new \DateTimeImmutable((string)$value, new \DateTimeZone('UTC'));
            } catch (\Exception $e) {
                return '';
            }
        }
        return $this->tag('QSO_DATE', $dt->format('Ymd'))
             . $this->tag('TIME_ON', $dt->format('His'));
    }

    /**
     * Strip trailing zeroes from a frequency string but always keep
     * a decimal point. "14.20000" -> "14.2", "7" -> "7.0".
     */
    private function formatFrequency(string $mhz): string
    {
        $mhz = trim($mhz);
        if (!is_numeric($mhz)) return $mhz;
        $f = (float)$mhz;
        $s = rtrim(rtrim(sprintf('%.5f', $f), '0'), '.');
        return str_contains($s, '.') ? $s : $s . '.0';
    }

    /**
     * Map our internal activation.code (free-text) to the per-award MY_*
     * fields. We support three prefix forms; anything else returns
     * empty MY_POTA_REF / MY_SOTA_REF / MY_IOTA and the activator
     * code only appears in the comment header.
     *
     * Accepted forms (case-insensitive prefix):
     *   POTA-K-1234       -> POTA: K-1234
     *   POTA K-1234       -> POTA: K-1234
     *   SOTA-9M2/PR-001   -> SOTA: 9M2/PR-001
     *   SOTA 9M2/PR-001   -> SOTA: 9M2/PR-001
     *   IOTA-AS-058       -> IOTA: AS-058
     *
     * @return array{0:string,1:string,2:string} [pota, sota, iota]
     */
    private function inferActivatorRefs(string $code): array
    {
        $pota = $sota = $iota = '';
        $trimmed = trim($code);
        if ($trimmed === '') {
            return [$pota, $sota, $iota];
        }
        if (preg_match('/^POTA[\s\-_:]+(.+)$/i', $trimmed, $m)) {
            $pota = trim($m[1]);
        } elseif (preg_match('/^SOTA[\s\-_:]+(.+)$/i', $trimmed, $m)) {
            $sota = trim($m[1]);
        } elseif (preg_match('/^IOTA[\s\-_:]+(.+)$/i', $trimmed, $m)) {
            $iota = trim($m[1]);
        }
        return [$pota, $sota, $iota];
    }
}
