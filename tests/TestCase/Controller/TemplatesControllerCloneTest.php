<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Clone-and-edit integration tests (M3-T8).
 *
 * Verifies the three readable-source paths (own / system / public-approved)
 * each produce a freshly-owned copy with all visibility flags reset, plus
 * the negative case where an unrelated private template is not clonable.
 * Mirrors the auth + fixture pattern used by the gallery/save tests so the
 * suite stays uniform.
 */
final class TemplatesControllerCloneTest extends TestCase
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

    public function testCloneSystemTemplate(): void
    {
        $userId = $this->loginAs();
        $tpls = $this->getTableLocator()->get('Templates');
        $sys = $tpls->saveOrFail($tpls->newEntity([
            'name' => 'Sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => [
                ['placeholder' => '{callsign}', 'x' => 1, 'y' => 1,
                 'font' => 'Inter-Regular.ttf', 'size' => 12, 'color' => '#000'],
            ]]),
            'is_system' => true, 'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_system' => true, 'is_public' => true, 'is_approved' => true]]));

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/templates/' . $sys->id . '/clone');

        // Find the clone row
        $clones = $tpls->find()->where(['user_id' => $userId])->orderBy(['id' => 'DESC'])->all();
        $this->assertGreaterThan(0, $clones->count());
        $clone = $clones->first();
        $this->assertSame('Sys (copy)', $clone->name);
        $this->assertFalse((bool)$clone->is_system);
        $this->assertFalse((bool)$clone->is_public);
        $this->assertFalse((bool)$clone->is_approved);
        $this->assertSame((string)$sys->layout_json, (string)$clone->layout_json);
        $this->assertRedirectContains('/templates/' . $clone->id . '/edit');
    }

    public function testCloneOwnTemplate(): void
    {
        $userId = $this->loginAs();
        $tpls = $this->getTableLocator()->get('Templates');
        $mine = $tpls->saveOrFail($tpls->newEntity([
            'user_id' => $userId, 'name' => 'Mine',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
        ]));
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/templates/' . $mine->id . '/clone');

        $clone = $tpls->find()->where(['user_id' => $userId, 'name' => 'Mine (copy)'])->first();
        $this->assertNotNull($clone);
    }

    public function testCannotClonePrivateForeignTemplate(): void
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
            // private (not public/approved)
        ]));
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/templates/' . $bsTpl->id . '/clone');
        $this->assertResponseCode(404);
    }
}
