<?php
declare(strict_types=1);

namespace App\Service;

/**
 * M6 — decode a Maidenhead grid locator (4 or 6 char) to a lat/lon
 * centroid. Inverse of the client-side latLonToGridSquare in
 * webroot/js/maidenhead.js. Used for the net participant map.
 */
final class Maidenhead
{
    /**
     * Decode a 4- or 6-character Maidenhead grid locator to a lat/lon centroid.
     *
     * Returns the centre of the 4-char square (±1° lat, ±1° lon accuracy) or
     * the centre of the 6-char subsquare (±2.5′ lat, ±5′ lon accuracy).
     * Returns null for null input, invalid format, or grid strings outside
     * the spec's two-letter field + two-digit square [+ two-letter subsquare].
     *
     * @param string|null $grid Maidenhead grid locator (e.g. "OI11", "OI11wg").
     * @return array{lat: float, lon: float}|null Centroid coordinates, or null on invalid input.
     */
    public static function toLatLon(?string $grid): ?array
    {
        if ($grid === null) {
            return null;
        }
        $g = strtoupper(trim($grid));
        if (!preg_match('/^[A-R]{2}[0-9]{2}([A-X]{2})?$/', $g)) {
            return null;
        }
        $lon = (ord($g[0]) - ord('A')) * 20.0 - 180.0;
        $lat = (ord($g[1]) - ord('A')) * 10.0 - 90.0;
        $lon += ((int)$g[2]) * 2.0;
        $lat += ((int)$g[3]) * 1.0;
        if (strlen($g) === 6) {
            $lon += (ord($g[4]) - ord('A')) * (2.0 / 24.0);
            $lat += (ord($g[5]) - ord('A')) * (1.0 / 24.0);
            // centre of the subsquare
            $lon += (2.0 / 24.0) / 2.0;
            $lat += (1.0 / 24.0) / 2.0;
        } else {
            // centre of the square
            $lon += 1.0;
            $lat += 0.5;
        }
        return ['lat' => round($lat, 4), 'lon' => round($lon, 4)];
    }
}
