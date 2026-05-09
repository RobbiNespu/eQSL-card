<?php
declare(strict_types=1);

namespace App\Service;

final class PlaceholderResolver
{
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
