<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class GuestVisit extends Entity
{
    protected array $_accessible = [
        'session_token' => true,
        'ip_hash' => true,
        'user_agent_hash' => true,
        'last_seen_at' => true,
        'cards' => true,
        'uploads' => true,
    ];
}
