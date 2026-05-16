<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AuditLogger;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class AuditLoggerTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.GuestVisits', 'app.AuditLogs'];

    private function seedUser(int $id): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $users->saveOrFail($users->newEntity([
            'id' => $id,
            'name' => 'Admin',
            'callsign' => 'AA' . $id,
            'email' => "u{$id}@x.com",
            'role' => 'admin',
            'password_hash' => 'x',
        ], ['accessibleFields' => ['*' => true]]));
    }

    private function seedGuestVisit(int $id): void
    {
        $gv = TableRegistry::getTableLocator()->get('GuestVisits');
        $gv->saveOrFail($gv->newEntity([
            'id' => $id,
            'session_token' => str_pad((string)$id, 43, 'a'),
            'ip_hash' => str_repeat('0', 64),
            'user_agent_hash' => str_repeat('0', 64),
            'last_seen_at' => date('Y-m-d H:i:s'),
        ], ['accessibleFields' => ['*' => true]]));
    }

    public function testLogWithMinimalArgs(): void
    {
        $id = (new AuditLogger())->log('card.generated');
        $this->assertGreaterThan(0, $id);
        $row = TableRegistry::getTableLocator()->get('AuditLogs')->get($id);
        $this->assertSame('card.generated', $row->event);
    }

    public function testLogWithFullArgs(): void
    {
        $this->seedUser(1);
        $id = (new AuditLogger())->log(
            event: 'template.approved',
            actorUserId: 1,
            target: ['type' => 'Templates', 'id' => 99],
            metadata: ['reviewer_email' => 'admin@x.com'],
        );
        $row = TableRegistry::getTableLocator()->get('AuditLogs')->get($id);
        $this->assertSame('template.approved', $row->event);
        $this->assertSame(1, $row->actor_user_id);
        $this->assertSame('Templates', $row->target_type);
        $this->assertSame(99, $row->target_id);
        $meta = json_decode((string)$row->metadata_json, true);
        $this->assertSame('admin@x.com', $meta['reviewer_email']);
    }

    public function testGuestActor(): void
    {
        $this->seedGuestVisit(5);
        $id = (new AuditLogger())->log('card.generated', actorGuestVisitId: 5);
        $row = TableRegistry::getTableLocator()->get('AuditLogs')->get($id);
        $this->assertSame(5, $row->actor_guest_visit_id);
        $this->assertNull($row->actor_user_id);
    }
}
