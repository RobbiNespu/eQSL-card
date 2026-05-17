/**
 * M5 T29 — NATO phonetic → callsign decoder.
 *
 * Pure function. Takes a Web Speech API transcript like
 * "nine mike two romeo delta x-ray over" and returns "9M2RDX".
 *
 * Behaviour:
 *  - Case-insensitive token match against the NATO table + common
 *    military/maritime variants ("niner" → 9, "tree" → 3, "fife" → 5).
 *  - Hyphens (x-ray) and basic punctuation become token separators.
 *  - Unknown tokens are dropped (recogniser tail words like "over"
 *    or "out" don't pollute the output).
 *  - Bare alphanumeric tokens pass through uppercased — some
 *    operators just say "9M2RDX" without phonetic spelling, and
 *    the Web Speech API sometimes returns chunks like "9m2" as a
 *    single token rather than spelling them out.
 *  - STOP_WORDS short-circuits common English / radio filler words
 *    BEFORE the pass-through rule, so "this is whiskey one alpha
 *    whiskey over" decodes to "W1AW" rather than "THISISW1AWOVER".
 *    Without this guard the pass-through rule would happily uppercase
 *    every filler word the recogniser returns.
 *
 * Dual-published: CJS module export for Vitest + window global
 * for the Alpine `quickAddForm` component in app.js. Mirrors the
 * pattern used by bands.js.
 *
 * Deliberately-unmapped words:
 *   "for" — would map to 4 but appears constantly in everyday
 *           speech-to-text and would corrupt non-callsign phrases.
 *   "to"  — same problem (→ 2). Both are listed in STOP_WORDS so
 *           they're dropped entirely rather than passed through as
 *           bare alphanumeric.
 *   Operators who really say "for" meaning "four" will need to
 *   re-say it as "four" or "fower". Acceptable trade-off.
 */
var NATO_TABLE = {
    // Letters — standard NATO/ITU
    alpha:    'A', alfa: 'A',
    bravo:    'B',
    charlie:  'C',
    delta:    'D',
    echo:     'E',
    foxtrot:  'F',
    golf:     'G',
    hotel:    'H',
    india:    'I',
    juliet:   'J', juliett: 'J',
    kilo:     'K',
    lima:     'L',
    mike:     'M',
    november: 'N',
    oscar:    'O',
    papa:     'P',
    quebec:   'Q',
    romeo:    'R',
    sierra:   'S',
    tango:    'T',
    uniform:  'U',
    victor:   'V',
    whiskey:  'W', whisky: 'W',
    xray:     'X', exray: 'X', // "x-ray" is split into "x" + "ray" then rejoined; see splitTokens
    yankee:   'Y',
    zulu:     'Z',
    // Digits — words + military variants
    zero:  '0',
    one:   '1',
    two:   '2',
    three: '3', tree: '3',
    four:  '4', fower: '4',
    five:  '5', fife: '5',
    six:   '6',
    seven: '7',
    eight: '8',
    nine:  '9', niner: '9',
};

// Filler words to drop BEFORE the bare-alphanumeric pass-through rule
// would otherwise uppercase them into the callsign. Covers basic English
// articles/prepositions plus standard radio phraseology. CQ-suffixed
// callsigns are unaffected — they decode via the phonetic table, not
// from a bare "cq" token.
var STOP_WORDS = {
    a: true, an: true, and: true, the: true, this: true, that: true,
    is: true, am: true, are: true, was: true, be: true,
    to: true, from: true, for: true, of: true, on: true, in: true,
    at: true, with: true, by: true,
    my: true, your: true, his: true, her: true, their: true,
    // Radio phraseology
    over: true, out: true, roger: true, wilco: true, copy: true,
    break: true, breaking: true, calling: true, cq: true, qrz: true,
    please: true, affirmative: true, negative: true,
    monitor: true, monitoring: true, station: true,
};

function splitTokens(transcript) {
    if (typeof transcript !== 'string') return [];
    // Normalise hyphens, punctuation, and the common "x-ray"/"x ray"
    // recogniser output into "xray" so the table hits cleanly.
    var clean = transcript
        .toLowerCase()
        .replace(/[.,!?;:]/g, ' ')
        .replace(/\bx[\s-]?ray\b/g, 'xray')
        .replace(/-/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    if (!clean) return [];
    return clean.split(' ');
}

function decodeCallsign(transcript) {
    var tokens = splitTokens(transcript);
    var out = '';
    for (var i = 0; i < tokens.length; i++) {
        var tok = tokens[i];
        if (!tok) continue;
        // Phonetic table hit — the canonical path.
        if (Object.prototype.hasOwnProperty.call(NATO_TABLE, tok)) {
            out += NATO_TABLE[tok];
            continue;
        }
        // Filler-word short-circuit BEFORE pass-through, otherwise the
        // bare-alphanumeric rule below would uppercase "over" → "OVER"
        // into the callsign.
        if (Object.prototype.hasOwnProperty.call(STOP_WORDS, tok)) {
            continue;
        }
        // Bare alphanumeric chunk — operator said the callsign directly
        // ("9m2rdx") or the recogniser glued letters together. Pass
        // through uppercased.
        if (/^[a-z0-9]+$/.test(tok)) {
            out += tok.toUpperCase();
        }
        // Anything else (punctuation residue, unrecognised noise) is
        // silently dropped — better to under-decode than to inject garbage.
    }
    return out;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        decodeCallsign: decodeCallsign,
        splitTokens: splitTokens,
        NATO_TABLE: NATO_TABLE,
        STOP_WORDS: STOP_WORDS,
    };
}
if (typeof window !== 'undefined') {
    window.decodeNatoCallsign = decodeCallsign;
}
