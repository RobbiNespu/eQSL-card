<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * ActivationsController — M5 T14 integration tests.
 *
 * Covers:
 *  - GET /activations renders for logged-in users; redirects anon
 *  - POST /activations creates with server-stamped started_at / user_id
 *  - POST /activations/{id}/end sets ended_at
 *  - Cross-user isolation: ending another user's activation 404s
 *  - GET/POST /activations/{id}/edit owner-scoped + persists changes
 *  - POST /activations/{id}/delete owner-scoped; SET NULL cascade keeps qsos
 *  - The active-activation banner appears on /qsos/quick when one exists
 */
final class ActivationsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Activations', 'app.Qsos'];

    private function login(string $email = 'op@x.com'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'OP', 'email' => $email, 'role' => 'user',
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id, 'email' => $email]]);
        return (int)$u->id;
    }

    private function seedActivation(int $userId, array $extras = []): int
    {
        $tbl = $this->getTableLocator()->get('Activations');
        $row = $tbl->saveOrFail($tbl->newEntity(array_merge([
            'code' => 'POTA-K-1234',
            'name' => 'Test Park',
            'user_id' => $userId,
            'started_at' => '2026-05-16 08:00:00',
        ], $extras), ['accessibleFields' => ['*' => true]]));
        return (int)$row->id;
    }

    public function testIndexRendersForLoggedInUser(): void
    {
        $this->login();
        $this->get('/activations');
        $this->assertResponseOk();
        $this->assertResponseContains('Start a new activation');
    }

    public function testAnonymousRedirectedToLogin(): void
    {
        $this->get('/activations');
        $this->assertRedirectContains('/login');
    }

    public function testStartCreatesWithServerStampedFields(): void
    {
        $userId = $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/activations', [
            'code' => 'SOTA-9M2/PR-001',
            'name' => 'Bukit Larut',
            'grid_square' => 'OJ02',
            // Try to spoof these — server must override.
            'user_id' => 999,
            'started_at' => '2020-01-01 00:00:00',
            'ended_at' => '2020-01-02 00:00:00',
        ]);
        $this->assertResponseSuccess();  // 302 redirect on success

        $row = $this->getTableLocator()->get('Activations')->find()
            ->where(['code' => 'SOTA-9M2/PR-001'])
            ->firstOrFail();
        $this->assertSame($userId, (int)$row->user_id, 'user_id must come from session, not request');
        $this->assertNull($row->ended_at);
        // started_at should be "now" not the spoofed 2020 value
        $this->assertGreaterThan(
            (new \DateTimeImmutable('2026-01-01'))->getTimestamp(),
            $row->started_at->getTimestamp(),
            'started_at must be server-stamped, not from request'
        );
    }

    public function testStartWithInvalidGridShowsErrors(): void
    {
        $this->login();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/activations', [
            'code' => 'X', 'name' => 'Y', 'grid_square' => 'XX99', // invalid Maidenhead
        ]);
        $this->assertResponseOk();  // re-renders with errors, no redirect
        $this->assertResponseContains('Maidenhead');
    }

    public function testEndSetsEndedAt(): void
    {
        $userId = $this->login();
        $id = $this->seedActivation($userId, ['ended_at' => null]);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/activations/' . $id . '/end');

        $row = $this->getTableLocator()->get('Activations')->get($id);
        $this->assertNotNull($row->ended_at);
    }

    public function testEndOtherUserActivation404s(): void
    {
        $a = $this->login('a@x.com');
        // Seed an activation owned by user B.
        $users = $this->getTableLocator()->get('Users');
        $userB = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user',
            'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsActivation = $this->seedActivation((int)$userB->id, ['ended_at' => null]);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/activations/' . $bsActivation . '/end');
        $this->assertResponseCode(404);

        // User B's activation must still be open.
        $row = $this->getTableLocator()->get('Activations')->get($bsActivation);
        $this->assertNull($row->ended_at);
    }

    public function testEditPersistsChanges(): void
    {
        $userId = $this->login();
        $id = $this->seedActivation($userId);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/activations/' . $id . '/edit', [
            'code' => 'POTA-K-1234',
            'name' => 'Renamed Park',
            'grid_square' => 'OJ03',
            'notes' => 'Updated notes',
        ]);
        $this->assertResponseCode(302);

        $row = $this->getTableLocator()->get('Activations')->get($id);
        $this->assertSame('Renamed Park', $row->name);
        $this->assertSame('OJ03', $row->grid_square);
        $this->assertSame('Updated notes', $row->notes);
    }

    public function testDeleteKeepsLinkedQsos(): void
    {
        $userId = $this->login();
        $activationId = $this->seedActivation($userId);

        // Link a QSO to this activation.
        $qsos = $this->getTableLocator()->get('Qsos');
        $qso = $qsos->saveOrFail($qsos->newEntity([
            'user_id' => $userId,
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-16 09:00:00',
            'qso_type' => 'contact',
            'transport' => 'rf',
        ], ['accessibleFields' => ['*' => true]]));
        // activation_id is locked from mass assignment — set directly.
        $qso->set('activation_id', $activationId, ['guard' => false]);
        $qsos->saveOrFail($qso);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/activations/' . $activationId . '/delete');
        $this->assertResponseCode(302);

        // Activation row gone.
        $this->assertNull($this->getTableLocator()->get('Activations')->find()
            ->where(['id' => $activationId])->first());

        // QSO row survives with activation_id NULL (FK ON DELETE SET NULL).
        $qsoAfter = $qsos->get($qso->id);
        $this->assertNull($qsoAfter->activation_id);
        $this->assertSame('W1AW', $qsoAfter->call_worked);
    }

    public function testQuickAddShowsActiveBanner(): void
    {
        $userId = $this->login();
        $this->seedActivation($userId, [
            'name' => 'Bukit Larut',
            'code' => 'SOTA-9M2/PR-001',
            'grid_square' => 'OJ02',
            'ended_at' => null,
        ]);

        $this->get('/qsos/quick');
        $this->assertResponseOk();
        $this->assertResponseContains('Logging for');
        $this->assertResponseContains('Bukit Larut');
        $this->assertResponseContains('OJ02');
    }

    public function testQuickAddShowsPromptWhenNoActiveActivation(): void
    {
        $this->login();
        $this->get('/qsos/quick');
        $this->assertResponseOk();
        $this->assertResponseContains('Logging without an activation');
    }

    /**
     * T17 — Export endpoint returns ADIF as text/plain with Content-Disposition
     * attachment and a slugified filename derived from the activation code.
     */
    public function testExportReturnsAdifAttachment(): void
    {
        $userId = $this->login();
        $activationId = $this->seedActivation($userId, [
            'code' => 'POTA-K-1234',
            'name' => 'Test Park',
            'grid_square' => 'OJ02wx',
        ]);
        // Seed a tagged QSO.
        $qsos = $this->getTableLocator()->get('Qsos');
        $qso = $qsos->newEntity([
            'user_id' => $userId,
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-16 09:15:30',
            'band' => '20m', 'mode' => 'SSB', 'frequency_mhz' => '14.2',
            'rst_sent' => '59', 'rst_received' => '59',
            'qso_type' => 'contact', 'transport' => 'rf',
        ], ['accessibleFields' => ['*' => true]]);
        $qso->set('activation_id', $activationId, ['guard' => false]);
        $qsos->saveOrFail($qso);

        $this->get('/activations/' . $activationId . '/export.adi');
        $this->assertResponseOk();
        $this->assertContentType('text/plain');
        $this->assertHeaderContains('Content-Disposition', 'POTA-K-1234.adi');

        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('<ADIF_VER:5>3.1.4', $body);
        $this->assertStringContainsString('<CALL:4>W1AW', $body);
        $this->assertStringContainsString('<MY_POTA_REF:6>K-1234', $body);
        $this->assertStringContainsString('<MY_GRIDSQUARE:6>OJ02wx', $body);
    }

    public function testExportEmptyActivationReturnsHeaderOnlyAdif(): void
    {
        $userId = $this->login();
        $activationId = $this->seedActivation($userId);

        $this->get('/activations/' . $activationId . '/export.adi');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('<EOH>', $body);
        $this->assertStringNotContainsString('<EOR>', $body);
    }

    public function testExportOtherUserActivation404s(): void
    {
        $this->login('a@x.com');
        $users = $this->getTableLocator()->get('Users');
        $userB = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user',
            'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsActivation = $this->seedActivation((int)$userB->id);

        $this->get('/activations/' . $bsActivation . '/export.adi');
        $this->assertResponseCode(404);
    }

    public function testExportExcludesQsosFromOtherActivations(): void
    {
        $userId = $this->login();
        $a1 = $this->seedActivation($userId, ['code' => 'POTA-K-1234', 'name' => 'Park A']);
        $a2 = $this->seedActivation($userId, ['code' => 'POTA-K-9999', 'name' => 'Park B']);
        $qsos = $this->getTableLocator()->get('Qsos');

        foreach (['W1AW' => $a1, 'JA1ABC' => $a2] as $call => $actId) {
            $q = $qsos->newEntity([
                'user_id' => $userId, 'call_worked' => $call,
                'qso_datetime_utc' => '2026-05-16 09:00:00',
                'qso_type' => 'contact', 'transport' => 'rf',
            ], ['accessibleFields' => ['*' => true]]);
            $q->set('activation_id', $actId, ['guard' => false]);
            $qsos->saveOrFail($q);
        }

        $this->get('/activations/' . $a1 . '/export.adi');
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('<CALL:4>W1AW', $body);
        $this->assertStringNotContainsString('JA1ABC', $body);
    }
}
