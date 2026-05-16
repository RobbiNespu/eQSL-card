<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\GuestSession;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;

final class GuestSessionTest extends TestCase
{
    protected array $fixtures = ['app.GuestVisits'];

    public function testCreatesGuestVisitWhenCookieMissing(): void
    {
        $req = ServerRequestFactory::fromGlobals(['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'curl']);
        $svc = new GuestSession();
        $visit = $svc->ensure($req);
        $this->assertSame(43, strlen($visit->session_token));
    }

    public function testReusesExistingVisit(): void
    {
        $req = ServerRequestFactory::fromGlobals(['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'curl']);
        $req = $req->withCookieParams(['guest_session' => 'TOK0000000000000000000000000000000000000000']);
        // pre-seed the row so reuse is verified
        $this->getTableLocator()->get('GuestVisits')->saveOrFail(
            $this->getTableLocator()->get('GuestVisits')->newEntity([
                'session_token' => 'TOK0000000000000000000000000000000000000000',
                'ip_hash' => hash('sha256', '203.0.113.5'),
                'user_agent_hash' => hash('sha256', 'curl'),
            ])
        );
        $svc = new GuestSession();
        $visit = $svc->ensure($req);
        $this->assertSame('TOK0000000000000000000000000000000000000000', $visit->session_token);
    }
}
