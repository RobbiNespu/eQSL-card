<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Designer view smoke tests (M3-T3).
 *
 * The view rendering doesn't actually execute Fabric.js (that's browser-side
 * and lands in M3-T16's Vitest harness), so these tests just confirm the
 * server-rendered scaffolding is correct: assets are linked, the categorized
 * palette markup is present, and an existing template's `layout_json` is
 * embedded into the Alpine factory call so the client can rehydrate.
 */
final class TemplatesDesignerSmokeTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Templates'];

    private function loginAs(string $email): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'X', 'email' => $email, 'role' => 'user', 'callsign' => 'AA1AA',
            'password_hash' => (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash('pw'),
        ], ['accessibleFields' => ['*' => true]]));
        $this->session(['Auth' => ['id' => $u->id]]);

        return $u->id;
    }

    public function testDesignerLoadsRequiredAssets(): void
    {
        $this->loginAs('a@x.com');
        $this->get('/templates/new');
        $this->assertResponseOk();
        $this->assertResponseContains('fabric.min.js');
        $this->assertResponseContains('designer.js');
        $this->assertResponseContains('Operator &amp; QSO basics');
        $this->assertResponseContains('Time &amp; frequency');
        $this->assertResponseContains('Signal report');
    }

    public function testDesignerInitialFieldsRoundTrip(): void
    {
        $userId = $this->loginAs('owner@x.com');
        $templates = $this->getTableLocator()->get('Templates');
        $tpl = $templates->saveOrFail($templates->newEntity([
            'user_id' => $userId, 'name' => 'r/t',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => [
                ['placeholder' => '{callsign}', 'x' => 100, 'y' => 200,
                 'font' => 'Inter-Bold.ttf', 'size' => 96, 'color' => '#000000', 'rotation' => 0],
            ]]),
        ]));
        $this->get('/templates/' . $tpl->id . '/edit');
        $this->assertResponseOk();
        // The layout_json is embedded as JSON inside the designer(...) factory
        // call. We just confirm the round-trip: what the controller hands the
        // view shows up in the rendered HTML for the Alpine factory to parse.
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('{callsign}', $body);
        $this->assertStringContainsString('Inter-Bold.ttf', $body);
    }
}
