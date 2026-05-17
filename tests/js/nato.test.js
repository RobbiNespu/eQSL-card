/**
 * Vitest suite for the NATO phonetic decoder (M5 T29).
 *
 * Covers:
 *  - Canonical NATO words → letters
 *  - Common military / maritime variants ("niner", "tree", "fife", "fower", "alfa")
 *  - Hyphenated and spaced "x-ray" / "x ray" both decode to X
 *  - Filler words ("over", "out", "the") are dropped
 *  - Bare alphanumeric tokens pass through uppercased
 *  - Punctuation and mixed case are normalised
 *  - Non-string / empty inputs return ''
 */
import { describe, it, expect } from 'vitest';
import nato from '../../webroot/js/nato.js';
const { decodeCallsign, splitTokens, NATO_TABLE } = nato;

describe('decodeCallsign', () => {
    describe('canonical NATO phonetic', () => {
        const cases = [
            // Classic Malaysian callsign
            { transcript: 'nine mike two romeo delta x-ray', expected: '9M2RDX' },
            // US callsign with hyphen variant
            { transcript: 'whiskey one alpha whiskey', expected: 'W1AW' },
            // UK callsign
            { transcript: 'golf zero romeo whiskey alpha', expected: 'G0RWA' },
            // Long-suffix Australian callsign
            { transcript: 'victor kilo two charlie delta echo', expected: 'VK2CDE' },
            // Single letter
            { transcript: 'mike', expected: 'M' },
            // Empty
            { transcript: '', expected: '' },
        ];
        cases.forEach(({ transcript, expected }) => {
            it(`"${transcript}" → ${expected || '(empty)'}`, () => {
                expect(decodeCallsign(transcript)).toBe(expected);
            });
        });
    });

    describe('military / maritime variants', () => {
        it('niner → 9', () => {
            expect(decodeCallsign('niner mike two romeo delta x-ray')).toBe('9M2RDX');
        });
        it('tree → 3 and fife → 5', () => {
            expect(decodeCallsign('kilo tree alpha bravo fife')).toBe('K3AB5');
        });
        it('fower → 4', () => {
            expect(decodeCallsign('whiskey fower alpha whiskey')).toBe('W4AW');
        });
        it('alfa (ITU spelling) → A', () => {
            expect(decodeCallsign('alfa bravo charlie')).toBe('ABC');
        });
        it('juliett (double-t spelling) → J', () => {
            expect(decodeCallsign('juliett one juliet')).toBe('J1J');
        });
        it('whisky (no e) → W', () => {
            expect(decodeCallsign('whisky one alpha')).toBe('W1A');
        });
    });

    describe('x-ray variants', () => {
        it('"x-ray" with hyphen', () => {
            expect(decodeCallsign('nine mike two romeo delta x-ray')).toBe('9M2RDX');
        });
        it('"x ray" with space', () => {
            expect(decodeCallsign('nine mike two romeo delta x ray')).toBe('9M2RDX');
        });
        it('"xray" already joined', () => {
            expect(decodeCallsign('nine mike two romeo delta xray')).toBe('9M2RDX');
        });
        it('"exray" common mishearing', () => {
            expect(decodeCallsign('nine mike two romeo delta exray')).toBe('9M2RDX');
        });
    });

    describe('filler words dropped', () => {
        it('"over" suffix is dropped', () => {
            expect(decodeCallsign('whiskey one alpha whiskey over')).toBe('W1AW');
        });
        it('"out" suffix is dropped', () => {
            expect(decodeCallsign('whiskey one alpha whiskey out')).toBe('W1AW');
        });
        it('"the" is in STOP_WORDS, dropped entirely', () => {
            expect(decodeCallsign('whiskey one alpha whiskey the')).toBe('W1AW');
        });
        it('"this is" prefix common in radio phraseology', () => {
            expect(decodeCallsign('this is whiskey one alpha whiskey over')).toBe('W1AW');
        });
        it('"calling cq" prefix', () => {
            expect(decodeCallsign('calling cq from victor kilo two charlie')).toBe('VK2C');
        });
        it('"for" and "to" deliberately NOT mapped to 4/2 and dropped via STOP_WORDS', () => {
            // Design choice — too common in everyday speech to safely
            // map. STOP_WORDS drops them rather than letting the
            // bare-alphanumeric pass-through uppercase them.
            expect(decodeCallsign('for')).toBe('');
            expect(decodeCallsign('to')).toBe('');
        });
    });

    describe('bare alphanumeric pass-through', () => {
        it('"9m2rdx" already-joined chunk', () => {
            expect(decodeCallsign('9m2rdx')).toBe('9M2RDX');
        });
        it('mixed: spelled prefix + glued suffix', () => {
            expect(decodeCallsign('nine mike 2rdx')).toBe('9M2RDX');
        });
        it('two-letter glued suffix', () => {
            // Recogniser may glue trailing letters; multi-char chunks
            // pass through cleanly because they can't collide with the
            // single-letter STOP_WORDS ("a", "is", etc.).
            expect(decodeCallsign('victor kilo two cd')).toBe('VK2CD');
        });
    });

    describe('normalisation', () => {
        it('uppercase input', () => {
            expect(decodeCallsign('WHISKEY ONE ALPHA WHISKEY')).toBe('W1AW');
        });
        it('mixed case input', () => {
            expect(decodeCallsign('Whiskey One Alpha Whiskey')).toBe('W1AW');
        });
        it('trailing punctuation', () => {
            expect(decodeCallsign('whiskey one alpha whiskey.')).toBe('W1AW');
        });
        it('extra whitespace', () => {
            expect(decodeCallsign('  whiskey   one  alpha  whiskey  ')).toBe('W1AW');
        });
    });

    describe('defensive input handling', () => {
        it('null returns empty string', () => {
            expect(decodeCallsign(null)).toBe('');
        });
        it('undefined returns empty string', () => {
            expect(decodeCallsign(undefined)).toBe('');
        });
        it('number returns empty string', () => {
            expect(decodeCallsign(42)).toBe('');
        });
        it('object returns empty string', () => {
            expect(decodeCallsign({ transcript: 'whiskey' })).toBe('');
        });
        it('whitespace-only string returns empty', () => {
            expect(decodeCallsign('   ')).toBe('');
        });
    });
});

describe('splitTokens', () => {
    it('normalises x-ray variants into a single "xray" token', () => {
        expect(splitTokens('nine mike two romeo delta x-ray')).toEqual([
            'nine', 'mike', 'two', 'romeo', 'delta', 'xray',
        ]);
        expect(splitTokens('nine x ray')).toEqual(['nine', 'xray']);
    });
    it('non-string input returns empty array', () => {
        expect(splitTokens(null)).toEqual([]);
        expect(splitTokens(123)).toEqual([]);
    });
});

describe('NATO_TABLE integrity', () => {
    it('covers every letter A–Z at least once', () => {
        const letters = new Set(Object.values(NATO_TABLE).filter(v => /^[A-Z]$/.test(v)));
        for (let c = 65; c <= 90; c++) {
            expect(letters.has(String.fromCharCode(c))).toBe(true);
        }
    });
    it('covers every digit 0–9 at least once', () => {
        const digits = new Set(Object.values(NATO_TABLE).filter(v => /^[0-9]$/.test(v)));
        for (let d = 0; d <= 9; d++) {
            expect(digits.has(String(d))).toBe(true);
        }
    });
});
