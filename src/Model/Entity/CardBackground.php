<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Card background image — uploaded by an operator (user_id) or a
 * guest (guest_visit_id), used as the canvas under a rendered QSL
 * card. Renamed from "Upload" in migration 20260516000007 to make
 * the entity name match the actual purpose; FK column names on
 * sibling tables (cards.upload_id, templates.background_upload_id)
 * stay put for backward-compat with existing data.
 */
class CardBackground extends Entity
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
