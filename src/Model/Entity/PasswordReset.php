<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

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
