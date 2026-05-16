<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * QsosController::quick() — M5 T7 portable-first one-thumb entry surface.
 *
 * Covers the auto-fill behaviour that distinguishes /qsos/quick from
 * the heavier /qsos/new form:
 *  - GET renders the minimal form + recent list
 *  - POST without qso_datetime_utc auto-fills "now in UTC"
 *  - POST without band but with frequency derives band via HamRadio
 *  - POST without transport / qso_type defaults to rf / contact
 *  - POST does NOT redirect (caller stays on the form for next contact)
 *  - Anonymous request 302s to /login
 */
final class QsosControllerQuickTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos', 'app.Activations'];

    private function login(): int
    {
        $users = $this->getTableLocator()->get('Users');
        $user = $users->saveOrFail($users->newEntity([
            'name' => 'OP',
            'email' => 'op@x.com',
            'role' => 'user',
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $user->id, 'email' => 'op@x.com']]);

        return $user->id;
    }

    public function testGetRendersForm(): void
    {
        $this->login();
        $this->get('/qsos/quick');
        $this->assertResponseOk();
        $this->assertResponseContains('Quick add');
        $this->assertResponseContains('name="call_worked"');
    }

    public function testPostAutoFillsDatetimeUtc(): void
    {
        $userId = $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => '9M2RDX',
            'frequency_mhz' => '14.20',
            'mode' => 'SSB',
        ]);

        $this->assertResponseOk();  // Re-renders, no redirect.
        $row = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'call_worked' => '9M2RDX'])
            ->firstOrFail();
        $this->assertNotNull($row->qso_datetime_utc, 'qso_datetime_utc must be auto-filled');
        // Confirm it's within the last minute.
        $diffSec = abs(time() - $row->qso_datetime_utc->getTimestamp());
        $this->assertLessThan(60, $diffSec, 'qso_datetime_utc should be "now"');
    }

    public function testPostDerivesBandFromFrequency(): void
    {
        $userId = $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => 'W1AW',
            'frequency_mhz' => '14.07415',
            'mode' => 'FT8',
        ]);

        $row = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'call_worked' => 'W1AW'])
            ->firstOrFail();
        $this->assertSame('20m', $row->band, '14.07415 MHz must derive to 20m');
    }

    public function testPostDefaultsTransportAndQsoType(): void
    {
        $userId = $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => '9W2NSP',
            'frequency_mhz' => '145.625',
            'mode' => 'FM',
        ]);

        $row = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'call_worked' => '9W2NSP'])
            ->firstOrFail();
        $this->assertSame('rf', $row->transport, 'transport defaults to rf');
        $this->assertSame('contact', $row->qso_type, 'qso_type defaults to contact');
    }

    public function testPostDoesNotRedirect(): void
    {
        $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => 'JA1ABC',
            'frequency_mhz' => '7.020',
            'mode' => 'CW',
        ]);
        // Quick-add stays on the form for the next contact — no 302.
        $this->assertResponseCode(200);
        $this->assertResponseNotContains('http-equiv="refresh"');
    }

    public function testAnonymousRedirectedToLogin(): void
    {
        $this->get('/qsos/quick');
        $this->assertRedirectContains('/login');
    }

    /**
     * T9 — XHR contract. Clients sending Accept: application/json get a
     * JSON {ok: true, qso: {...}} payload instead of the HTML re-render.
     */
    public function testJsonPostReturnsJsonPayload(): void
    {
        $userId = $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/qsos/quick', [
            'call_worked' => 'JA1XYZ',
            'frequency_mhz' => '21.250',
            'mode' => 'SSB',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertTrue($body['ok']);
        $this->assertSame('JA1XYZ', $body['qso']['callsign']);
        $this->assertSame('15m', $body['qso']['band'], '21.250 MHz derives to 15m');
        $this->assertSame('SSB', $body['qso']['mode']);
    }

    /**
     * T9 — JSON path with validation failure returns 422 + errors map.
     */
    public function testJsonPostValidationFailureReturns422(): void
    {
        $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/qsos/quick', [
            // call_worked is required by the QSO entity validation.
            'call_worked' => '',
            'mode' => 'CW',
        ]);

        $this->assertResponseCode(422);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertArrayHasKey('errors', $body);
    }

    /**
     * T16 — when an active activation exists, new QSOs auto-tag with it.
     */
    public function testPostAutoTagsWithActiveActivation(): void
    {
        $userId = $this->login();
        // Seed an active activation.
        $tbl = $this->getTableLocator()->get('Activations');
        $activation = $tbl->saveOrFail($tbl->newEntity([
            'code' => 'POTA-K-1234', 'name' => 'Active Park',
            'user_id' => $userId,
            'started_at' => '2026-05-16 08:00:00',
        ], ['accessibleFields' => ['*' => true]]));

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => 'W1AW',
            'frequency_mhz' => '14.07415',
            'mode' => 'FT8',
        ]);

        $row = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'call_worked' => 'W1AW'])
            ->firstOrFail();
        $this->assertSame((int)$activation->id, (int)$row->activation_id, 'QSO must auto-tag with active activation');
    }

    /**
     * T16 — with no active activation, activation_id stays NULL.
     */
    public function testPostWithoutActiveActivationLeavesNull(): void
    {
        $userId = $this->login();

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => 'JA1ABC',
            'frequency_mhz' => '14.20',
            'mode' => 'SSB',
        ]);

        $row = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'call_worked' => 'JA1ABC'])
            ->firstOrFail();
        $this->assertNull($row->activation_id);
    }

    /**
     * T16 — an ENDED activation must NOT auto-tag (findActiveForUser excludes
     * rows with ended_at set).
     */
    public function testPostIgnoresEndedActivation(): void
    {
        $userId = $this->login();
        $tbl = $this->getTableLocator()->get('Activations');
        $tbl->saveOrFail($tbl->newEntity([
            'code' => 'SOTA-OLD', 'name' => 'Yesterday Summit',
            'user_id' => $userId,
            'started_at' => '2026-05-15 08:00:00',
            'ended_at'   => '2026-05-15 12:00:00',
        ], ['accessibleFields' => ['*' => true]]));

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => '9W2NSP',
            'frequency_mhz' => '7.020',
            'mode' => 'CW',
        ]);

        $row = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'call_worked' => '9W2NSP'])
            ->firstOrFail();
        $this->assertNull($row->activation_id, 'Ended activations must not auto-tag new QSOs');
    }

    /**
     * T16 — activation_id from request body is ignored (locked from mass
     * assignment in the Qso entity). User can't spoof tagging.
     */
    public function testPostIgnoresActivationIdFromRequestBody(): void
    {
        $userId = $this->login();
        // Seed user B's activation, then attempt to tag with it from user A.
        $users = $this->getTableLocator()->get('Users');
        $userB = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user',
            'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $tbl = $this->getTableLocator()->get('Activations');
        $bsAct = $tbl->saveOrFail($tbl->newEntity([
            'code' => 'POTA-OTHER', 'name' => 'Their Park',
            'user_id' => (int)$userB->id,
            'started_at' => '2026-05-16 08:00:00',
        ], ['accessibleFields' => ['*' => true]]));

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => 'VK2XYZ',
            'frequency_mhz' => '14.0',
            'mode' => 'SSB',
            'activation_id' => (int)$bsAct->id,  // attempt to spoof
        ]);

        $row = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'call_worked' => 'VK2XYZ'])
            ->firstOrFail();
        $this->assertNull($row->activation_id, "Mustn't tag with another user's activation via request body");
    }

    /**
     * T21 — POST with client_uuid creates the QSO with that UUID set.
     */
    public function testPostStoresClientUuidWhenProvided(): void
    {
        $userId = $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => 'W1AW',
            'mode' => 'SSB',
            'client_uuid' => 'a1b2c3d4-1111-2222-3333-aabbccddeeff',
        ]);

        $row = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'call_worked' => 'W1AW'])
            ->firstOrFail();
        $this->assertSame('a1b2c3d4-1111-2222-3333-aabbccddeeff', $row->client_uuid);
    }

    /**
     * T21 — Same client_uuid POSTed twice returns the existing row
     * instead of creating a duplicate. JSON path: response has
     * replayed: true.
     */
    public function testJsonPostWithDuplicateUuidReplaysExistingRow(): void
    {
        $userId = $this->login();
        $uuid = 'b2b2b2b2-1111-2222-3333-444444444444';
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        // First POST: creates the row.
        $this->post('/qsos/quick', [
            'call_worked' => 'JA1ABC',
            'mode' => 'CW',
            'client_uuid' => $uuid,
        ]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertFalse($body['replayed']);
        $firstId = $body['qso']['id'];

        // Second POST with same UUID: must return the same row, replayed: true.
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => 'DIFFERENT-CALL-IGNORED',
            'mode' => 'FT8',
            'client_uuid' => $uuid,
        ]);
        $body2 = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body2['ok']);
        $this->assertTrue($body2['replayed']);
        $this->assertSame($firstId, $body2['qso']['id'], 'Same row returned, not a new insert');
        $this->assertSame('JA1ABC', $body2['qso']['callsign'], 'Original data preserved (request body ignored on replay)');

        // Only one row in DB.
        $count = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'client_uuid' => $uuid])
            ->count();
        $this->assertSame(1, $count, 'No duplicate row');
    }

    /**
     * T21 — Cross-user UUID isolation: user A's POST with UUID X must
     * NOT replay user B's row with the same UUID. The dedup key is
     * (user_id, client_uuid), not just client_uuid alone.
     */
    public function testClientUuidIsScopedPerUser(): void
    {
        $userA = $this->login('a@x.com');
        $uuid = 'shared-uuid-across-users-0000-0001';
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => 'CALLBYA',
            'mode' => 'SSB',
            'client_uuid' => $uuid,
        ]);
        $this->assertResponseSuccess();

        // Switch to user B.
        $users = $this->getTableLocator()->get('Users');
        $userB = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user',
            'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => (int)$userB->id, 'email' => 'b@x.com']]);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/quick', [
            'call_worked' => 'CALLBYB',
            'mode' => 'CW',
            'client_uuid' => $uuid,
        ]);

        // Both users now have a row with this UUID — they don't collide.
        $rowA = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userA, 'client_uuid' => $uuid])
            ->firstOrFail();
        $rowB = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => (int)$userB->id, 'client_uuid' => $uuid])
            ->firstOrFail();
        $this->assertSame('CALLBYA', $rowA->call_worked);
        $this->assertSame('CALLBYB', $rowB->call_worked);
        $this->assertNotSame($rowA->id, $rowB->id);
    }

    /**
     * T16 — JSON path also honours auto-tagging.
     */
    public function testJsonPostAutoTagsWithActiveActivation(): void
    {
        $userId = $this->login();
        $tbl = $this->getTableLocator()->get('Activations');
        $activation = $tbl->saveOrFail($tbl->newEntity([
            'code' => 'POTA-K-1234', 'name' => 'Active Park',
            'user_id' => $userId,
            'started_at' => '2026-05-16 08:00:00',
        ], ['accessibleFields' => ['*' => true]]));

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/qsos/quick', [
            'call_worked' => 'DL1ABC',
            'frequency_mhz' => '7.020',
            'mode' => 'CW',
        ]);

        $this->assertResponseOk();
        $row = $this->getTableLocator()->get('Qsos')->find()
            ->where(['user_id' => $userId, 'call_worked' => 'DL1ABC'])
            ->firstOrFail();
        $this->assertSame((int)$activation->id, (int)$row->activation_id);
    }

}
