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
