<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ImageOptimizer;
use Cake\TestSuite\TestCase;

final class ImageOptimizerTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/eqsl-img-test-' . uniqid();
        mkdir($this->tmp, 0o775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->tmp . '/*') ?: []);
        @rmdir($this->tmp);
        parent::tearDown();
    }

    public function testResizesLargeImageToBoundingBox(): void
    {
        $src = $this->tmp . '/big.jpg';
        $img = imagecreatetruecolor(3000, 2000);
        imagefill($img, 0, 0, imagecolorallocate($img, 100, 150, 200));
        imagejpeg($img, $src, 90);
        imagedestroy($img);

        $optimizer = new ImageOptimizer(maxWidth: 2000, maxHeight: 1500, quality: 82);
        $dst = $this->tmp . '/out.jpg';
        $info = $optimizer->optimize($src, $dst);

        [$w, $h] = getimagesize($dst);
        $this->assertLessThanOrEqual(2000, $w);
        $this->assertLessThanOrEqual(1500, $h);
        $this->assertSame('image/jpeg', $info['mime_type']);
        $this->assertGreaterThan(0, $info['file_size_bytes']);
    }

    public function testReturnsOriginalDimensionsWhenSmaller(): void
    {
        $src = $this->tmp . '/small.jpg';
        $img = imagecreatetruecolor(800, 600);
        imagejpeg($img, $src, 90);
        imagedestroy($img);

        $dst = $this->tmp . '/out.jpg';
        $info = (new ImageOptimizer(maxWidth: 2000, maxHeight: 1500))->optimize($src, $dst);
        $this->assertSame(800, $info['width_px']);
        $this->assertSame(600, $info['height_px']);
    }

    public function testRejectsNonImageContent(): void
    {
        $src = $this->tmp . '/bad.jpg';
        file_put_contents($src, "not an image");

        $this->expectException(\RuntimeException::class);
        (new ImageOptimizer())->optimize($src, $this->tmp . '/out.jpg');
    }
}
