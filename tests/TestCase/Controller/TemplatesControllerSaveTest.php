<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class TemplatesControllerSaveTest extends TestCase
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

    public function testNewTemplateValidPost(): void
    {
        $userId = $this->loginAs();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/templates/new', [
            'name' => 'My design',
            'description' => 'A nice eQSL',
            'canvas_width' => 1500,
            'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => [
                ['placeholder' => '{callsign}', 'x' => 100, 'y' => 200,
                 'font' => 'Inter-Bold.ttf', 'size' => 96, 'color' => '#000000', 'rotation' => 0],
            ]]),
        ]);
        $this->assertResponseCode(302);
        $tpls = $this->getTableLocator()->get('Templates');
        $row = $tpls->find()->where(['user_id' => $userId, 'name' => 'My design'])->first();
        $this->assertNotNull($row);
    }

    public function testInvalidLayoutShowsErrorsAndDoesNotSave(): void
    {
        $userId = $this->loginAs();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/templates/new', [
            'name' => 'broken',
            'canvas_width' => 1500,
            'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => [
                ['placeholder' => 'x', 'x' => 9999, 'y' => 0,
                 'font' => 'Comic.ttf', 'size' => 0, 'color' => 'red'],
            ]]),
        ]);
        $this->assertResponseOk(); // re-render
        $tpls = $this->getTableLocator()->get('Templates');
        $count = $tpls->find()->where(['user_id' => $userId])->count();
        $this->assertSame(0, $count);
    }

    public function testEditOwnTemplate(): void
    {
        $userId = $this->loginAs();
        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'user_id' => $userId, 'name' => 'orig',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
        ]));
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put('/templates/' . $tpl->id . '/edit', [
            'name' => 'renamed',
            'canvas_width' => 1500,
            'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
        ]);
        $this->assertResponseCode(302);
        $row = $tpls->get($tpl->id);
        $this->assertSame('renamed', $row->name);
    }

    public function testCannotEditOtherUserTemplate(): void
    {
        $a = $this->loginAs();
        $users = $this->getTableLocator()->get('Users');
        $b = $users->saveOrFail($users->newEntity([
            'name' => 'B', 'email' => 'b@x.com', 'role' => 'user', 'callsign' => 'BB1BB', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        $tpls = $this->getTableLocator()->get('Templates');
        $bsTpl = $tpls->saveOrFail($tpls->newEntity([
            'user_id' => $b->id, 'name' => 'theirs',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
        ]));
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->put('/templates/' . $bsTpl->id . '/edit', [
            'name' => 'hijacked',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
        ]);
        $this->assertResponseCode(404);
    }
}
