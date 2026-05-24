<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Normalizes uploaded images for background storage.
 *
 * Reads a JPEG, PNG, or WebP source, optionally downscales it so neither
 * dimension exceeds the configured limits, strips EXIF data by re-encoding,
 * and writes the result as a JPEG. Returns image metadata including a
 * SHA-256 hash for de-duplication.
 */
final class ImageOptimizer
{
    /**
     * @param int $maxWidth  Maximum output width in pixels (default 2000).
     * @param int $maxHeight Maximum output height in pixels (default 1500).
     * @param int $quality   JPEG output quality 0–100 (default 82).
     */
    public function __construct(
        private int $maxWidth = 2000,
        private int $maxHeight = 1500,
        private int $quality = 82,
    ) {}

    /**
     * Decode, optionally downscale, and re-encode an image file as JPEG.
     *
     * If the source image fits within `maxWidth × maxHeight` the dimensions
     * are preserved. Re-encoding strips EXIF and any embedded payload.
     *
     * @param string $sourcePath      Absolute path to the source image (JPEG / PNG / WebP).
     * @param string $destinationPath Absolute path for the output JPEG.
     * @return array{width_px:int,height_px:int,file_size_bytes:int,mime_type:string,sha256_hash:string}
     * @throws \RuntimeException If the file is not a recognised image or write fails.
     */
    public function optimize(string $sourcePath, string $destinationPath): array
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new \RuntimeException('File is not a recognised image.');
        }

        [$origW, $origH, $type] = $info;
        $img = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default        => throw new \RuntimeException('Unsupported image type: ' . image_type_to_mime_type($type)),
        };
        if ($img === false) {
            throw new \RuntimeException('Failed to decode image.');
        }

        $scale = min(1.0, $this->maxWidth / $origW, $this->maxHeight / $origH);
        $newW = (int)round($origW * $scale);
        $newH = (int)round($origH * $scale);

        if ($scale < 1.0) {
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($img);
            $img = $resized;
        }

        // Re-encode strips EXIF and any embedded payload.
        if (!imagejpeg($img, $destinationPath, $this->quality)) {
            imagedestroy($img);
            throw new \RuntimeException('Failed to write optimized image.');
        }
        imagedestroy($img);

        return [
            'width_px'        => $newW,
            'height_px'       => $newH,
            'file_size_bytes' => filesize($destinationPath),
            'mime_type'       => 'image/jpeg',
            'sha256_hash'     => hash_file('sha256', $destinationPath),
        ];
    }
}
