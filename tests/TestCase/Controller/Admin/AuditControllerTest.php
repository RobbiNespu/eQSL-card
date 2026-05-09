<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Admin;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for /admin/audit (M4-T8).
 *
 * Covers:
 *  - 403 for authenticated non-admins (mirrors DashboardController guard).
 *  - List renders OK for admins, with an audit row visible.
 *  - `?event=` filter narrows the listing.
 */
final class AuditControllerTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.AuditLogs'];

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
        $this->get('/admin/audit');
        $this->assertResponseCode(403);
    }

    public function testAdminSeesAuditLog(): void
    {
        $admin = $this->loginAs('admin');
        // Seed an audit row
        (new \App\Service\AuditLogger())->log('test.event', actorUserId: $admin);
        $this->get('/admin/audit');
        $this->assertResponseOk();
        $this->assertResponseContains('test.event');
    }

    public function testEventFilter(): void
    {
        $admin = $this->loginAs('admin');
        $audit = new \App\Service\AuditLogger();
        $audit->log('apple.event', actorUserId: $admin);
        $audit->log('banana.event', actorUserId: $admin);
        $this->get('/admin/audit?event=apple.event');
        // `banana.event` still appears in the filter <option> list (the
        // dropdown is built from distinct event names across the whole
        // table, NOT from the filtered query) — so the targeted assertion
        // is that the table body row for banana was filtered out. Each
        // row renders the event name wrapped in a <code> tag, so that
        // exact string is unique to row output and never to the dropdown.
        $this->assertResponseContains('<code>apple.event</code>');
        $this->assertResponseNotContains('<code>banana.event</code>');
    }
}
