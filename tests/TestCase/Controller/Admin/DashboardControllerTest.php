<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Admin;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for /admin (M4-T5) — dashboard root.
 *
 * Covers:
 *  - Anonymous access redirects to /login (Authentication middleware).
 *  - Authenticated non-admins get 403 (beforeFilter() guard).
 *  - Admins see the rendered dashboard with the well-known section headings
 *    so a future template tweak that drops them is caught at CI.
 */
final class DashboardControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates', 'app.Cards', 'app.CardBackgrounds', 'app.AuditLogs', 'app.GuestVisits'];

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

    public function testNonAdminGets403(): void
    {
        $this->loginAs('user');
        $this->get('/admin');
        $this->assertResponseCode(403);
    }

    public function testAdminSeesDashboard(): void
    {
        $this->loginAs('admin');
        $this->get('/admin');
        $this->assertResponseOk();
        $this->assertResponseContains('Admin Dashboard');
        $this->assertResponseContains('Quick links');
        $this->assertResponseContains('Recent activity');
    }

    public function testAnonymousRedirected(): void
    {
        $this->get('/admin');
        $this->assertRedirectContains('/login');
    }
}
