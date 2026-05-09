<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Admin;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for /admin/templates/* moderation queue
 * (M3-T10/T11/T12).
 *
 * Covers:
 *  - Non-admin users hit 403 on the pending list (beforeFilter() guard).
 *  - Admins see pending submissions in the rendered list.
 *  - Approving a pending template flips `is_approved`.
 *  - Rejecting a pending template clears `is_public` and leaves
 *    `is_approved = false` so the submitter can resubmit.
 *  - Approving an already-approved template 404s (firstOrFail on the
 *    pending-only filter), proving the action is idempotent.
 */
final class TemplatesModerationTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Templates'];

    private function loginAs(string $role, string $email = 'x@x.com'): int
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

    private function seedPendingTemplate(int $userId): int
    {
        $tpls = $this->getTableLocator()->get('Templates');
        $row = $tpls->saveOrFail($tpls->newEntity([
            'user_id' => $userId, 'name' => 'pending one',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_public' => true, 'is_approved' => false,
        ], ['accessibleFields' => ['is_public' => true, 'is_approved' => true]]));

        return $row->id;
    }

    public function testNonAdminGets403(): void
    {
        $this->loginAs('user');
        $this->get('/admin/templates/pending');
        $this->assertResponseCode(403);
    }

    public function testAdminSeesPendingList(): void
    {
        $this->loginAs('admin');
        $userId = $this->loginAs('user', 'submitter@x.com');
        $tplId = $this->seedPendingTemplate($userId);
        $this->loginAs('admin');
        $this->get('/admin/templates/pending');
        $this->assertResponseOk();
        $this->assertResponseContains('pending one');
    }

    public function testApproveSetsFlag(): void
    {
        $userId = $this->loginAs('user');
        $tplId = $this->seedPendingTemplate($userId);
        $this->loginAs('admin');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/templates/' . $tplId . '/approve');
        $this->assertRedirect('/admin/templates/pending');
        $row = $this->getTableLocator()->get('Templates')->get($tplId);
        $this->assertTrue((bool)$row->is_approved);
    }

    public function testRejectClearsPublicFlag(): void
    {
        $userId = $this->loginAs('user');
        $tplId = $this->seedPendingTemplate($userId);
        $this->loginAs('admin');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/templates/' . $tplId . '/reject', ['reason' => 'too generic']);
        $this->assertRedirect('/admin/templates/pending');
        $row = $this->getTableLocator()->get('Templates')->get($tplId);
        $this->assertFalse((bool)$row->is_public);
        $this->assertFalse((bool)$row->is_approved);
    }

    public function testApprovingApprovedReturns404(): void
    {
        $userId = $this->loginAs('user');
        $tpls = $this->getTableLocator()->get('Templates');
        $tpl = $tpls->saveOrFail($tpls->newEntity([
            'user_id' => $userId, 'name' => 'already',
            'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]),
            'is_public' => true, 'is_approved' => true,
        ], ['accessibleFields' => ['is_public' => true, 'is_approved' => true]]));
        $this->loginAs('admin');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/templates/' . $tpl->id . '/approve');
        $this->assertResponseCode(404);
    }
}
