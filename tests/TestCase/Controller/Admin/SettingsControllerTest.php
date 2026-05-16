<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Admin;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for /admin/settings (M4-T17).
 *
 * Covers:
 *  - 403 for authenticated non-admins (mirrors the rest of Admin prefix).
 *  - GET renders the form (Site name + SMTP sections present).
 *  - POST applies the allow-list, coerces numeric fields, and persists via
 *    the AppSettings runtime loader.
 */
final class SettingsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.AppSettings', 'app.AuditLogs'];

    protected function setUp(): void
    {
        parent::setUp();
        // Cold AppSettings cache between tests so a cross-test leak cannot
        // mask a missing controller write.
        (new \App\Service\AppSettings())->clear();
    }

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
        $this->get('/admin/settings');
        $this->assertResponseCode(403);
    }

    public function testGetShowsForm(): void
    {
        $this->loginAs('admin');
        $this->get('/admin/settings');
        $this->assertResponseOk();
        $this->assertResponseContains('Site name');
        $this->assertResponseContains('SMTP');
    }

    public function testPostSavesSettings(): void
    {
        $this->loginAs('admin');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/admin/settings', [
            'site_name' => 'My Station',
            'max_upload_mb' => '5',
            'share_base_url' => 'https://example.com',
            'smtp_host' => 'mail.example.com',
            'smtp_port' => '587',
        ]);
        $this->assertRedirect('/admin/settings');

        $s = new \App\Service\AppSettings();
        $s->clear();
        $this->assertSame('My Station', $s->get('site_name'));
        $this->assertSame(5, $s->get('max_upload_mb'));
        $this->assertSame(587, $s->get('smtp_port'));
    }
}
