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
     * Frequency edges (MHz, inclusive) for each band. Ranges are the union
     * across ITU Regions 1/2/3 so the lookup gives the right answer for
     * most operators regardless of country. Where a band only exists in
     * one region (e.g. 4m in Region 1, 33cm in Region 2) we still include
     * it — the operator hears the band they were on.
     *
     * Order mirrors BANDS so lookup hits low → high.
     *
     * @var array<string, array{0:float,1:float}>
     */
    public const BAND_RANGES = [
        '2200m' => [0.1357, 0.1378],
        '630m'  => [0.472,  0.479],
        '160m'  => [1.8,    2.0],
        '80m'   => [3.5,    4.0],
        '60m'   => [5.3,    5.4],
        '40m'   => [7.0,    7.3],
        '30m'   => [10.1,   10.15],
        '20m'   => [14.0,   14.35],
        '17m'   => [18.068, 18.168],
        '15m'   => [21.0,   21.45],
        '12m'   => [24.89,  24.99],
        '10m'   => [28.0,   29.7],
        '6m'    => [50.0,   54.0],
        '4m'    => [70.0,   70.5],
        '2m'    => [144.0,  148.0],
        '1.25m' => [222.0,  225.0],
        '70cm'  => [420.0,  450.0],
        '33cm'  => [902.0,  928.0],
        '23cm'  => [1240.0, 1300.0],
        '13cm'  => [2300.0, 2450.0],
        '9cm'   => [3300.0, 3500.0],
        '6cm'   => [5650.0, 5925.0],
        '3cm'   => [10000.0, 10500.0],
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
