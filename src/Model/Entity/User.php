<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;

/**
 * User entity.
 *
 * The `password` field is virtual: assigning it triggers _setPassword() which
 * hashes the plain text with Argon2id and stores the result in `password_hash`.
 * Both fields are in $_hidden so they are never serialised to JSON or array.
 */
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
        // M5 T27 — per-user safety toggle for quick-add. When true,
        // /qsos/quick disables Save while the dupe-check badge shows
        // the red duplicate-in-activation state.
        'block_dupes_in_activation' => true,
        // M5 T29 — opt-in feature flag for the NATO-phonetic mic
        // button on /qsos/quick. Default OFF because the Web Speech
        // API has uneven cross-browser support (Chromium-only) and
        // routes through Google's cloud on Android Chrome.
        'voice_input_callsign' => true,
    ];

    protected array $_hidden = ['password_hash', 'password'];

    /**
     * Hash a plain-text password with Argon2id and store it in password_hash.
     *
     * Returns null (the virtual `password` field is never persisted). Empty
     * strings are ignored so a profile-edit form that omits the password field
     * does not accidentally clear the stored hash.
     *
     * @param string $plain Plain-text password from the form.
     * @return string|null Always null (hash goes to password_hash instead).
     */
    protected function _setPassword(string $plain): ?string
    {
        if ($plain === '') {
            return null;
        }
        $this->set('password_hash', (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash($plain));
        return null;
    }
}
