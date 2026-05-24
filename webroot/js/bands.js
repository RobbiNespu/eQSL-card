/**
 * M5 T26 — Client-side band derivation from MHz frequency.
 *
 * Mirrors src/Service/HamRadio::BAND_RANGES so the dupe-check API
 * call from the quick-add form can supply the band parameter the
 * server uses to scope `same_band_today` / `same_band_this_activation`.
 * Without this, the badge could only ever show "first contact" vs
 * "worked before" — no per-band signal.
 *
 * Pure function; dual-published as CJS module (Vitest) + window
 * global (Alpine component in app.js).
 *
 * If the PHP-side BAND_RANGES table ever changes (e.g. an MCMC ham
 * allocation update), keep this file in sync OR replace the client
 * derivation with a server `freq=` param on the dupe-check API.
 */
var EQSL_BAND_RANGES = [
    ['80m',  3.500,    3.900],
    ['60m',  5.3515,   5.3665],
    ['40m',  7.000,    7.200],
    ['30m',  10.100,   10.150],
    ['20m',  14.000,   14.350],
    ['17m',  18.068,   18.168],
    ['15m',  21.000,   21.450],
    ['12m',  24.890,   24.990],
    ['10m',  28.000,   29.700],
    ['6m',   50.000,   54.000],
    ['2m',   144.000,  148.000],
    ['70cm', 430.000,  440.000],
];

/**
 * Derive the amateur radio band name from a frequency in MHz.
 *
 * Accepts a number or a numeric string. Returns null for values that
 * don't fall within any entry in EQSL_BAND_RANGES (out-of-band, 0, negative,
 * NaN, empty string, null, or undefined).
 *
 * @param {number|string|null|undefined} mhz - frequency in MHz
 * @returns {string|null} band name (e.g. '20m') or null if not matched
 */
function bandForFrequencyMhz(mhz) {
    if (mhz === null || mhz === undefined || mhz === '') return null;
    var f = (typeof mhz === 'number') ? mhz : parseFloat(String(mhz));
    if (!isFinite(f) || f <= 0) return null;
    for (var i = 0; i < EQSL_BAND_RANGES.length; i++) {
        var lo = EQSL_BAND_RANGES[i][1];
        var hi = EQSL_BAND_RANGES[i][2];
        if (f >= lo && f <= hi) return EQSL_BAND_RANGES[i][0];
    }
    return null;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { bandForFrequencyMhz, EQSL_BAND_RANGES };
}
if (typeof window !== 'undefined') {
    window.bandForFrequencyMhz = bandForFrequencyMhz;
}
