<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Renders a 400px-wide PNG thumbnail from a Template entity using sample QSO
 * data and the bundled demo background. Output goes to webroot/files/templates/.
 */
final class TemplateThumbnailRenderer
{
    private const SAMPLE_QSO = [
        'callsign' => 'W1AW',
        'operator_callsign' => 'AA1AA',
        'qso_datetime_utc' => '2026-05-09 14:32:00',
        'frequency_mhz' => '14.205',
        'band' => '20m',
        'mode' => 'SSB',
        'rst_sent' => '59',
        'rst_received' => '59',
        'operator_name' => 'Hiram',
        'notes' => 'Sample',
    ];

    public function __construct(
        private CardRenderer $renderer,
        private string $thumbnailDir,
        private string $demoBackgroundPath,
        private int $thumbWidth = 400,
    ) {
    }

    /**
     * @return string Relative path of the thumbnail (e.g. "files/templates/123.png")
     */
    public function render(int $templateId, array $template): string
    {
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0o775, true);
        }

        // 1. Render full-resolution PNG to a tmp file
        $tmpFull = tempnam(sys_get_temp_dir(), 'thumb_full_');
        $this->renderer->renderPng($template, $this->demoBackgroundPath, self::SAMPLE_QSO, $tmpFull);

        // 2. Resize to thumbWidth × proportional height, save as PNG
        $info = getimagesize($tmpFull);
        if ($info === false) {
            @unlink($tmpFull);
            throw new \RuntimeException('Failed to read rendered thumbnail source.');
        }
        [$srcW, $srcH] = $info;
        $ratio = $this->thumbWidth / $srcW;
        $thumbH = (int)round($srcH * $ratio);

        $src = imagecreatefrompng($tmpFull);
        $thumb = imagecreatetruecolor($this->thumbWidth, $thumbH);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $this->thumbWidth, $thumbH, $srcW, $srcH);
        imagedestroy($src);

        $thumbPath = $this->thumbnailDir . $templateId . '.png';
        imagepng($thumb, $thumbPath, 6);
        imagedestroy($thumb);
        @unlink($tmpFull);

        // Convert absolute path to webroot-relative
        $relative = 'files/templates/' . $templateId . '.png';

        return $relative;
    }
}
