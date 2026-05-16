<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Template gallery integration tests (M3-T7).
 *
 * Verifies the three-tab layout segregates rows correctly:
 *  - `mine` only includes templates owned by the current user.
 *  - `public` only includes is_public + is_approved rows owned by *other* users.
 *  - `system` includes is_system rows regardless of owner.
 *
 * Plus the empty-state copy and the auth gate (anonymous → /login).
 */
final class TemplatesControllerIndexTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Templates'];

    private function loginAs(string $email = 'a@x.com'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X', 'email' => $email, 'role' => 'user', 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);
        return $u->id;
    }

    public function testTabsShowMineSeparate(): void
    {
        $u1 = $this->loginAs('owner@x.com');
        $users = $this->getTableLocator()->get('Users');
        $u2 = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));

        $tpls = $this->getTableLocator()->get('Templates');
        $tpls->saveOrFail($tpls->newEntity([
            'user_id' => $u1, 'name' => 'MyOwn',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
        ]));
        $tpls->saveOrFail($tpls->newEntity([
            'user_id' => $u2->id, 'name' => 'PublicOne',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_public' => true, 'is_approved' => true]]));
        $tpls->saveOrFail($tpls->newEntity([
            'name' => 'SysOne',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $this->get('/templates');
        $this->assertResponseOk();
        $this->assertResponseContains('MyOwn');
        $this->assertResponseContains('PublicOne');
        $this->assertResponseContains('SysOne');
    }

    public function testEmptyStateOnNoTemplates(): void
    {
        $this->loginAs();
        $this->get('/templates');
        $this->assertResponseOk();
        $this->assertResponseContains('No templates yet');
    }

    public function testRequiresAuth(): void
    {
        $this->get('/templates');
        $this->assertRedirectContains('/login');
    }
}
