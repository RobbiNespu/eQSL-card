<?php
declare(strict_types=1);

namespace App\Service;

final class ImageOptimizer
{
    public function __construct(
        private int $maxWidth = 2000,
        private int $maxHeight = 1500,
        private int $quality = 82,
    ) {}

    /**
     * @return array{width_px:int,height_px:int,file_size_bytes:int,mime_type:string,sha256_hash:string}
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
