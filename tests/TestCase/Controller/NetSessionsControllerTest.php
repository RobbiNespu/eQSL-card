<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * NetSessionsController — M6 T9 integration tests.
 *
 * Covers:
 *  - GET /net-sessions redirects anon to /login
 *  - GET /net-sessions lists owned sessions
 *  - POST /net-sessions/new creates a scheduled session (server stamps owner_id / status / slug)
 *  - POST /net-sessions/{id}/start transitions status to live, stamps started_at
 *  - Stranger cannot start another user's session (404)
 */
final class NetSessionsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.NetSessions', 'app.NetSessionLoggers', 'app.Qsos'];

    private function login(string $email = 'ncs@x.com'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'NCS', 'email' => $email, 'role' => 'user', 'callsign' => '9W2NSP',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id, 'email' => $email]]);
        return (int)$u->id;
    }

    private function seedNetSession(int $ownerId, array $extras = []): int
    {
        $tbl = $this->getTableLocator()->get('NetSessions');
        $row = $tbl->saveOrFail($tbl->newEntity(array_merge([
            'net_title' => 'Test Net', 'owner_id' => $ownerId, 'status' => 'scheduled',
            'public_slug' => 'slug-' . uniqid(),
        ], $extras), ['accessibleFields' => ['*' => true]]));
        return (int)$row->id;
    }

    public function testIndexRequiresAuth(): void
    {
        $this->get('/net-sessions');
        $this->assertRedirectContains('/login');
    }

    public function testIndexListsForOwner(): void
    {
        $uid = $this->login();
        $this->seedNetSession($uid, [
            'status'     => 'live',
            'net_title'  => 'MARTS Daily Net',
            'started_at' => '2026-05-22 12:00:00',
        ]);

        $this->get('/net-sessions');
        $this->assertResponseOk();
        $this->assertResponseContains('MARTS Daily Net');
    }

    public function testCreateSchedulesSession(): void
    {
        $uid = $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/net-sessions/new', [
            'net_title'        => 'My Test Net',
            'net_organisation' => 'TEST',
            'frequency_mhz'    => '14.225',
            'band'             => '20m',
            'mode'             => 'SSB',
            'is_public'        => '1',
            'notes'            => 'Integration test net',
            // Attempt to spoof — server must override.
            'owner_id'    => 9999,
            'status'      => 'live',
            'public_slug' => 'hacked-slug',
        ]);
        $this->assertResponseSuccess();

        $tbl = $this->getTableLocator()->get('NetSessions');
        $row = $tbl->find()->where(['net_title' => 'My Test Net'])->firstOrFail();
        $this->assertSame('scheduled', $row->status, 'status must be server-forced to scheduled');
        $this->assertNotEmpty($row->public_slug, 'public_slug must be generated server-side');
        $this->assertNotSame('hacked-slug', $row->public_slug, 'spoofed slug must be ignored');
        $this->assertSame($uid, (int)$row->owner_id, 'owner_id must come from session');
    }

    public function testStartTransitionsToLive(): void
    {
        $uid = $this->login();
        $id  = $this->seedNetSession($uid, ['status' => 'scheduled']);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/net-sessions/' . $id . '/start');

        // Do NOT follow the redirect — assert DB state.
        $this->assertResponseSuccess();
        $this->assertRedirect();

        $row = $this->getTableLocator()->get('NetSessions')->get($id);
        $this->assertSame('live', $row->status, 'status must transition to live');
        $this->assertNotNull($row->started_at, 'started_at must be stamped');
    }

    public function testStrangerCannotStart(): void
    {
        // Login as user A.
        $this->login('a@x.com');

        // Create user B and seed a session owned by B.
        $users = $this->getTableLocator()->get('Users');
        $userB = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user',
            'callsign' => 'BB1BB',
            'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsSessionId = $this->seedNetSession((int)$userB->id);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/net-sessions/' . $bsSessionId . '/start');
        $this->assertResponseCode(404);

        // B's session must still be scheduled.
        $row = $this->getTableLocator()->get('NetSessions')->get($bsSessionId);
        $this->assertSame('scheduled', $row->status);
    }

    // -------------------------------------------------------------------------
    // M6 T10 — check-in write/edit/delete JSON actions
    // -------------------------------------------------------------------------

    private function createUser(string $email, string $callsign = 'AA1AA'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'U', 'email' => $email, 'role' => 'user', 'callsign' => $callsign,
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        return (int)$u->id;
    }

    private function addCoLogger(int $sessionId, int $userId): void
    {
        $t = $this->getTableLocator()->get('NetSessionLoggers');
        $t->saveOrFail($t->newEntity(['net_session_id' => $sessionId, 'user_id' => $userId, 'added_via' => 'owner'], ['accessibleFields' => ['*' => true]]));
    }

    public function testCoLoggerCanLogCheckin(): void
    {
        // Create owner and live session.
        $ownerId = $this->login('owner@x.com');
        $sessionId = $this->seedNetSession($ownerId, ['status' => 'live']);

        // Create co-logger and add to session.
        $coId = $this->createUser('cologger@x.com', '9W2COL');
        $this->addCoLogger($sessionId, $coId);

        // Switch auth session to co-logger.
        $this->session(['Auth' => ['id' => $coId, 'email' => 'cologger@x.com']]);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/net-sessions/' . $sessionId . '/checkins', [
            'call_worked'   => '9W2ABC',
            'operator_name' => 'Test Station',
            'grid_square'   => 'OJ02',
            'rst_received'  => '59',
            'net_role'      => 'Check-in',
        ]);

        $this->assertResponseOk();

        // Verify DB record.
        $qso = $this->getTableLocator()->get('Qsos')->find()
            ->where(['call_worked' => '9W2ABC', 'net_session_id' => $sessionId])
            ->firstOrFail();

        $this->assertSame('net', $qso->qso_type, 'qso_type must be net');
        $this->assertSame($ownerId, (int)$qso->user_id, 'user_id must be the session owner');
        $this->assertSame($coId, (int)$qso->logged_by_user_id, 'logged_by_user_id must be the co-logger');
    }

    public function testStrangerCannotLogCheckin(): void
    {
        // Create owner and live session.
        $ownerId = $this->createUser('owner2@x.com', '9W2OWN');
        $sessionId = $this->seedNetSession($ownerId, ['status' => 'live']);

        // Login as a stranger with no membership.
        $this->login('stranger@x.com');

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/net-sessions/' . $sessionId . '/checkins', [
            'call_worked'  => '9W2ZZZ',
            'rst_received' => '59',
            'net_role'     => 'Check-in',
        ]);

        $this->assertResponseCode(404);
    }

    // -------------------------------------------------------------------------
    // M6 T11 — cockpit shell renders for the session owner
    // -------------------------------------------------------------------------

    public function testCockpitRendersForLogger(): void
    {
        $uid = $this->login();
        $id  = $this->seedNetSession($uid, [
            'status'     => 'live',
            'net_title'  => 'T11 Cockpit Net',
            'started_at' => '2026-05-22 12:00:00',
        ]);

        $this->get('/net-sessions/' . $id . '/cockpit');

        $this->assertResponseOk();
        $this->assertResponseContains('T11 Cockpit Net');
    }

    // -------------------------------------------------------------------------
    // M6 T13 — delta feed (?since cursor) + live stats
    // -------------------------------------------------------------------------

    private function seedCheckinRow(int $sessionId, int $ownerId, string $call): int
    {
        $q = $this->getTableLocator()->get('Qsos');
        $row = $q->saveOrFail($q->newEntity([
            'user_id'           => $ownerId,
            'call_worked'       => $call,
            'qso_type'          => 'net',
            'net_session_id'    => $sessionId,
            'ncs_callsign'      => '9W2NSP',
            'net_title'         => 'Test Net',
            'rst_received'      => '59',
            'grid_square'       => 'OJ02',
            'qso_datetime_utc'  => '2026-05-22 12:00:00',
        ], ['accessibleFields' => ['*' => true]]));
        return (int)$row->id;
    }

    public function testDeltaFeedReturnsShapeAndRows(): void
    {
        $uid = $this->login();
        $sessionId = $this->seedNetSession($uid, ['status' => 'live']);
        $this->seedCheckinRow($sessionId, $uid, '9M2RDX');

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/net-sessions/' . $sessionId . '/checkins?since=2000-01-01T00:00:00+00:00');

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);

        $this->assertArrayHasKey('server_time', $body);
        $this->assertArrayHasKey('status', $body);
        $this->assertArrayHasKey('stats', $body);
        $this->assertArrayHasKey('checkins', $body);
        $this->assertArrayHasKey('removed', $body);

        $this->assertCount(1, $body['checkins'], 'should have exactly 1 check-in row');
        $this->assertSame('9M2RDX', $body['checkins'][0]['callsign']);
        $this->assertSame(1, $body['stats']['checkins']);
    }

    public function testDeltaFeedSinceFutureReturnsEmpty(): void
    {
        $uid = $this->login();
        $sessionId = $this->seedNetSession($uid, ['status' => 'live']);
        $this->seedCheckinRow($sessionId, $uid, '9M2RDX');

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/net-sessions/' . $sessionId . '/checkins?since=2099-01-01T00:00:00+00:00');

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);

        // Delta window is in the future — no rows updated after that.
        $this->assertSame([], $body['checkins'], 'checkins delta should be empty for future cursor');
        // Stats always reflect ALL rows, not just the delta window.
        $this->assertSame(1, $body['stats']['checkins'], 'stats should count all rows regardless of cursor');
    }

    public function testDeltaFeedMalformedSinceDoesNotError(): void
    {
        $uid = $this->login();
        $sessionId = $this->seedNetSession($uid, ['status' => 'live']);
        $this->seedCheckinRow($sessionId, $uid, '9M2RDX');

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/net-sessions/' . $sessionId . '/checkins?since=not-a-date');

        // Defensive parse: malformed cursor is treated as no cursor → all rows returned.
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertCount(1, $body['checkins'], 'malformed since should fall back to returning all rows');
    }
}
