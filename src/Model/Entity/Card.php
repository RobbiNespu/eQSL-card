<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Card extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'guest_visit_id' => true,
        'qso_id' => true,
        'template_id' => true,
        'upload_id' => true,
        'qso_data_json' => true,
        'png_path' => true,
        'pdf_path' => true,
        'share_slug' => true,
        'share_password_hash' => true,
        'share_revoked_at' => true,
        'user' => true,
        'guest_visit' => true,
        'template' => true,
        'upload' => true,
    ];

    protected array $_hidden = ['share_password_hash'];
}
