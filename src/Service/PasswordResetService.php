<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Password-reset token service.
 *
 * Issues URL-safe 43-character tokens and stores only their SHA-256 hash so
 * that a database leak cannot be replayed. `consume()` is single-shot:
 * the row's `used_at` is set on the first successful call, and any later
 * call throws `\RuntimeException`.
 *
 * The TTL is configurable via the constructor so tests can force expiry
 * with `ttlSeconds: -1`.
 */
final class PasswordResetService
{
    public function __construct(private int $ttlSeconds = 3600)
    {
    }

    /**
     * Issue a new reset token for the given email and persist its hash.
     *
     * @param string $email Email address the reset link was requested for.
     * @return string The plaintext token to embed in the reset URL.
     */
    public function issue(string $email): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $resets = TableRegistry::getTableLocator()->get('PasswordResets');
        $resets->saveOrFail($resets->newEntity([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => DateTime::now()->addSeconds($this->ttlSeconds),
        ]));

        return $token;
    }

    /**
     * Consume a reset token: returns the associated email and marks the
     * row used. Throws `\RuntimeException` if the token is unknown,
     * already used, or past its expiry.
     *
     * @param string $token Plaintext token from the reset URL.
     * @return string The email address the token was issued for.
     * @throws \RuntimeException If token is invalid, expired, or already consumed.
     */
    public function consume(string $token): string
    {
        $resets = TableRegistry::getTableLocator()->get('PasswordResets');
        $row = $resets->find()->where([
            'token_hash' => hash('sha256', $token),
            'used_at IS' => null,
        ])->first();
        if ($row === null || $row->expires_at->lessThan(DateTime::now())) {
            throw new \RuntimeException('Token is invalid or expired.');
        }
        $row->used_at = DateTime::now();
        $resets->saveOrFail($row);

        return (string)$row->email;
    }
}
