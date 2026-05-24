<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\OperationLog;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Email-verification token service (M4-T13/T14).
 *
 * Mirrors `PasswordResetService` but sets `kind = 'email_verify'` on the row
 * so the shared `password_resets` table can carry both flows. `consume()`
 * is single-shot (sets `used_at`), idempotent at the per-row level, and
 * additionally stamps `users.email_verified_at` so the rest of the app
 * can gate features on a verified email without a join through the token
 * table.
 *
 * The TTL is constructor-configurable so tests can force expiry via
 * `ttlSeconds: -1` (the same trick used in `PasswordResetServiceTest`).
 */
final class EmailVerificationService
{
    public function __construct(private int $ttlSeconds = 86400)
    {
    }

    /**
     * Issue a new verification token for the given email and persist its
     * SHA-256 hash. Returns the plaintext token to embed in the verify URL.
     *
     * @param string $email Email address the verification was requested for.
     * @return string Plaintext URL-safe base64 token (43 chars).
     */
    public function issue(string $email): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $resets = TableRegistry::getTableLocator()->get('PasswordResets');
        $entity = $resets->newEmptyEntity();
        $entity->set('email', $email, ['guard' => false]);
        $entity->set('kind', 'email_verify', ['guard' => false]);
        $entity->set('token_hash', hash('sha256', $token), ['guard' => false]);
        $entity->set('expires_at', DateTime::now()->addSeconds($this->ttlSeconds), ['guard' => false]);
        $resets->saveOrFail($entity);

        OperationLog::event('email.verify.issued');

        return $token;
    }

    /**
     * Consume a verification token: marks the row used, stamps
     * `users.email_verified_at`, and returns the email address.
     *
     * @param string $token Plaintext token from the verify URL.
     * @return string The email address the token was issued for.
     * @throws \RuntimeException If the token is unknown, already used, or expired.
     */
    public function consume(string $token): string
    {
        $resets = TableRegistry::getTableLocator()->get('PasswordResets');
        $row = $resets->find()->where([
            'kind' => 'email_verify',
            'token_hash' => hash('sha256', $token),
            'used_at IS' => null,
        ])->first();
        if ($row === null || $row->expires_at->lessThan(DateTime::now())) {
            throw new \RuntimeException('Verification token is invalid or expired.');
        }
        $row->set('used_at', DateTime::now(), ['guard' => false]);
        $resets->saveOrFail($row);

        $users = TableRegistry::getTableLocator()->get('Users');
        $user = $users->find()->where(['email' => $row->email])->firstOrFail();
        $user->set('email_verified_at', DateTime::now(), ['guard' => false]);
        $users->saveOrFail($user);

        OperationLog::event('email.verify.consumed');

        return (string)$row->email;
    }
}
