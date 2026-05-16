<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Admin;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for /admin/users (M4-T6).
 *
 * Covers:
 *  - 403 for authenticated non-admins (mirrors DashboardController guard).
 *  - List renders OK for admins.
 *  - `?q=` search filters rows.
 *  - Role change persists AND writes a `user.role_changed` audit row.
 *  - Self-protection: cannot demote or delete yourself.
 *  - Delete soft-deletes (sets deleted_at, row remains).
 */
final class UsersControllerTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.AuditLogs'];

    private function makeUser(string $email, string $role = 'user'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X', 'email' => $email, 'role' => $role, 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        return $u->id;
    }

    private function loginAdmin(): int
    {
        $id = $this->makeUser('admin' . uniqid() . '@x.com', 'admin');
        $this->session(['Auth' => ['id' => $id]]);
        return $id;
    }

    public function testNonAdminGets403(): void
    {
        $id = $this->makeUser('u@x.com', 'user');
        $this->session(['Auth' => ['id' => $id]]);
        $this->get('/admin/users');
        $this->assertResponseCode(403);
    }

    public function testListPagination(): void
    {
        $this->loginAdmin();
        $this->get('/admin/users');
        $this->assertResponseOk();
    }

    public function testSearchByEmail(): void
    {
        $admin = $this->loginAdmin();
        $this->makeUser('findme@x.com');
        $this->makeUser('other@x.com');
        $this->get('/admin/users?q=findme');
        $this->assertResponseContains('findme@x.com');
        $this->assertResponseNotContains('other@x.com');
    }

    public function testRoleChangeAuditsAndPersists(): void
    {
        $admin = $this->loginAdmin();
        $target = $this->makeUser('promote@x.com', 'user');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/admin/users/{$target}/edit", ['role' => 'admin']);
        $this->assertRedirect('/admin/users');

        $row = $this->getTableLocator()->get('Users')->get($target);
        $this->assertSame('admin', $row->role);

        $audit = $this->getTableLocator()->get('AuditLogs');
        $log = $audit->find()->where(['event' => 'user.role_changed', 'target_id' => $target])->first();
        $this->assertNotNull($log);
    }

    public function testCannotDemoteSelf(): void
    {
        $admin = $this->loginAdmin();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put("/admin/users/{$admin}/edit", ['role' => 'user']);
        $this->assertResponseOk();
        $this->assertResponseContains('cannot demote yourself');
        $row = $this->getTableLocator()->get('Users')->get($admin);
        $this->assertSame('admin', $row->role);
    }

    public function testDeleteSoftDeletes(): void
    {
        $admin = $this->loginAdmin();
        $target = $this->makeUser('del@x.com');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post("/admin/users/{$target}/delete");
        $this->assertRedirect('/admin/users');
        $row = $this->getTableLocator()->get('Users')->get($target);
        $this->assertNotNull($row->deleted_at);
    }

    public function testCannotDeleteSelf(): void
    {
        $admin = $this->loginAdmin();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post("/admin/users/{$admin}/delete");
        $this->assertRedirect('/admin/users');
        $row = $this->getTableLocator()->get('Users')->get($admin);
        $this->assertNull($row->deleted_at);
    }
}
