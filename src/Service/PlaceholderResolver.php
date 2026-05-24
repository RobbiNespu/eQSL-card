<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Resolves `{placeholder}` tokens in a template string against a data array.
 *
 * Syntax: `{key}` or `{key:format}`. When a format string is supplied and
 * the value is (or parses as) a DateTimeInterface, the format is passed to
 * `DateTime::format()`. Unknown keys resolve to an empty string; missing keys
 * do not throw. Safe for use in card field text and credit footer templates.
 */
final class PlaceholderResolver
{
    /**
     * Replace all `{key}` and `{key:format}` tokens in `$template` with values from `$data`.
     *
     * @param string               $template Template string containing `{placeholder}` tokens.
     * @param array<string, mixed> $data     Map of placeholder keys to scalar or DateTime values.
     * @return string Resolved string; unknown keys produce empty strings.
     */
    public function resolve(string $template, array $data): string
    {
        return preg_replace_callback(
            '/\{(?<key>[a-z_][a-z0-9_]*)(?::(?<fmt>[^}]+))?\}/i',
            function (array $m) use ($data): string {
                $key = $m['key'];
                if (!array_key_exists($key, $data)) {
                    return '';
                }
                $value = $data[$key];
                if (isset($m['fmt']) && $m['fmt'] !== '') {
                    if ($value instanceof \DateTimeInterface) {
                        return $value->format($m['fmt']);
                    }
                    if (is_string($value) && strtotime($value) !== false) {
                        return (new \DateTimeImmutable($value))->format($m['fmt']);
                    }
                }
                return (string)$value;
            },
            $template
        ) ?? '';
    }
}
