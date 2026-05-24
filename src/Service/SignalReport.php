<?php
declare(strict_types=1);

namespace App\Service;

/**
 * M6 — extract signal strength (1–9) from an RST/RS report string.
 * RST = Readability(1-5) Strength(1-9) [Tone]. The strength is the
 * SECOND character. RS (phone) is two chars: R then S. Either way the
 * strength digit is index 1.
 */
final class SignalReport
{
    /**
     * Extract the signal strength digit (1–9) from an RST or RS report string.
     *
     * The strength digit is always the second character of the numeric portion:
     * RST "59" or "599" → 9; RS "57" → 7. Returns null for null input, reports
     * with fewer than two digits, or values outside 1–9.
     *
     * @param string|null $rst Raw RST/RS report (e.g. "599", "59", "57T").
     * @return int|null Signal strength 1–9, or null if indeterminate.
     */
    public static function strength(?string $rst): ?int
    {
        if ($rst === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $rst);
        if ($digits === '' || strlen($digits) < 2) {
            return null;
        }
        $s = (int)$digits[1];
        return ($s >= 1 && $s <= 9) ? $s : null;
    }

    /**
     * @param iterable<?string> $rsts
     * @return array<int|string,int> keys 1..9 plus 'unknown'
     */
    public static function distribution(iterable $rsts): array
    {
        $dist = ['unknown' => 0];
        for ($i = 1; $i <= 9; $i++) {
            $dist[$i] = 0;
        }
        foreach ($rsts as $rst) {
            $s = self::strength($rst);
            if ($s === null) {
                $dist['unknown']++;
            } else {
                $dist[$s]++;
            }
        }
        return $dist;
    }
}
