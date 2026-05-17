<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * QsosController::dupeCheck — M5 T25 integration tests.
 *
 * Endpoint: GET /api/qsos/dupe-check?callsign=W1AW&band=20m
 * Response: JSON { callsign, total_qsos, last_worked_at,
 *                  same_band_today, same_band_this_activation }
 *
 * Covers:
 *  - Never-worked callsign → zero shape
 *  - Worked callsign → total_qsos + last_worked_at populated
 *  - Same-band-today flag flips when worked today on the queried band
 *  - Same-band-this-activation flag flips when activation is active +
 *    the (call, band) is already tagged with it
 *  - Cross-user isolation
 *  - Anonymous → /login redirect (auth required)
 *  - Empty / malformed callsign returns zero shape (no HTTP error)
 *  - Callsign case-insensitive matching (server normalises)
 *  - HTTP method limited to GET
 */
final class QsosControllerDupeCheckTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos', 'app.Activations'];

    private function login(string $email = 'op@x.com'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'OP', 'email' => $email, 'role' => 'user', 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id, 'email' => $email]]);
        return (int)$u->id;
    }

    private function seedQso(int $userId, array $extras = []): int
    {
        $qsos = $this->getTableLocator()->get('Qsos');
        $row = $qsos->saveOrFail($qsos->newEntity(array_merge([
            'user_id' => $userId,
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-16 14:32:00',
            'band' => '20m', 'mode' => 'SSB',
            'qso_type' => 'contact', 'transport' => 'rf',
        ], $extras), ['accessibleFields' => ['*' => true]]));
        return (int)$row->id;
    }

    public function testNeverWorkedCallsignReturnsZeroShape(): void
    {
        $this->login();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=K1ZZZ&band=20m');

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame('K1ZZZ', $body['callsign']);
        $this->assertSame(0, $body['total_qsos']);
        $this->assertNull($body['last_worked_at']);
        $this->assertFalse($body['same_band_today']);
        $this->assertFalse($body['same_band_this_activation']);
    }

    public function testWorkedCallsignReturnsCountAndLastWorkedAt(): void
    {
        $userId = $this->login();
        $this->seedQso($userId, ['call_worked' => 'W1AW', 'qso_datetime_utc' => '2026-05-10 14:32:00']);
        $this->seedQso($userId, ['call_worked' => 'W1AW', 'qso_datetime_utc' => '2026-05-14 08:21:00']);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=W1AW&band=20m');

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(2, $body['total_qsos']);
        $this->assertStringStartsWith('2026-05-14T08:21:00', $body['last_worked_at']);
    }

    public function testCallsignNormalisedToUppercase(): void
    {
        // Operator on quick-add types 'w1aw' or 'W1aw' — server should
        // match the stored 'W1AW' (all-caps by ham convention + entity setter).
        $userId = $this->login();
        $this->seedQso($userId, ['call_worked' => 'W1AW']);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=w1aw&band=20m');

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(1, $body['total_qsos'], 'Lower-case input must match upper-case stored callsign');
    }

    public function testSameBandTodayFlagFiresOnlyWhenWorkedTodayOnQueriedBand(): void
    {
        $userId = $this->login();
        $todayUtc = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d');

        // Worked yesterday on 20m → should NOT trigger today flag.
        $this->seedQso($userId, [
            'call_worked' => 'JA1ABC', 'band' => '20m',
            'qso_datetime_utc' => $todayUtc . ' 00:00:00',
        ]);
        // Move it to yesterday by editing directly via SQL-like update.
        $tbl = $this->getTableLocator()->get('Qsos');
        $row = $tbl->find()->where(['call_worked' => 'JA1ABC'])->firstOrFail();
        $row->set('qso_datetime_utc', new \Cake\I18n\DateTime('-1 day', 'UTC'), ['guard' => false]);
        $tbl->saveOrFail($row);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=JA1ABC&band=20m');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(1, $body['total_qsos']);
        $this->assertFalse($body['same_band_today'], 'Yesterday QSO must not flag same_band_today');

        // Add a same-day-on-40m QSO. Same band check is for the QUERIED band,
        // so asking about 20m still returns false; asking about 40m returns true.
        $this->seedQso($userId, [
            'call_worked' => 'JA1ABC', 'band' => '40m',
            'qso_datetime_utc' => $todayUtc . ' 10:00:00',
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=JA1ABC&band=20m');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['same_band_today'], '40m today should not flag for 20m query');

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=JA1ABC&band=40m');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['same_band_today'], '40m today should flag for 40m query');
    }

    public function testSameBandThisActivationFlagRequiresActiveActivation(): void
    {
        $userId = $this->login();
        // Active activation.
        $acts = $this->getTableLocator()->get('Activations');
        $act = $acts->saveOrFail($acts->newEntity([
            'user_id' => $userId, 'code' => 'POTA-K-1234', 'name' => 'Test Park',
            'started_at' => '2026-05-16 08:00:00',
        ], ['accessibleFields' => ['*' => true]]));

        // Worked W1AW on 20m, tagged with this activation.
        $qsos = $this->getTableLocator()->get('Qsos');
        $q = $qsos->newEntity([
            'user_id' => $userId, 'call_worked' => 'W1AW', 'band' => '20m', 'mode' => 'SSB',
            'qso_datetime_utc' => '2026-05-16 09:00:00',
            'qso_type' => 'contact', 'transport' => 'rf',
        ], ['accessibleFields' => ['*' => true]]);
        $q->set('activation_id', $act->id, ['guard' => false]);
        $qsos->saveOrFail($q);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=W1AW&band=20m');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['same_band_this_activation'],
            'W1AW on 20m tagged with active activation → flag must fire');

        // Different band on same activation does NOT flag.
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=W1AW&band=40m');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['same_band_this_activation']);
    }

    public function testEndedActivationDoesNotTriggerInActivationFlag(): void
    {
        $userId = $this->login();
        $acts = $this->getTableLocator()->get('Activations');
        $endedAct = $acts->saveOrFail($acts->newEntity([
            'user_id' => $userId, 'code' => 'SOTA-OLD', 'name' => 'Yesterday',
            'started_at' => '2026-05-15 08:00:00',
            'ended_at'   => '2026-05-15 12:00:00',
        ], ['accessibleFields' => ['*' => true]]));

        $qsos = $this->getTableLocator()->get('Qsos');
        $q = $qsos->newEntity([
            'user_id' => $userId, 'call_worked' => 'W1AW', 'band' => '20m', 'mode' => 'SSB',
            'qso_datetime_utc' => '2026-05-15 09:00:00',
            'qso_type' => 'contact', 'transport' => 'rf',
        ], ['accessibleFields' => ['*' => true]]);
        $q->set('activation_id', $endedAct->id, ['guard' => false]);
        $qsos->saveOrFail($q);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=W1AW&band=20m');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['same_band_this_activation'],
            'Ended activation must not feed the same_band_this_activation flag');
    }

    public function testCrossUserIsolation(): void
    {
        // User A logs a QSO with W1AW. User B's dupe-check for W1AW must
        // return zero — A's QSO is invisible to B.
        $userA = $this->login('a@x.com');
        $this->seedQso($userA, ['call_worked' => 'W1AW']);

        // Switch to user B.
        $users = $this->getTableLocator()->get('Users');
        $userB = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB',
            'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => (int)$userB->id, 'email' => 'b@x.com']]);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=W1AW&band=20m');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(0, $body['total_qsos'], "User A's QSO must not leak to user B's dupe-check");
    }

    public function testAnonymousRedirectedToLogin(): void
    {
        $this->get('/api/qsos/dupe-check?callsign=W1AW&band=20m');
        $this->assertRedirectContains('/login');
    }

    public function testEmptyCallsignReturnsZeroShapeNot422(): void
    {
        // Per-keystroke client calls would noise the network tab with
        // HTTP errors. Empty/invalid callsign returns 200 + zeroes so
        // the UI just shows the "first contact" grey state.
        $this->login();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/qsos/dupe-check?callsign=&band=20m');
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(0, $body['total_qsos']);
    }

    public function testMalformedCallsignReturnsZeroShape(): void
    {
        $this->login();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        // Forbidden chars (spaces, punctuation other than /).
        $this->get('/api/qsos/dupe-check?callsign=' . urlencode('W1 AW!') . '&band=20m');
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(0, $body['total_qsos']);
    }

    public function testPostMethodRejected(): void
    {
        $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/api/qsos/dupe-check', ['callsign' => 'W1AW']);
        // Route is constrained to GET via setMethods(['GET']) in
        // config/routes.php, so a POST doesn't match the route and the
        // router returns 404. allowMethod('get') in the controller is
        // a defense-in-depth check that would only matter if the route
        // declared multiple methods.
        $this->assertResponseCode(404);
    }
}
