/**
 * M5 T15 — Maidenhead grid square converter.
 *
 * Pure function; lives in its own file so it's testable under Vitest
 * (no DOM, no window). app.js wires it into `window.latLonToGridSquare`
 * for the activations form's GPS helper to consume.
 *
 * Algorithm (per the 1980 Maidenhead spec):
 *   Field   — 2 letters A-R. Earth divided into 18×18 fields:
 *             lon: 20° each (A=–180°), lat: 10° each (A=–90°)
 *   Square  — 2 digits 0-9. Subdivides each field into 10×10 squares:
 *             lon: 2° each, lat: 1° each
 *   Subsq.  — 2 letters a-x. Subdivides each square into 24×24
 *             sub-squares: lon: 5' each, lat: 2.5' each
 *
 * Returns null for out-of-range or non-numeric inputs (so callers can
 * branch on the falsy value without try/catch).
 */
/**
 * Convert a latitude/longitude pair to a Maidenhead grid square locator.
 *
 * Supports 4-character (field + square) and 6-character (+ sub-square)
 * precision. Returns null for out-of-range or non-finite coordinates so
 * callers can branch on the falsy value without a try/catch.
 *
 * @param {number} lat       - latitude in decimal degrees (−90 … 90)
 * @param {number} lon       - longitude in decimal degrees (−180 … 180)
 * @param {4|6}    precision - number of characters to return (default 6)
 * @returns {string|null} locator string (e.g. 'OJ11wg') or null on invalid input
 */
function latLonToGridSquare(lat, lon, precision) {
    if (precision === undefined) precision = 6;
    if (typeof lat !== 'number' || typeof lon !== 'number'
        || !isFinite(lat) || !isFinite(lon)
        || lat < -90 || lat > 90
        || lon < -180 || lon > 180) {
        return null;
    }
    var A = 65, a = 97;  // 'A' / 'a' char codes
    var lonAdj = lon + 180;
    var latAdj = lat + 90;
    var g = '';
    // Field (letters)
    g += String.fromCharCode(A + Math.floor(lonAdj / 20));
    g += String.fromCharCode(A + Math.floor(latAdj / 10));
    // Square (digits)
    g += String.fromCharCode(48 + Math.floor((lonAdj % 20) / 2));
    g += String.fromCharCode(48 + Math.floor(latAdj % 10));
    if (precision === 4) return g;
    // Sub-square (letters)
    g += String.fromCharCode(a + Math.floor(((lonAdj % 2) * 60) / 5));
    g += String.fromCharCode(a + Math.floor(((latAdj % 1) * 60) / 2.5));
    return g;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { latLonToGridSquare };
}
if (typeof window !== 'undefined') {
    window.latLonToGridSquare = latLonToGridSquare;
}
