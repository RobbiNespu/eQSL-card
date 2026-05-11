<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\CardRenderer;
use Cake\TestSuite\TestCase;

final class CardRendererTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/eqsl-render-test-' . uniqid();
        mkdir($this->tmp, 0o775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->tmp . '/*') ?: []);
        @rmdir($this->tmp);
        parent::tearDown();
    }

    public function testRendersPngWithFieldsAtCorrectPositions(): void
    {
        $bg = $this->tmp . '/bg.jpg';
        $img = imagecreatetruecolor(1500, 1000);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagejpeg($img, $bg);
        imagedestroy($img);

        $template = [
            'canvas_width' => 1500,
            'canvas_height' => 1000,
            'fields' => [
                ['placeholder' => '{callsign}', 'x' => 100, 'y' => 200,
                 'font' => 'Inter-Bold.ttf', 'size' => 96, 'color' => '#000000', 'rotation' => 0],
                ['placeholder' => 'Confirming QSO with {operator_name}', 'x' => 100, 'y' => 350,
                 'font' => 'Inter-Regular.ttf', 'size' => 36, 'color' => '#222222', 'rotation' => 0],
            ],
        ];
        $qso = ['callsign' => 'W1AW', 'operator_name' => 'Hiram Maxim'];

        $out = $this->tmp . '/card.webp';
        $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/');
        $info = $renderer->renderPng($template, $bg, $qso, $out);

        $this->assertFileExists($out);
        [$w, $h] = getimagesize($out);
        $this->assertSame(1500, $w);
        $this->assertSame(1000, $h);
        // Renderer emits WebP now (~40% smaller than the prior PNG).
        $this->assertSame('image/webp', $info['mime_type']);
    }

    /**
     * Render a tiny canvas with one field, then decode the WebP back to
     * a GD image so individual tests can probe pixels.
     *
     * @return array{0:\GdImage, 1:int, 2:int} [decodedImage, width, height]
     */
    private function renderAndOpen(array $fieldOverrides): array
    {
        $bg = $this->tmp . '/bg.jpg';
        // White background so any drawn pixel stands out.
        $img = imagecreatetruecolor(200, 80);
        imagefilledrectangle($img, 0, 0, 200, 80, imagecolorallocate($img, 255, 255, 255));
        imagejpeg($img, $bg, 95);
        imagedestroy($img);

        $field = array_merge([
            'placeholder' => 'X',
            'x' => 30, 'y' => 50,
            'font' => 'Inter-Bold.ttf',
            'size' => 32, 'color' => '#ff0000', 'rotation' => 0,
        ], $fieldOverrides);
        $template = ['canvas_width' => 200, 'canvas_height' => 80, 'fields' => [$field]];

        $out = $this->tmp . '/out.webp';
        $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/', creditFooterLines: []);
        $renderer->renderPng($template, $bg, [], $out);
        $loaded = imagecreatefromwebp($out);
        return [$loaded, imagesx($loaded), imagesy($loaded)];
    }

    private function isNearColor(\GdImage $img, int $x, int $y, int $r, int $g, int $b, int $tolerance = 20): bool
    {
        $rgb = imagecolorat($img, $x, $y);
        return abs((($rgb >> 16) & 0xFF) - $r) <= $tolerance
            && abs((($rgb >> 8) & 0xFF) - $g) <= $tolerance
            && abs(($rgb & 0xFF) - $b) <= $tolerance;
    }

    public function testNoShadowOrOutlineByDefault(): void
    {
        // Field with no outline / no shadow keys present. Region above
        // the baseline should be pure white (no shadow), region below
        // the text glyph should also be white once we step past the
        // text rectangle.
        [$img] = $this->renderAndOpen([]);
        // Top-left corner: definitely outside any drawn text region.
        $this->assertTrue(
            $this->isNearColor($img, 0, 0, 255, 255, 255),
            'corner should remain white when no shadow/outline configured'
        );
        imagedestroy($img);
    }

    public function testShadowDrawsAtOffset(): void
    {
        // A 4px-down shadow in BLUE on a white canvas. Pixels 4px below
        // the text body should contain blue-ish content from the shadow
        // stamp.
        [$img] = $this->renderAndOpen([
            'placeholder' => 'M',     // dense glyph
            'x' => 30, 'y' => 50,
            'size' => 40,
            'color' => '#ffffff',     // white main text — invisible on white bg
            'shadow_color' => '#0000ff',
            'shadow_offset_x' => 0,
            'shadow_offset_y' => 8,
        ]);
        // Scan a vertical column inside the glyph; expect at least one
        // pixel to be blue-ish from the shadow stamp.
        $foundBlue = false;
        for ($y = 30; $y < 70; $y++) {
            if ($this->isNearColor($img, 45, $y, 0, 0, 255, 80)) {
                $foundBlue = true;
                break;
            }
        }
        $this->assertTrue($foundBlue, 'shadow should leave blue-ish pixels in the glyph column');
        imagedestroy($img);
    }

    public function testOutlineDrawsStampedAtOffsets(): void
    {
        // GREEN outline on white text on white bg — the only visible
        // pixels should be from the outline stamp.
        [$img] = $this->renderAndOpen([
            'placeholder' => 'M',
            'x' => 30, 'y' => 50, 'size' => 40,
            'color' => '#ffffff',
            'outline_color' => '#00ff00',
            'outline_width' => 2,
        ]);
        $foundGreen = false;
        for ($y = 25; $y < 70; $y++) {
            for ($x = 30; $x < 80; $x++) {
                if ($this->isNearColor($img, $x, $y, 0, 255, 0, 80)) {
                    $foundGreen = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($foundGreen, 'outline stamps should leave green pixels around the glyph');
        imagedestroy($img);
    }

    public function testRejectsUnknownFont(): void
    {
        $bg = $this->tmp . '/bg.jpg';
        $img = imagecreatetruecolor(100, 100);
        imagejpeg($img, $bg);
        imagedestroy($img);

        $template = [
            'canvas_width' => 100, 'canvas_height' => 100,
            'fields' => [['placeholder' => 'x', 'x' => 10, 'y' => 50,
                          'font' => 'NotAFont.ttf', 'size' => 12, 'color' => '#000', 'rotation' => 0]],
        ];

        $this->expectException(\RuntimeException::class);
        (new CardRenderer(WWW_ROOT . 'files/fonts/'))
            ->renderPng($template, $bg, [], $this->tmp . '/card.png');
    }

    public function testCreditFooterIsDrawnByDefault(): void
    {
        $bg = $this->tmp . '/bg.jpg';
        $img = imagecreatetruecolor(1500, 1000);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagejpeg($img, $bg);
        imagedestroy($img);

        $template = ['canvas_width' => 1500, 'canvas_height' => 1000, 'fields' => []];

        $withFooter = $this->tmp . '/with-footer.png';
        (new CardRenderer(WWW_ROOT . 'files/fonts/'))
            ->renderPng($template, $bg, [], $withFooter);

        $withoutFooter = $this->tmp . '/no-footer.png';
        (new CardRenderer(WWW_ROOT . 'files/fonts/', creditFooterLines: []))
            ->renderPng($template, $bg, [], $withoutFooter);

        // Footer paints a translucent band + text, so file content must differ.
        $this->assertNotSame(
            hash_file('sha256', $withFooter),
            hash_file('sha256', $withoutFooter),
            'Default credit footer should produce a visually different PNG'
        );
    }

    public function testFromSettingsFactoryReturnsRenderer(): void
    {
        $r = CardRenderer::fromSettings(WWW_ROOT . 'files/fonts/');
        $this->assertInstanceOf(CardRenderer::class, $r);
    }

    public function testWrapsPngIntoPdf(): void
    {
        $bg = $this->tmp . '/bg.jpg';
        $img = imagecreatetruecolor(1500, 1000);
        imagejpeg($img, $bg);
        imagedestroy($img);

        $template = ['canvas_width' => 1500, 'canvas_height' => 1000, 'fields' => []];
        $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/');

        $png = $this->tmp . '/card.png';
        $pdf = $this->tmp . '/card.pdf';
        $renderer->renderPng($template, $bg, [], $png);
        $renderer->wrapPdf($png, $pdf, $template['canvas_width'], $template['canvas_height']);

        $this->assertFileExists($pdf);
        $bytes = file_get_contents($pdf);
        $this->assertStringStartsWith('%PDF-', (string)$bytes);
    }
}
