<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Guest visit session entity.
 *
 * Tracks an unauthenticated visitor identified by a URL-safe Base64
 * session_token (stored in a cookie). ip_hash and user_agent_hash are
 * SHA-256 digests; no raw PII is held in this entity.
 */
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
