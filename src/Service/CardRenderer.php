<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Renders eQSL card images (WebP) from a template layout + background photo.
 *
 * Responsibilities:
 *   - Composites a background image onto a canvas at the template's configured
 *     dimensions, draws each text field (with optional shadow + outline), then
 *     stamps a credit footer band at the bottom.
 *   - Writes a 400 px thumbnail alongside the full-resolution card.
 *   - Wraps an existing card image into a single-page PDF via FPDF (WebP cards
 *     are transparently transcoded to JPEG for embedding).
 *
 * Obtain an instance via {@see self::fromSettings()} in production so the credit
 * footer text is pulled from `app_settings`; pass the constructor directly in
 * tests.
 */
final class CardRenderer
{
    /**
     * Default credit footer drawn on every card. Two physical lines.
     * `{year}` and `{generated_at}` are resolved at render time. Admin can
     * override the whole text via `/admin/settings` (key `eqsl_credit_template`,
     * read by the controller and passed in via $creditFooterLines).
     */
    public const DEFAULT_CREDIT_FOOTER = [
        "This electronic QSL card was generated automatically. For any discrepancies, please email to contact@robbi.my.",
        "© {year} ROBBI.MY | Developed by Robbi Nespu (9W2NSP) | Generated via https://tools.robbi.my/eQSL on {generated_at}",
    ];

    /**
     * @param string[] $creditFooterLines One string per physical line. Empty array disables the footer.
     */
    public function __construct(
        private string $fontDir,
        private ?PlaceholderResolver $resolver = null,
        private array $creditFooterLines = self::DEFAULT_CREDIT_FOOTER,
    ) {
        $this->resolver = $resolver ?? new PlaceholderResolver();
        $this->fontDir = rtrim($this->fontDir, '/') . '/';
    }

    /**
     * Build a CardRenderer with credit footer text pulled from app_settings.
     * Falls back to DEFAULT_CREDIT_FOOTER when the admin hasn't customised it.
     * Use this from controllers; tests should keep using `new CardRenderer(...)`.
     */
    public static function fromSettings(string $fontDir, ?AppSettings $settings = null): self
    {
        $settings ??= new AppSettings();
        $tpl = (string)$settings->get('eqsl_credit_template', '');
        $lines = $tpl !== ''
            ? array_values(array_filter(preg_split('/\r\n|\n/', $tpl) ?: [], static fn ($l) => trim($l) !== ''))
            : self::DEFAULT_CREDIT_FOOTER;
        return new self($fontDir, creditFooterLines: $lines);
    }

    /**
     * @param string[] $extraFooterLines Extra lines appended to the credit
     *        footer (e.g. background attribution). Drawn in the same band,
     *        below the configured credit lines, in the same mono font.
     * @return array{width_px:int,height_px:int,file_size_bytes:int,mime_type:string}
     */
    public function renderPng(array $template, string $backgroundPath, array $qso, string $destinationPath, array $extraFooterLines = []): array
    {
        $width = (int)$template['canvas_width'];
        $height = (int)$template['canvas_height'];

        $canvas = imagecreatetruecolor($width, $height);

        $bgInfo = @getimagesize($backgroundPath);
        if ($bgInfo === false) {
            throw new \RuntimeException('Background is not a valid image.');
        }
        $bg = match ($bgInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($backgroundPath),
            IMAGETYPE_PNG  => imagecreatefrompng($backgroundPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($backgroundPath),
            default        => throw new \RuntimeException('Unsupported background image type.'),
        };
        imagecopyresampled($canvas, $bg, 0, 0, 0, 0, $width, $height, imagesx($bg), imagesy($bg));
        imagedestroy($bg);

        foreach ($template['fields'] as $field) {
            $this->drawField($canvas, $field, $qso);
        }

        // System credit footer — drawn AFTER template fields so a template can't
        // accidentally hide it. `creditFooterLines` is the configured base; the
        // controller may pass additional `$extraFooterLines` per render (e.g.
        // background attribution). Empty merged array disables the footer.
        $allLines = array_merge($this->creditFooterLines, $extraFooterLines);
        if (!empty($allLines)) {
            $this->drawCreditFooter($canvas, $width, $height, $allLines);
        }

        // WebP at quality 82 is ~40% smaller than the prior PNG (compression
        // level 6) for the photo-with-text composition we render, with no
        // perceptual quality loss. Every browser shipped after 2020 displays
        // WebP natively. The column name `cards.png_path` is kept for
        // backwards compat with existing rows; semantically it's just the
        // rendered card image path.
        if (!imagewebp($canvas, $destinationPath, 82)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Failed to write rendered WebP.');
        }

        // Thumbnail derived from the same in-memory canvas. Written to
        // `<dest>.thumb.webp` so callers can predict the path from png_path.
        // 400px-wide thumbs run ~25 KB; loading 25 of them on /cards is
        // ~600 KB instead of ~27 MB of full-size cards. Failures here are
        // non-fatal — the listing falls back to the full card if the thumb
        // file is missing.
        $thumbPath = $this->thumbPathFor($destinationPath);
        $this->writeThumbnail($canvas, $thumbPath, 400, $width, $height);

        imagedestroy($canvas);

        return [
            'width_px'        => $width,
            'height_px'       => $height,
            'file_size_bytes' => filesize($destinationPath),
            'mime_type'       => 'image/webp',
        ];
    }

    /**
     * The convention: <basename>.thumb.<ext>. Keeps the .webp extension so
     * the same Content-Type negotiation works.
     */
    public static function thumbPathFor(string $cardPath): string
    {
        $info = pathinfo($cardPath);
        $base = ($info['dirname'] ?? '.') . DIRECTORY_SEPARATOR . $info['filename'];
        $ext = $info['extension'] ?? 'webp';
        return $base . '.thumb.' . $ext;
    }

    /**
     * Downscale `$src` to `$targetWidth` pixels wide (proportional height) and
     * save as WebP at quality 70. Silently skips on invalid source dimensions.
     *
     * @param \GdImage $src         Source GD image (the full-res canvas).
     * @param string   $dest        Absolute path for the thumbnail file.
     * @param int      $targetWidth Desired width in pixels.
     * @param int      $srcW        Source canvas width in pixels.
     * @param int      $srcH        Source canvas height in pixels.
     * @return void
     */
    private function writeThumbnail(\GdImage $src, string $dest, int $targetWidth, int $srcW, int $srcH): void
    {
        if ($srcW <= 0 || $srcH <= 0) {
            return;
        }
        $ratio = $targetWidth / $srcW;
        $thumbW = $targetWidth;
        $thumbH = (int)round($srcH * $ratio);

        $thumb = imagecreatetruecolor($thumbW, $thumbH);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbW, $thumbH, $srcW, $srcH);
        // Lower quality on the thumb — at 400px wide, q70 is visually
        // indistinguishable from q82 but ~15% smaller.
        @imagewebp($thumb, $dest, 70);
        imagedestroy($thumb);
    }

    /**
     * Render the card image into a PDF. `$imagePath` can be PNG, JPEG, or
     * WebP — WebP is transcoded to a temporary JPEG before embedding because
     * FPDF's `Image()` only speaks PNG / JPEG / GIF. The temp file is
     * unlinked on the way out. `$destinationPath` is the file to write; for
     * the lazy-download endpoint, controllers pass a `tempnam()` path and
     * stream the bytes back to the client.
     */
    public function wrapPdf(string $imagePath, string $destinationPath, int $widthPx, int $heightPx): void
    {
        // Convert pixels @ 300 DPI to mm: 1 inch = 25.4 mm; px / 300 * 25.4
        $widthMm  = $widthPx  / 300 * 25.4;
        $heightMm = $heightPx / 300 * 25.4;

        $tmpJpeg = null;
        $info = @getimagesize($imagePath);
        $type = $info[2] ?? 0;
        if ($type === IMAGETYPE_WEBP) {
            $img = imagecreatefromwebp($imagePath);
            $tmpJpeg = tempnam(sys_get_temp_dir(), 'eqsl_pdf_') . '.jpg';
            imagejpeg($img, $tmpJpeg, 88);
            imagedestroy($img);
            $embedPath = $tmpJpeg;
            $embedType = 'JPEG';
        } elseif ($type === IMAGETYPE_JPEG) {
            $embedPath = $imagePath;
            $embedType = 'JPEG';
        } else {
            // PNG or unknown — let FPDF figure it out; PNG is the conservative
            // default for pre-WebP rendered cards on disk.
            $embedPath = $imagePath;
            $embedType = 'PNG';
        }

        try {
            $pdf = new \FPDF($widthMm > $heightMm ? 'L' : 'P', 'mm', [$widthMm, $heightMm]);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false);
            $pdf->AddPage();
            $pdf->Image($embedPath, 0, 0, $widthMm, $heightMm, $embedType);
            $pdf->Output('F', $destinationPath);
        } finally {
            if ($tmpJpeg !== null) {
                @unlink($tmpJpeg);
            }
        }
    }

    /**
     * Render a single template field onto the canvas.
     *
     * Resolves the placeholder against the QSO data array, then paints (in order):
     * shadow → outline ring → main fill text using imagettftext. Skips fields
     * whose resolved text is empty.
     *
     * @param \GdImage             $canvas GD image resource to draw onto.
     * @param array<string, mixed> $field  Template field definition (placeholder, x, y, font,
     *                                     size, color, rotation, shadow_*, outline_*).
     * @param array<string, mixed> $qso    QSO data array for placeholder resolution.
     * @return void
     * @throws \RuntimeException When the font file is not bundled.
     */
    private function drawField(\GdImage $canvas, array $field, array $qso): void
    {
        $text = $this->resolver->resolve((string)$field['placeholder'], $qso);
        if ($text === '') {
            return;
        }

        $fontPath = $this->fontDir . basename((string)$field['font']);
        if (!is_file($fontPath)) {
            throw new \RuntimeException("Font not bundled: {$field['font']}");
        }

        $size = (float)$field['size'];
        $rotation = (float)($field['rotation'] ?? 0);
        $x = (int)$field['x'];
        $y = (int)$field['y'];

        // Shadow: a single stamp at the configured offset in shadow color,
        // drawn FIRST so the outline + main text land on top of it. Skip
        // entirely when offset is (0,0) — that's the marker for "no shadow"
        // rather than overlapping the main text with a tinted duplicate.
        $shadowOffsetX = (int)($field['shadow_offset_x'] ?? 0);
        $shadowOffsetY = (int)($field['shadow_offset_y'] ?? 0);
        if ($shadowOffsetX !== 0 || $shadowOffsetY !== 0) {
            [$sr, $sg, $sb] = $this->hexToRgb((string)($field['shadow_color'] ?? '#000000'));
            $shadowColor = imagecolorallocate($canvas, $sr, $sg, $sb);
            imagettftext(
                $canvas, $size, $rotation,
                $x + $shadowOffsetX, $y + $shadowOffsetY,
                $shadowColor, $fontPath, $text
            );
        }

        // Outline: stamp the text in 8 directions at the configured width.
        // GD has no native stroke, so we approximate by drawing the same
        // text repeatedly with offsets. Width=0 (default) skips the loop
        // and we render only the main fill — preserves the cheap path for
        // every existing template that doesn't use outlines.
        $outlineWidth = (int)($field['outline_width'] ?? 0);
        if ($outlineWidth > 0) {
            [$or, $og, $ob] = $this->hexToRgb((string)($field['outline_color'] ?? '#000000'));
            $outlineColor = imagecolorallocate($canvas, $or, $og, $ob);
            // Iterate the offset ring at every integer step from 1..$outlineWidth
            // so thick outlines stay solid (rather than just the 8 outer
            // edge stamps with a gap inside).
            for ($w = 1; $w <= $outlineWidth; $w++) {
                foreach ([[-$w, -$w], [0, -$w], [$w, -$w], [-$w, 0], [$w, 0], [-$w, $w], [0, $w], [$w, $w]] as [$dx, $dy]) {
                    imagettftext(
                        $canvas, $size, $rotation,
                        $x + $dx, $y + $dy,
                        $outlineColor, $fontPath, $text
                    );
                }
            }
        }

        // Main fill last so it sits on top of shadow + outline.
        [$r, $g, $b] = $this->hexToRgb((string)$field['color']);
        $color = imagecolorallocate($canvas, $r, $g, $b);
        imagettftext($canvas, $size, $rotation, $x, $y, $color, $fontPath, $text);
    }

    /**
     * Paint a thin translucent band at the bottom and write the credit lines
     * centered in JetBrainsMono — small, terminal-styled. Font size scales
     * with canvas height so 1500×1000 cards and 800×600 thumbnails both look right.
     *
     * @param \GdImage $canvas    GD image resource to draw onto.
     * @param int      $width     Canvas width in pixels.
     * @param int      $height    Canvas height in pixels.
     * @param string[] $rawLines  One template string per physical line. Resolved
     *                            against a context that provides `{year}` and
     *                            `{generated_at}` placeholders.
     * @return void
     */
    private function drawCreditFooter(\GdImage $canvas, int $width, int $height, array $rawLines): void
    {
        $fontPath = $this->fontDir . 'JetBrainsMono-Regular.ttf';
        if (!is_file($fontPath)) {
            // Fall back to Inter Regular if the mono font isn't bundled, then
            // skip entirely if neither is available.
            $fontPath = $this->fontDir . 'Inter-Regular.ttf';
            if (!is_file($fontPath)) {
                return;
            }
        }

        $context = [
            'year' => date('Y'),
            'generated_at' => date('Y-m-d\TH:i:s\Z'),
        ];
        $resolver = $this->resolver ?? new PlaceholderResolver();
        $lines = array_map(
            fn (string $tpl) => $resolver->resolve($tpl, $context),
            $rawLines
        );

        // Geek-mode sizing: ~1.1% of canvas height per line, tighter
        // line-height, 6px top/bottom padding.
        $fontSize = max(9.0, $height * 0.011);
        $lineHeight = (int)round($fontSize * 1.35);
        $vPad = 6;
        $bandHeight = ($lineHeight * count($lines)) + ($vPad * 2);
        $bandTop = $height - $bandHeight;

        // Slightly transparent band so the background still peeks through —
        // less "watermark", more "subtle stamp".
        $bandColor = imagecolorallocatealpha($canvas, 0, 0, 0, 80); // ~69% opaque
        imagefilledrectangle($canvas, 0, $bandTop, $width, $height, $bandColor);

        // Soft green-tinted off-white — leans into the terminal aesthetic
        // without being so on-the-nose as #00ff00.
        $textColor = imagecolorallocate($canvas, 220, 230, 220);

        foreach ($lines as $i => $line) {
            // Center horizontally using imagettfbbox to measure pixel width.
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $x = max(8, (int)round(($width - $textWidth) / 2));
            $y = $bandTop + $vPad + ($i * $lineHeight) + (int)round($fontSize);
            imagettftext($canvas, $fontSize, 0, $x, $y, $textColor, $fontPath, $line);
        }
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2)),
        ];
    }
}
