<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Validates a template's layout_json shape.
 *
 * Expected shape: {"fields": [{ "placeholder": str, "x": int, "y": int,
 *                               "font": str, "size": int>0, "color": "#rgb|rrggbb",
 *                               "rotation": number }, ...]}
 */
final class TemplateLayoutValidator
{
    private const ALLOWED_FONTS = [
        'Inter-Regular.ttf', 'Inter-Bold.ttf',
        'RobotoSlab-Regular.ttf', 'JetBrainsMono-Regular.ttf',
        'Cinzel-Regular.ttf',
    ];

    /** @return string[] empty array on success, errors on failure */
    public function validate(string $jsonString, int $canvasWidth, int $canvasHeight): array
    {
        $errors = [];

        try {
            $data = json_decode($jsonString, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ['layout_json: not valid JSON (' . $e->getMessage() . ')'];
        }

        if (!is_array($data) || !isset($data['fields']) || !is_array($data['fields'])) {
            return ['layout_json: must contain a "fields" array'];
        }

        if (count($data['fields']) > 50) {
            $errors[] = 'layout_json: too many fields (max 50)';
        }

        foreach ($data['fields'] as $i => $f) {
            $prefix = "fields[{$i}]";

            foreach (['placeholder', 'x', 'y', 'font', 'size', 'color'] as $key) {
                if (!array_key_exists($key, $f)) {
                    $errors[] = "{$prefix}: missing '{$key}'";
                    continue 2;
                }
            }

            if (!is_string($f['placeholder']) || $f['placeholder'] === '') {
                $errors[] = "{$prefix}.placeholder: must be a non-empty string";
            }
            if (mb_strlen((string)$f['placeholder']) > 200) {
                $errors[] = "{$prefix}.placeholder: too long (max 200 chars)";
            }

            $x = (int)$f['x'];
            $y = (int)$f['y'];
            if ($x < 0 || $x > $canvasWidth) {
                $errors[] = "{$prefix}.x: must be 0..{$canvasWidth}";
            }
            if ($y < 0 || $y > $canvasHeight) {
                $errors[] = "{$prefix}.y: must be 0..{$canvasHeight}";
            }

            $size = (int)$f['size'];
            if ($size <= 0 || $size > 500) {
                $errors[] = "{$prefix}.size: must be 1..500";
            }

            if (!in_array($f['font'], self::ALLOWED_FONTS, true)) {
                $errors[] = "{$prefix}.font: must be one of " . implode(', ', self::ALLOWED_FONTS);
            }

            if (!is_string($f['color']) || !preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $f['color'])) {
                $errors[] = "{$prefix}.color: must be a hex color like #fff or #ffffff";
            }

            if (isset($f['rotation']) && (!is_numeric($f['rotation']) || $f['rotation'] < -360 || $f['rotation'] > 360)) {
                $errors[] = "{$prefix}.rotation: must be a number in -360..360";
            }

            // Optional outline + shadow properties (added later). Each is
            // validated only when present; absence means "no outline / no
            // shadow" and is fine. Same hex-color shape as `color`.
            if (isset($f['outline_color'])) {
                if (!is_string($f['outline_color']) || !preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $f['outline_color'])) {
                    $errors[] = "{$prefix}.outline_color: must be a hex color like #fff or #ffffff";
                }
            }
            if (isset($f['outline_width']) && (!is_numeric($f['outline_width']) || $f['outline_width'] < 0 || $f['outline_width'] > 20)) {
                $errors[] = "{$prefix}.outline_width: must be 0..20";
            }
            if (isset($f['shadow_color'])) {
                if (!is_string($f['shadow_color']) || !preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $f['shadow_color'])) {
                    $errors[] = "{$prefix}.shadow_color: must be a hex color like #fff or #ffffff";
                }
            }
            foreach (['shadow_offset_x', 'shadow_offset_y'] as $sk) {
                if (isset($f[$sk]) && (!is_numeric($f[$sk]) || $f[$sk] < -30 || $f[$sk] > 30)) {
                    $errors[] = "{$prefix}.{$sk}: must be -30..30";
                }
            }
        }

        return $errors;
    }
}
