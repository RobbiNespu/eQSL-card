<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\CardRenderer;
use App\Service\TemplateThumbnailRenderer;
use Cake\TestSuite\TestCase;

final class TemplateThumbnailRendererTest extends TestCase
{
    private string $thumbDir;
    private string $demoBg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->thumbDir = sys_get_temp_dir() . '/eqsl-thumb-' . uniqid() . '/';
        mkdir($this->thumbDir, 0o775, true);

        $this->demoBg = sys_get_temp_dir() . '/demo-bg-' . uniqid() . '.jpg';
        $img = imagecreatetruecolor(1500, 1000);
        imagefill($img, 0, 0, imagecolorallocate($img, 235, 242, 250));
        imagejpeg($img, $this->demoBg);
        imagedestroy($img);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->thumbDir . '*') ?: []);
        @rmdir($this->thumbDir);
        @unlink($this->demoBg);
        parent::tearDown();
    }

    public function testProducesPngAtTargetWidth(): void
    {
        $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/');
        $thumb = new TemplateThumbnailRenderer($renderer, $this->thumbDir, $this->demoBg);

        $template = [
            'canvas_width' => 1500,
            'canvas_height' => 1000,
            'fields' => [
                ['placeholder' => '{callsign}', 'x' => 100, 'y' => 200,
                 'font' => 'Inter-Bold.ttf', 'size' => 96, 'color' => '#000000', 'rotation' => 0],
            ],
        ];

        $path = $thumb->render(42, $template);
        $this->assertSame('files/templates/42.png', $path);
        $absolute = $this->thumbDir . '42.png';
        $this->assertFileExists($absolute);

        [$w, $h] = getimagesize($absolute);
        $this->assertSame(400, $w);
        // 1500x1000 → 400xH where H = round(1000 * 400/1500) = 267
        $this->assertSame(267, $h);
    }

    public function testThrowsOnInvalidTemplate(): void
    {
        $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/');
        $thumb = new TemplateThumbnailRenderer($renderer, $this->thumbDir, $this->demoBg);

        $this->expectException(\RuntimeException::class);
        $thumb->render(99, ['canvas_width' => 1500, 'canvas_height' => 1000, 'fields' => [
            ['placeholder' => 'x', 'x' => 1, 'y' => 1, 'font' => 'NotAFont.ttf',
             'size' => 12, 'color' => '#000', 'rotation' => 0],
        ]]);
    }
}
