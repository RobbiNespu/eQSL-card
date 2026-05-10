<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Canonical list of image licenses for upload attribution.
 *
 * Stored as short string codes (e.g. `cc_by_4_0`) on `uploads.license`.
 * `unknown` and `null` both mean "license not specified" — the renderer
 * displays them as "unknown license" in the attribution footer.
 *
 * Validators stay permissive so legacy ADIF/CSV-imported uploads that
 * predate this table column don't break — UI shows the canonical list
 * but storage accepts anything ≤ 40 chars.
 */
final class ImageLicense
{
    /** @var array<string, string> machine code → human label */
    public const LICENSES = [
        'unknown' => 'Unknown / not specified',
        'own_work' => 'Own work (taken by uploader)',
        'public_domain' => 'Public domain',
        'cc0' => 'CC0 1.0 (Public Domain Dedication)',
        'cc_by_4_0' => 'CC BY 4.0',
        'cc_by_sa_4_0' => 'CC BY-SA 4.0',
        'cc_by_nc_4_0' => 'CC BY-NC 4.0',
        'cc_by_nd_4_0' => 'CC BY-ND 4.0',
        // Stock-photo platform licenses. Each platform issues its own
        // proprietary license — none are Creative Commons. Added here so
        // attribution is explicit instead of forcing 'permission_granted'.
        'pixabay_license' => 'Pixabay Content License',
        'unsplash_license' => 'Unsplash License',
        'pexels_license' => 'Pexels License',
        'permission_granted' => 'Used with permission',
        'fair_use' => 'Fair use / fair dealing',
        'all_rights_reserved' => 'All rights reserved (proceed with caution)',
    ];

    /**
     * Build options for a Form->control select with `'unknown'` selected by
     * default if no current value is supplied. Pre-prepends a non-canonical
     * `$current` value so editing a pre-existing row keeps its stored value
     * selected even if it was set before this enum existed.
     *
     * @return array<string, string>
     */
    public static function options(?string $current = null): array
    {
        $out = self::LICENSES;
        if ($current !== null && $current !== '' && !array_key_exists($current, $out)) {
            $out = [$current => $current . ' (legacy value)'] + $out;
        }
        return $out;
    }

    /** Human label for a stored license code. Falls back to the raw code. */
    public static function label(?string $code): string
    {
        if ($code === null || $code === '' || $code === 'unknown') {
            return 'unknown license';
        }
        return self::LICENSES[$code] ?? $code;
    }

    /**
     * Format an attribution footer line for a card render.
     *
     * Examples:
     *   formatLine('Hiram Maxim', 'cc_by_4_0', 'W1AW')
     *     → "Background: Hiram Maxim (CC BY 4.0) — used by W1AW"
     *   formatLine(null, null, 'W1AW')
     *     → "Background: unknown source (unknown license) — used by W1AW"
     */
    public static function formatLine(?string $author, ?string $license, string $operatorCallsign): string
    {
        $authorPart = ($author !== null && trim($author) !== '') ? trim($author) : 'unknown source';
        $licensePart = self::label($license);
        $operator = trim($operatorCallsign) !== '' ? trim($operatorCallsign) : 'an unknown operator';
        return "Background: {$authorPart} ({$licensePart}) — used by {$operator}";
    }
}
