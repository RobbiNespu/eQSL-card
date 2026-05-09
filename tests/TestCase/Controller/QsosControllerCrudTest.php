<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * QsosController CRUD integration tests (M2-T3).
 *
 * Covers:
 *  - GET /qsos/new renders the add form.
 *  - POST /qsos/new creates a row whose user_id comes from the session,
 *    not the request payload.
 *  - PUT /qsos/{id}/edit updates a row.
 *  - PUT /qsos/{id}/edit cannot reassign user_id (entity-layer guard).
 *  - POST /qsos/{id}/delete hard-deletes the row.
 *  - GET /qsos/{id}/edit on another user's row 404s.
 */
final class QsosControllerCrudTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Qsos'];

    private function seedUserAndLogin(string $email = 'op@x.com'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $user = $users->saveOrFail($users->newEntity([
            'name' => 'OP',
            'email' => $email,
            'role' => 'user',
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $user->id, 'email' => $email]]);

        return $user->id;
    }

    private function seedQso(int $userId, array $extras = []): int
    {
        $qsos = $this->getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity(array_merge([
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
            'band' => '20m',
            'mode' => 'SSB',
        ], $extras));
        $entity->user_id = $userId;
        $qsos->saveOrFail($entity);

        return $entity->id;
    }

    public function testGetAddForm(): void
    {
        $this->seedUserAndLogin();
        $this->get('/qsos/new');
        $this->assertResponseOk();
        $this->assertResponseContains('Add QSO');
    }

    public function testPostAddCreatesQsoWithCorrectUserId(): void
    {
        $userId = $this->seedUserAndLogin();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/qsos/new', [
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32',
            'band' => '20m',
            'mode' => 'SSB',
        ]);
        $this->assertResponseCode(302);
        $qsos = $this->getTableLocator()->get('Qsos');
        $row = $qsos->find()->where(['call_worked' => 'W1AW'])->first();
        $this->assertNotNull($row);
        $this->assertSame($userId, $row->user_id, 'user_id must come from session, not request');
    }

    public function testEditUpdates(): void
    {
        $userId = $this->seedUserAndLogin();
        $qsoId = $this->seedQso($userId);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/qsos/{$qsoId}/edit", [
            'call_worked' => 'K2DST',
            'qso_datetime_utc' => '2026-05-09 14:32',
            'band' => '20m',
            'mode' => 'SSB',
        ]);
        $this->assertRedirectContains('/qsos/' . $qsoId);
        $row = $this->getTableLocator()->get('Qsos')->get($qsoId);
        $this->assertSame('K2DST', $row->call_worked);
    }

    public function testEditCannotChangeUserId(): void
    {
        $userId = $this->seedUserAndLogin();
        $qsoId = $this->seedQso($userId);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/qsos/{$qsoId}/edit", [
            'user_id' => 999, // attacker tries to reassign
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32',
        ]);
        $row = $this->getTableLocator()->get('Qsos')->get($qsoId);
        $this->assertSame($userId, $row->user_id, 'user_id must NOT be reassignable via patchEntity');
    }

    public function testDeleteRemovesRow(): void
    {
        $userId = $this->seedUserAndLogin();
        $qsoId = $this->seedQso($userId);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post("/qsos/{$qsoId}/delete");
        $this->assertRedirect('/qsos');
        $count = $this->getTableLocator()->get('Qsos')->find()->where(['id' => $qsoId])->count();
        $this->assertSame(0, $count);
    }

    public function testCannotEditOtherUserQso(): void
    {
        $a = $this->seedUserAndLogin('a@x.com');
        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B',
            'email' => 'b@x.com',
            'role' => 'user',
            'callsign' => 'BB1BB',
            'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $bsQso = $this->seedQso($b->id);

        $this->get("/qsos/{$bsQso}/edit");
        $this->assertResponseCode(404);
    }
}
