<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class AuditLog extends Entity
{
    protected array $_accessible = [
        // All fields are server-set; nothing should be mass-assignable.
        'actor_user_id' => false,
        'actor_guest_visit_id' => false,
        'event' => false,
        'target_type' => false,
        'target_id' => false,
        'metadata_json' => false,
    ];
}
