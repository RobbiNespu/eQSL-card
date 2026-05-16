<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Canonical lists of amateur-radio bands and modes used in dropdowns.
 *
 * Bands follow ADIF conventions (lowercase metres / centimetres). Modes follow
 * ADIF MODE values where possible. Validators in `QsosTable` stay permissive
 * (free-text up to 8 / 20 chars) so ADIF imports with non-standard values
 * still load — these constants drive the UI dropdowns only, not enforcement.
 */
final class HamRadio
{
    /** Bands ordered roughly by frequency (LF → SHF). */
    public const BANDS = [
        '2200m', '630m',
        '160m', '80m', '60m', '40m', '30m', '20m', '17m', '15m', '12m', '10m',
        '6m', '4m', '2m', '1.25m',
        '70cm', '33cm', '23cm', '13cm', '9cm', '6cm', '3cm',
    ];

    /**
     * Frequency edges (MHz, inclusive) for each band, narrowed to the
     * Malaysian amateur-service allocations (MCMC / RAEM Class A & B).
     * Operators in other ITU regions have wider edges on several bands
     * (e.g. USA 40m runs to 7.300, Region 1 has a 4m band); a typed
     * frequency outside the table below simply leaves the band picker
     * untouched rather than guessing.
     *
     * Bands not currently allocated to MY hams (160m, 4m, 1.25m, 33cm,
     * 23cm and above) are intentionally absent here. Those bands still
     * appear in the BANDS dropdown above so an operator can pick them
     * manually for cross-region QSOs — they just don't auto-fill.
     *
     * @var array<string, array{0:float,1:float}>
     */
    public const BAND_RANGES = [
        '80m'  => [3.500,   3.900],
        '60m'  => [5.3515,  5.3665],
        '40m'  => [7.000,   7.200],
        '30m'  => [10.100,  10.150],
        '20m'  => [14.000,  14.350],
        '17m'  => [18.068,  18.168],
        '15m'  => [21.000,  21.450],
        '12m'  => [24.890,  24.990],
        '10m'  => [28.000,  29.700],
        '6m'   => [50.000,  54.000],
        '2m'   => [144.000, 148.000],
        '70cm' => [430.000, 440.000],
    ];

    /** Common modes, grouped by family. */
    public const MODES = [
        // Voice
        'SSB', 'USB', 'LSB', 'AM', 'FM',
        // Morse
        'CW',
        // Weak-signal digital (WSJT-X family)
        'FT8', 'FT4', 'JT65', 'JT9', 'JS8',
        // Text-mode digital
        'RTTY', 'PSK31', 'PSK63', 'OLIVIA',
        // Digital voice
        'DMR', 'D-STAR', 'C4FM', 'P25',
        // Data / packet
        'PACKET', 'APRS', 'VARA',
        // Image
        'ATV', 'SSTV',
        // Catch-all
        'OTHER',
    ];

    /**
     * Build a key=>label list for a `<select>`, optionally including a
     * pre-existing `$current` value that's not in the canonical list — so
     * editing an ADIF-imported QSO with a quirky band/mode still selects
     * the user's stored value rather than defaulting to blank.
     *
     * @return array<string, string>
     */
    public static function bandOptions(?string $current = null): array
    {
        return self::buildOptions(self::BANDS, $current);
    }

    /** @return array<string, string> */
    public static function modeOptions(?string $current = null): array
    {
        return self::buildOptions(self::MODES, $current);
    }

    /**
     * Resolve the canonical band for an RF frequency, in MHz. Returns
     * null when the frequency is outside every known amateur band — the
     * caller should leave the band field alone rather than guess.
     *
     * Use cases: auto-fill the band picker when an operator types a
     * frequency in the QSO form, and the same logic for ADIF imports
     * that supply FREQ but no BAND tag.
     */
    public static function bandForFrequency(float|int|string|null $mhz): ?string
    {
        if ($mhz === null || $mhz === '') {
            return null;
        }
        $f = (float)$mhz;
        if ($f <= 0) {
            return null;
        }
        foreach (self::BAND_RANGES as $band => [$lo, $hi]) {
            if ($f >= $lo && $f <= $hi) {
                return $band;
            }
        }
        return null;
    }

    /**
     * @param list<string> $canonical
     * @return array<string, string>
     */
    private static function buildOptions(array $canonical, ?string $current): array
    {
        $values = $canonical;
        if ($current !== null && $current !== '' && !in_array($current, $canonical, true)) {
            array_unshift($values, $current);
        }
        $out = [];
        foreach ($values as $v) {
            $out[$v] = $v;
        }
        return $out;
    }
}
