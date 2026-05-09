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

        $out = $this->tmp . '/card.png';
        $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/');
        $info = $renderer->renderPng($template, $bg, $qso, $out);

        $this->assertFileExists($out);
        [$w, $h] = getimagesize($out);
        $this->assertSame(1500, $w);
        $this->assertSame(1000, $h);
        $this->assertSame('image/png', $info['mime_type']);
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
}
