<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\ServerRequestInterface;

final class GuestSession
{
    public const COOKIE = 'guest_session';

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
