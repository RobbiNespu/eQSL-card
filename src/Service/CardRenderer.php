<?php
declare(strict_types=1);

namespace App\Service;

final class CardRenderer
{
    public function __construct(
        private string $fontDir,
        private ?PlaceholderResolver $resolver = null,
    ) {
        $this->resolver = $resolver ?? new PlaceholderResolver();
        $this->fontDir = rtrim($this->fontDir, '/') . '/';
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
