<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\CardRenderer;
use Cake\TestSuite\TestCase;

final class CardRendererParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/eqsl-parity-' . uniqid() . '/';
        mkdir($this->tmpDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->tmpDir . '*') ?: []);
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function testM1SeedRoundTripsThroughJsonAndProducesIdenticalPng(): void
    {
        // Load the seed
        $seedPath = CONFIG . 'seeds/default_system_template.json';
        $this->assertFileExists($seedPath);
        $original = json_decode((string)file_get_contents($seedPath), true, flags: JSON_THROW_ON_ERROR);

        // Build a deterministic background (solid color, fixed size)
        $bgPath = $this->tmpDir . 'bg.jpg';
        $bg = imagecreatetruecolor($original['canvas_width'], $original['canvas_height']);
        imagefill($bg, 0, 0, imagecolorallocate($bg, 235, 242, 250));
        imagejpeg($bg, $bgPath, 88);
        imagedestroy($bg);

        $qsoData = [
            'callsign' => 'W1AW', 'operator_callsign' => 'AA1AA',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
            'frequency_mhz' => '14.205', 'band' => '20m', 'mode' => 'SSB',
            'rst_sent' => '59', 'rst_received' => '59',
            'operator_name' => 'Hiram', 'notes' => 'Sample',
        ];

        // Footer is disabled for parity tests because its `{generated_at}`
        // placeholder bakes the current second into the PNG. Two back-to-back
        // renders that straddle a second boundary would hash differently —
        // not what this test is asserting.
        $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/', creditFooterLines: []);

        // Render A
        $pngA = $this->tmpDir . 'a.png';
        $renderer->renderPng([
            'canvas_width' => $original['canvas_width'],
            'canvas_height' => $original['canvas_height'],
            'fields' => $original['fields'],
        ], $bgPath, $qsoData, $pngA);

        // Round-trip the JSON (simulating DB store + retrieval)
        $serialized = json_encode(['fields' => $original['fields']], JSON_UNESCAPED_SLASHES);
        $deserialized = json_decode($serialized, true, flags: JSON_THROW_ON_ERROR);

        // Render B
        $pngB = $this->tmpDir . 'b.png';
        $renderer->renderPng([
            'canvas_width' => $original['canvas_width'],
            'canvas_height' => $original['canvas_height'],
            'fields' => $deserialized['fields'],
        ], $bgPath, $qsoData, $pngB);

        $hashA = hash_file('sha256', $pngA);
        $hashB = hash_file('sha256', $pngB);
        $this->assertSame($hashA, $hashB, 'Designer JSON round-trip must produce byte-identical PNG');
    }

    public function testDesignerStyleJsonProducesValidPng(): void
    {
        // Mimic exactly what the designer's save() POSTs:
        // {"fields":[{"placeholder":"...","x":1,"y":2,"font":"...","size":36,"color":"#000","rotation":0}, ...]}
        $designerJson = json_encode(['fields' => [
            ['placeholder' => '{operator_callsign}', 'x' => 80, 'y' => 130,
             'font' => 'Cinzel-Regular.ttf', 'size' => 90, 'color' => '#0b1d3a', 'rotation' => 0],
            ['placeholder' => 'to {callsign}', 'x' => 80, 'y' => 210,
             'font' => 'Inter-Regular.ttf', 'size' => 48, 'color' => '#0b1d3a', 'rotation' => 0],
        ]], JSON_UNESCAPED_SLASHES);

        $bgPath = $this->tmpDir . 'bg.jpg';
        $bg = imagecreatetruecolor(1500, 1000);
        imagejpeg($bg, $bgPath);
        imagedestroy($bg);

        // Footer is disabled for parity tests because its `{generated_at}`
        // placeholder bakes the current second into the PNG. Two back-to-back
        // renders that straddle a second boundary would hash differently —
        // not what this test is asserting.
        $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/', creditFooterLines: []);
        $pngPath = $this->tmpDir . 'designer.webp';
        $info = $renderer->renderPng([
            'canvas_width' => 1500,
            'canvas_height' => 1000,
            'fields' => json_decode($designerJson, true)['fields'],
        ], $bgPath, [
            'callsign' => 'W1AW', 'operator_callsign' => 'AA1AA',
        ], $pngPath);

        $this->assertFileExists($pngPath);
        $this->assertSame(1500, $info['width_px']);
        $this->assertSame('image/webp', $info['mime_type']);
    }
}
