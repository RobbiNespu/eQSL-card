/**
 * Pure helpers shared by the designer Alpine factory (M3-T16).
 *
 * These functions deliberately avoid touching the DOM, Fabric, or `window`
 * so they can be exercised under Node/Vitest without a browser harness.
 * The module exports both via CommonJS (Node test runner) and as a
 * `window.designerHelpers` global (loaded before designer.js in the
 * browser); see templates/Templates/edit.php for the script order.
 */

/**
 * Map a font filename to a CSS font-family string for the designer preview.
 *
 * The server-side renderer loads the actual TTF file; this client-side
 * mapping uses a CSS substitute so the operator can judge proportions without
 * shipping the full font files to the browser. Falls back to 'sans-serif'.
 *
 * @param {string} filename - TTF filename as stored in the layout JSON (e.g. 'Inter-Regular.ttf')
 * @returns {string} CSS font-family value
 */
function fontFamilyFor(filename) {
    // Designer-side preview only. Server-side renderer (M3-T7+) loads the
    // actual TTF; here we just pick a CSS family that looks roughly right
    // so the operator can judge proportions.
    const map = {
        'Inter-Regular.ttf': 'Inter, sans-serif',
        'Inter-Bold.ttf': 'Inter, sans-serif',
        'RobotoSlab-Regular.ttf': '"Roboto Slab", serif',
        'JetBrainsMono-Regular.ttf': '"JetBrains Mono", monospace',
        'Cinzel-Regular.ttf': 'Cinzel, serif',
    };
    return map[filename] || 'sans-serif';
}

/**
 * Parse the `layout_json` column value stored on a template row.
 * Returns { fields: [] } on a missing, null, or malformed value so that
 * callers never need to guard against JSON.parse exceptions.
 *
 * @param {string|null|undefined} jsonString - raw JSON string from the server
 * @returns {{ fields: object[] }} always an object with a `fields` array
 */
function parseLayoutJson(jsonString) {
    // A malformed legacy layout_json should not blow up the page; we degrade
    // to an empty fields array so the user can rebuild rather than seeing a
    // white-screen stack trace.
    try {
        const parsed = JSON.parse(jsonString || '{"fields":[]}');
        if (!parsed || !Array.isArray(parsed.fields)) {
            return { fields: [] };
        }
        return parsed;
    } catch (e) {
        return { fields: [] };
    }
}

/**
 * Serialise a fields array to the `layout_json` string stored in the database.
 *
 * @param {object[]} fields - the Alpine `fields` array from the designer
 * @returns {string} JSON string of the form {"fields":[…]}
 */
function serializeLayout(fields) {
    return JSON.stringify({ fields: fields || [] });
}

// Browser global — designer.js reads `window.designerHelpers` so this script
// MUST be loaded before designer.js (see templates/Templates/edit.php).
if (typeof window !== 'undefined') {
    window.designerHelpers = { fontFamilyFor, parseLayoutJson, serializeLayout };
}

// CommonJS export — picked up by Vitest under Node (M3-T16).
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { fontFamilyFor, parseLayoutJson, serializeLayout };
}
