/**
 * Vitest suite for the pure designer helpers (M3-T16).
 *
 * These helpers live in webroot/js/designer-helpers.js so they can be
 * exercised under Node without a DOM/Fabric mock. Execution happens in
 * CI (M4-T19) — the dev Docker image is PHP-only.
 */
import { describe, it, expect } from 'vitest';
// designer-helpers.js stays CommonJS (loaded by <script> tags from PHP),
// so we default-import it and pull the named exports off the resulting
// module.exports object — the most reliable ESM-from-CJS interop shape.
import helpers from '../../webroot/js/designer-helpers.js';
const { fontFamilyFor, parseLayoutJson, serializeLayout } = helpers;

describe('fontFamilyFor', () => {
    it('maps known TTF filenames to css font-family', () => {
        expect(fontFamilyFor('Inter-Regular.ttf')).toBe('Inter, sans-serif');
        expect(fontFamilyFor('Inter-Bold.ttf')).toBe('Inter, sans-serif');
        expect(fontFamilyFor('RobotoSlab-Regular.ttf')).toBe('"Roboto Slab", serif');
        expect(fontFamilyFor('JetBrainsMono-Regular.ttf')).toBe('"JetBrains Mono", monospace');
        expect(fontFamilyFor('Cinzel-Regular.ttf')).toBe('Cinzel, serif');
    });

    it('falls back to sans-serif for unknown or empty input', () => {
        expect(fontFamilyFor('Comic.ttf')).toBe('sans-serif');
        expect(fontFamilyFor('')).toBe('sans-serif');
        expect(fontFamilyFor(undefined)).toBe('sans-serif');
    });
});

describe('parseLayoutJson', () => {
    it('parses valid JSON with fields array', () => {
        const r = parseLayoutJson('{"fields":[{"placeholder":"x","x":1,"y":2}]}');
        expect(r.fields.length).toBe(1);
        expect(r.fields[0].placeholder).toBe('x');
    });

    it('returns empty fields on malformed JSON (degraded mode)', () => {
        expect(parseLayoutJson('not json').fields).toEqual([]);
    });

    it('returns empty fields when fields key is missing', () => {
        expect(parseLayoutJson('{}').fields).toEqual([]);
    });

    it('returns empty fields when fields is not an array', () => {
        expect(parseLayoutJson('{"fields":"oops"}').fields).toEqual([]);
        expect(parseLayoutJson('{"fields":null}').fields).toEqual([]);
    });

    it('returns empty fields when input is empty/undefined', () => {
        expect(parseLayoutJson('').fields).toEqual([]);
        expect(parseLayoutJson(undefined).fields).toEqual([]);
    });
});

describe('serializeLayout', () => {
    it('round-trips with parseLayoutJson', () => {
        const fields = [{ placeholder: 'a', x: 1, y: 2 }];
        const json = serializeLayout(fields);
        expect(parseLayoutJson(json).fields).toEqual(fields);
    });

    it('handles empty / undefined fields', () => {
        expect(serializeLayout([])).toBe('{"fields":[]}');
        expect(serializeLayout(undefined)).toBe('{"fields":[]}');
    });
});
