/**
 * Vitest suite for the band-from-frequency client-side helper (M5 T26).
 *
 * Mirrors the PHP-side HamRadio::BAND_RANGES, so when the table
 * changes server-side this test file is the canary that catches
 * drift between the two.
 */
import { describe, it, expect } from 'vitest';
import bands from '../../webroot/js/bands.js';
const { bandForFrequencyMhz, EQSL_BAND_RANGES } = bands;

describe('bandForFrequencyMhz', () => {
    describe('known band centres', () => {
        const cases = [
            { mhz: 3.500,   band: '80m' },
            { mhz: 3.700,   band: '80m' },
            { mhz: 3.900,   band: '80m' },
            { mhz: 7.020,   band: '40m' },
            { mhz: 7.200,   band: '40m' },
            { mhz: 10.130,  band: '30m' },
            { mhz: 14.000,  band: '20m' },
            { mhz: 14.07415, band: '20m' },  // FT8 standard
            { mhz: 14.350,  band: '20m' },
            { mhz: 21.250,  band: '15m' },
            { mhz: 28.500,  band: '10m' },
            { mhz: 145.625, band: '2m' },
            { mhz: 433.500, band: '70cm' },
        ];
        cases.forEach(({ mhz, band }) => {
            it(`${mhz} MHz → ${band}`, () => {
                expect(bandForFrequencyMhz(mhz)).toBe(band);
            });
        });
    });

    describe('boundary inclusivity', () => {
        it('includes the lower bound (e.g. 14.000 → 20m)', () => {
            expect(bandForFrequencyMhz(14.000)).toBe('20m');
        });
        it('includes the upper bound (e.g. 14.350 → 20m)', () => {
            expect(bandForFrequencyMhz(14.350)).toBe('20m');
        });
    });

    describe('out-of-range frequencies', () => {
        it('returns null for sub-LF (0.5 MHz)', () => {
            expect(bandForFrequencyMhz(0.5)).toBeNull();
        });
        it('returns null for between-band gaps (8 MHz, between 40m and 30m)', () => {
            expect(bandForFrequencyMhz(8.000)).toBeNull();
        });
        it('returns null above 70cm (e.g. 500 MHz)', () => {
            expect(bandForFrequencyMhz(500)).toBeNull();
        });
        it('returns null for negative', () => {
            expect(bandForFrequencyMhz(-14)).toBeNull();
        });
        it('returns null for zero', () => {
            expect(bandForFrequencyMhz(0)).toBeNull();
        });
    });

    describe('input parsing', () => {
        it('accepts numeric strings', () => {
            expect(bandForFrequencyMhz('14.07415')).toBe('20m');
        });
        it('accepts numbers', () => {
            expect(bandForFrequencyMhz(14.07415)).toBe('20m');
        });
        it('returns null for empty string', () => {
            expect(bandForFrequencyMhz('')).toBeNull();
        });
        it('returns null for null', () => {
            expect(bandForFrequencyMhz(null)).toBeNull();
        });
        it('returns null for undefined', () => {
            expect(bandForFrequencyMhz(undefined)).toBeNull();
        });
        it('returns null for non-numeric strings', () => {
            expect(bandForFrequencyMhz('hello')).toBeNull();
        });
    });

    describe('table integrity', () => {
        it('exports a populated BAND_RANGES table', () => {
            expect(EQSL_BAND_RANGES.length).toBeGreaterThan(10);
        });
        it('every entry is [name, lo, hi] tuple with lo <= hi', () => {
            for (const [name, lo, hi] of EQSL_BAND_RANGES) {
                expect(typeof name).toBe('string');
                expect(lo).toBeLessThanOrEqual(hi);
            }
        });
    });
});
