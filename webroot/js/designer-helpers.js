/**
 * Pure helpers shared by the designer Alpine factory (M3-T16).
 *
 * These functions deliberately avoid touching the DOM, Fabric, or `window`
 * so they can be exercised under Node/Vitest without a browser harness.
 * The module exports both via CommonJS (Node test runner) and as a
 * `window.designerHelpers` global (loaded before designer.js in the
 * browser); see templates/Templates/edit.php for the script order.
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
