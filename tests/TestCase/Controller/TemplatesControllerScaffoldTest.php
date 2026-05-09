<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * TemplatesController scaffold tests (M3-T2).
 *
 * Covers the basics:
 *  - Anonymous request to `/templates/new` redirects to /login.
 *  - Logged-in user can GET the designer form and the page mounts the
 *    Alpine factory + ships fabric.min.js.
 *  - User can edit their own template.
 *  - Cross-user edit attempts surface as 404 (no row leakage).
 */
final class TemplatesControllerScaffoldTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates'];

    private function loginAs(string $email, string $role = 'user'): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X', 'email' => $email, 'role' => $role,
            'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);

        return $u->id;
    }

    public function testAnonymousRedirectsFromNew(): void
    {
        $this->get('/templates/new');
        $this->assertRedirectContains('/login');
    }

    public function testLoggedInUserCanGetNewForm(): void
    {
        $this->loginAs('a@x.com');
        $this->get('/templates/new');
        $this->assertResponseOk();
        $this->assertResponseContains('New template');
        // Confirms the view mounts the Alpine factory and ships fabric.js.
        $this->assertResponseContains('designer(');
        $this->assertResponseContains('fabric.min.js');
    }

    public function testEditOwnTemplate(): void
    {
        $userId = $this->loginAs('owner@x.com');
        $templates = $this->getTableLocator()->get('Templates');
        $tpl = $templates->saveOrFail($templates->newEntity([
            'user_id' => $userId, 'name' => 'mine',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
        ]));
        $this->get('/templates/' . $tpl->id . '/edit');
        $this->assertResponseOk();
        $this->assertResponseContains('Edit template');
    }

    public function testCannotEditOtherUserTemplate(): void
    {
        $a = $this->loginAs('a@x.com');
        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $templates = $this->getTableLocator()->get('Templates');
        $tpl = $templates->saveOrFail($templates->newEntity([
            'user_id' => $b->id, 'name' => 'theirs',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
        ]));
        $this->get('/templates/' . $tpl->id . '/edit');
        // Cross-user → 404 (firstOrFail) rather than 403, matching the
        // pattern used by CardsController so we don't leak row existence.
        $this->assertResponseCode(404);
    }
}
