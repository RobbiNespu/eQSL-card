<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;

class User extends Entity
{
    protected array $_accessible = [
        'name' => true,
        'email' => true,
        'role' => true,
        'callsign' => true,
        'qth' => true,
        'grid_square' => true,
        'bio' => true,
        'password' => true, // virtual; mapped to password_hash via mutator
        'password_hash' => true, // accessible for seeding/tests; hidden in _hidden
        'email_verified_at' => true,
        'last_login_at' => true,
        'cards' => true,
        'templates' => true,
        'uploads' => true,
    ];

    protected array $_hidden = ['password_hash', 'password'];

    protected function _setPassword(string $plain): ?string
    {
        if ($plain === '') {
            return null;
        }
        $this->set('password_hash', (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash($plain));
        return null;
    }
}
