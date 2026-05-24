<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Password reset token entity.
 *
 * Represents one single-use reset link. The raw token is never stored —
 * only its SHA-256 hash (token_hash). token_hash is in $_hidden to prevent
 * accidental serialisation.
 */
class PasswordReset extends Entity
{
    protected array $_accessible = [
        'email' => true,
        'token_hash' => true,
        'expires_at' => true,
        'used_at' => true,
    ];

    protected array $_hidden = ['token_hash'];
}
