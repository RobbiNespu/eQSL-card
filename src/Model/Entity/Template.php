<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Card template entity.
 *
 * is_approved and is_system are locked from mass assignment; both are
 * mutated exclusively through admin service-layer methods to prevent
 * unprivileged promotion via a crafted POST.
 */
class Template extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'name' => true,
        'description' => true,
        'canvas_width' => true,
        'canvas_height' => true,
        'layout_json' => true,
        'thumbnail_path' => true,
        'background_upload_id' => true,
        'qso_type' => true,
        'is_public' => true,        // user can request public; admin reviews
        'is_approved' => false,     // admin-only via service layer
        'is_system' => false,       // installer-only via service layer
        'user' => true,
        'cards' => true,
    ];
}
