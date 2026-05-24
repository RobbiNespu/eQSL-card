<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Manages anonymous guest sessions for the public eQSL card viewer.
 *
 * A guest session is a lightweight, cookie-keyed row in `guest_visits`
 * that tracks a visitor's last-seen timestamp without requiring a user
 * account. The session token is a 32-byte URL-safe random string; the IP
 * and user-agent are stored only as SHA-256 hashes to avoid logging PII.
 */
final class GuestSession
{
    public const COOKIE = 'guest_session';

    /**
     * Retrieve an existing guest session from the request cookie, or create a new one.
     *
     * If a `guest_session` cookie is present and maps to a live row in `guest_visits`,
     * `last_seen_at` is updated and the row is returned. Otherwise a fresh row is
     * inserted with hashed IP + user-agent, and the new entity is returned. The
     * caller is responsible for setting the cookie on the response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $req Incoming HTTP request.
     * @return object The GuestVisit entity (existing or newly created).
     */
    public function ensure(ServerRequestInterface $req): object
    {
        $table = TableRegistry::getTableLocator()->get('GuestVisits');
        $cookie = $req->getCookieParams()[self::COOKIE] ?? '';
        if ($cookie !== '') {
            $existing = $table->find()->where(['session_token' => $cookie])->first();
            if ($existing) {
                $existing->last_seen_at = DateTime::now();
                $table->saveOrFail($existing);
                return $existing;
            }
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $row = $table->newEntity([
            'session_token' => $token,
            'ip_hash' => hash('sha256', (string)($req->getServerParams()['REMOTE_ADDR'] ?? '')),
            'user_agent_hash' => hash('sha256', $req->getHeaderLine('User-Agent')),
        ]);
        $table->saveOrFail($row);
        return $row;
    }
}
