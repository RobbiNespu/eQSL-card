<?php
declare(strict_types=1);

namespace App\Service;

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
     * @return array{width_px:int,height_px:int,file_size_bytes:int,mime_type:string}
     */
    public function renderPng(array $template, string $backgroundPath, array $qso, string $destinationPath): array
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
        // accidentally hide it. Empty array (`creditFooterLines`) disables it.
        if (!empty($this->creditFooterLines)) {
            $this->drawCreditFooter($canvas, $width, $height);
        }

        if (!imagepng($canvas, $destinationPath, 6)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Failed to write rendered PNG.');
        }
        imagedestroy($canvas);

        return [
            'width_px'        => $width,
            'height_px'       => $height,
            'file_size_bytes' => filesize($destinationPath),
            'mime_type'       => 'image/png',
        ];
    }

    public function wrapPdf(string $pngPath, string $destinationPath, int $widthPx, int $heightPx): void
    {
        // Convert pixels @ 300 DPI to mm: 1 inch = 25.4 mm; px / 300 * 25.4
        $widthMm  = $widthPx  / 300 * 25.4;
        $heightMm = $heightPx / 300 * 25.4;

        $pdf = new \FPDF($widthMm > $heightMm ? 'L' : 'P', 'mm', [$widthMm, $heightMm]);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->Image($pngPath, 0, 0, $widthMm, $heightMm, 'PNG');
        $pdf->Output('F', $destinationPath);
    }

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

        [$r, $g, $b] = $this->hexToRgb((string)$field['color']);
        $color = imagecolorallocate($canvas, $r, $g, $b);

        imagettftext(
            $canvas,
            (float)$field['size'],
            (float)($field['rotation'] ?? 0),
            (int)$field['x'],
            (int)$field['y'],
            $color,
            $fontPath,
            $text
        );
    }

    /**
     * Paint a translucent black band at the bottom and write the credit
     * lines in white over it. Font size scales with canvas height so a
     * 1500x1000 card and a 800x600 thumbnail both look right.
     */
    private function drawCreditFooter(\GdImage $canvas, int $width, int $height): void
    {
        $fontPath = $this->fontDir . 'Inter-Regular.ttf';
        if (!is_file($fontPath)) {
            return; // Font not bundled — skip footer rather than crash the render.
        }

        $context = [
            'year' => date('Y'),
            'generated_at' => date('Y-m-d\TH:i:s\Z'),
        ];
        $resolver = $this->resolver ?? new PlaceholderResolver();
        $lines = array_map(
            fn (string $tpl) => $resolver->resolve($tpl, $context),
            $this->creditFooterLines
        );

        // Sizing: ~1.6% of canvas height per line, 4px gap, 8px top/bottom padding.
        $fontSize = max(11.0, $height * 0.016);
        $lineHeight = (int)round($fontSize * 1.4);
        $vPad = 8;
        $bandHeight = ($lineHeight * count($lines)) + ($vPad * 2);
        $bandTop = $height - $bandHeight;

        // Translucent black band. GD's truecolor canvas supports alpha.
        $bandColor = imagecolorallocatealpha($canvas, 0, 0, 0, 70); // ~73% opaque
        imagefilledrectangle($canvas, 0, $bandTop, $width, $height, $bandColor);

        $textColor = imagecolorallocate($canvas, 240, 240, 240);

        foreach ($lines as $i => $line) {
            // Center horizontally using imagettfbbox to measure pixel width.
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $x = max(8, (int)round(($width - $textWidth) / 2));
            // Baseline = top of band + padding + (line index * lineHeight) + (line height as ascender).
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
