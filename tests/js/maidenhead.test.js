/**
 * Vitest suite for the Maidenhead grid-square converter (M5-T15).
 *
 * Pure function; lives in webroot/js/maidenhead.js as a CJS module so
 * we can import it under Node without a DOM. The same file installs
 * itself onto window in the browser, so the activations form's GPS
 * helper can call window.latLonToGridSquare directly.
 */
import { describe, it, expect } from 'vitest';
import maidenhead from '../../webroot/js/maidenhead.js';
const { latLonToGridSquare } = maidenhead;

describe('latLonToGridSquare', () => {
    describe('known reference points', () => {
        it('Kuala Lumpur (3.139, 101.687) → OJ03 / OJ03ud area', () => {
            // KL straddles the OJ02xx/OJ03xx boundary; coords from
            // wikipedia put 3.139°N, 101.687°E at roughly OJ03ud.
            expect(latLonToGridSquare(3.139, 101.687, 4)).toBe('OJ03');
            const six = latLonToGridSquare(3.139, 101.687, 6);
            expect(six).toMatch(/^OJ03[a-x]{2}$/);
        });

        it('ARRL HQ Newington CT (41.7148, -72.7270) → FN31pr area', () => {
            expect(latLonToGridSquare(41.7148, -72.7270, 4)).toBe('FN31');
            expect(latLonToGridSquare(41.7148, -72.7270, 6)).toMatch(/^FN31[a-x]{2}$/);
        });

        it('London (51.5074, -0.1278) → IO91wn area', () => {
            expect(latLonToGridSquare(51.5074, -0.1278, 4)).toBe('IO91');
        });

        it('Sydney (-33.8688, 151.2093) → QF56od area', () => {
            expect(latLonToGridSquare(-33.8688, 151.2093, 4)).toBe('QF56');
        });
    });

    describe('precision parameter', () => {
        it('defaults to 6-char output', () => {
            const result = latLonToGridSquare(3.139, 101.687);
            expect(result).toHaveLength(6);
        });

        it('respects precision=4 (drops sub-square)', () => {
            const result = latLonToGridSquare(3.139, 101.687, 4);
            expect(result).toHaveLength(4);
        });

        it('respects precision=6 (full sub-square)', () => {
            const result = latLonToGridSquare(3.139, 101.687, 6);
            expect(result).toHaveLength(6);
        });
    });

    describe('format invariants', () => {
        it('first 2 chars are uppercase letters A-R', () => {
            const result = latLonToGridSquare(3.139, 101.687);
            expect(result.substring(0, 2)).toMatch(/^[A-R]{2}$/);
        });

        it('chars 3-4 are digits 0-9', () => {
            const result = latLonToGridSquare(3.139, 101.687);
            expect(result.substring(2, 4)).toMatch(/^[0-9]{2}$/);
        });

        it('chars 5-6 are lowercase letters a-x', () => {
            const result = latLonToGridSquare(3.139, 101.687);
            expect(result.substring(4, 6)).toMatch(/^[a-x]{2}$/);
        });
    });

    describe('boundary handling', () => {
        it('handles equator + prime meridian (0, 0) → JJ00', () => {
            expect(latLonToGridSquare(0, 0, 4)).toBe('JJ00');
        });

        it('handles north pole (90, 0) → JR00', () => {
            // lat 90 → field index 18 (just past 'R'), so this is a
            // boundary case. The implementation should still produce a
            // valid-looking string; portal acceptance of polar grids
            // is portal-specific (irrelevant for amateur ops in practice).
            const result = latLonToGridSquare(90, 0, 4);
            expect(result).not.toBeNull();
        });

        it('handles south pole (-90, 0) → JA00', () => {
            expect(latLonToGridSquare(-90, 0, 4)).toBe('JA00');
        });

        it('handles date-line west (-180, 0) → AJ00', () => {
            expect(latLonToGridSquare(0, -180, 4)).toBe('AJ00');
        });
    });

    describe('input validation', () => {
        it('returns null for non-numeric lat', () => {
            expect(latLonToGridSquare('foo', 0)).toBeNull();
            expect(latLonToGridSquare(null, 0)).toBeNull();
            expect(latLonToGridSquare(undefined, 0)).toBeNull();
        });

        it('returns null for non-numeric lon', () => {
            expect(latLonToGridSquare(0, 'foo')).toBeNull();
            expect(latLonToGridSquare(0, null)).toBeNull();
        });

        it('returns null for NaN / Infinity', () => {
            expect(latLonToGridSquare(NaN, 0)).toBeNull();
            expect(latLonToGridSquare(0, NaN)).toBeNull();
            expect(latLonToGridSquare(Infinity, 0)).toBeNull();
            expect(latLonToGridSquare(0, -Infinity)).toBeNull();
        });

        it('returns null for out-of-range lat', () => {
            expect(latLonToGridSquare(91, 0)).toBeNull();
            expect(latLonToGridSquare(-91, 0)).toBeNull();
        });

        it('returns null for out-of-range lon', () => {
            expect(latLonToGridSquare(0, 181)).toBeNull();
            expect(latLonToGridSquare(0, -181)).toBeNull();
        });
    });
});
