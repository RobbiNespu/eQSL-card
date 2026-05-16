<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Upload extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'guest_visit_id' => true,
        'original_filename' => true,
        'storage_path' => true,
        'mime_type' => true,
        'width_px' => true,
        'height_px' => true,
        'file_size_bytes' => true,
        'sha256_hash' => true,
        'author_name' => true,
        'license' => true,
        'user' => true,
        'guest_visit' => true,
    ];
}
