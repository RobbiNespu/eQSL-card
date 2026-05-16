<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Admin;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for /admin/cards (M4-T7).
 *
 * Covers:
 *  - 403 for authenticated non-admins (mirrors DashboardController guard).
 *  - List renders OK for admins.
 */
final class CardsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Templates', 'app.CardBackgrounds', 'app.Cards', 'app.GuestVisits'];

    private static int $shaCounter = 0;

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
        $this->get('/admin/cards');
        $this->assertResponseCode(403);
    }

    public function testAdminSeesAllCards(): void
    {
        $userId = $this->loginAs('admin');
        $this->get('/admin/cards');
        $this->assertResponseOk();
        $this->assertResponseContains('Admin · All cards');
    }
}
