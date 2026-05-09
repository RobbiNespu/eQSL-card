<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Admin;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for /admin/upgrade (M2-T18).
 *
 * Covers:
 *  - Anonymous access redirects to /login (Authentication middleware).
 *  - Authenticated non-admin users get 403 (beforeFilter() guard).
 *  - Admins can GET the page and see the migration status table.
 *  - Admins POSTing the form re-runs migrations and clears caches; the
 *    success banner appears in the rendered response.
 */
final class UpgradeControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users'];

    private function loginAs(string $role): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X', 'email' => uniqid('u') . '@x.com', 'role' => $role,
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);

        return $u->id;
    }

    public function testAnonymousRedirectsToLogin(): void
    {
        $this->get('/admin/upgrade');
        $this->assertRedirectContains('/login');
    }

    public function testNonAdminGetsForbidden(): void
    {
        $this->loginAs('user');
        $this->get('/admin/upgrade');
        $this->assertResponseCode(403);
    }

    public function testAdminCanGetPage(): void
    {
        $this->loginAs('admin');
        $this->get('/admin/upgrade');
        $this->assertResponseOk();
        $this->assertResponseContains('Migration status');
    }

    public function testAdminPostRunsMigrationsSuccessfully(): void
    {
        $this->loginAs('admin');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/upgrade', []);
        $this->assertResponseOk();
        $this->assertResponseContains('Migrations applied');
    }
}
