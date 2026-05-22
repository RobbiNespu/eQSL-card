<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\NetMetrics;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class NetMetricsTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.NetSessions', 'app.Qsos'];

    private int $userId;

    public function setUp(): void
    {
        parent::setUp();
        $users = TableRegistry::getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'Test NCS', 'email' => 'ncs@test.com', 'role' => 'user',
            'callsign' => '9M2X', 'password_hash' => 'x',
        ], ['accessibleFields' => ['*' => true]]));
        $this->userId = (int)$u->id;
    }

    private function qsos()
    {
        return TableRegistry::getTableLocator()->get('Qsos');
    }

    private function metrics(): NetMetrics
    {
        return new NetMetrics($this->qsos());
    }

    private function seedCheckin(int $sessionId, string $call, ?string $grid, ?string $rst): void
    {
        $q = $this->qsos();
        $q->saveOrFail($q->newEntity([
            'user_id' => $this->userId, 'call_worked' => $call, 'qso_type' => 'net',
            'ncs_callsign' => '9M2X', 'net_title' => 'MARTS Daily Net',
            'net_session_id' => $sessionId, 'grid_square' => $grid, 'rst_received' => $rst,
            'qso_datetime_utc' => '2026-05-22 12:00:00',
        ], ['accessibleFields' => ['*' => true]]));
    }

    private function seedSession(int $ownerId, string $status = 'live', ?string $startedAt = null, ?string $endedAt = null): int
    {
        $ns = TableRegistry::getTableLocator()->get('NetSessions');
        $row = $ns->saveOrFail($ns->newEntity([
            'owner_id' => $ownerId, 'net_title' => 'MARTS Daily Net',
            'status' => $status, 'public_slug' => 'slug-' . uniqid(),
            'started_at' => $startedAt, 'ended_at' => $endedAt,
        ], ['accessibleFields' => ['*' => true]]));
        return (int)$row->id;
    }

    public function testSessionStatsCountsCheckins(): void
    {
        $this->seedCheckin(1, '9M2A', 'OJ02', '59');
        $this->seedCheckin(1, '9M2B', null, '57');
        $this->seedCheckin(1, '9M2A', 'OJ02', '55'); // dup callsign
        $stats = $this->metrics()->sessionStats(1);
        $this->assertSame(3, $stats['checkins']);
        $this->assertSame(2, $stats['unique']);
        $this->assertArrayHasKey('signal', $stats);
        $this->assertSame(1, $stats['signal'][9]);
    }

    public function testMapPointsHaveLatLon(): void
    {
        $this->seedCheckin(1, '9M2A', 'OJ02', '59');
        $this->seedCheckin(1, '9M2B', null, '57'); // no grid → excluded
        $points = $this->metrics()->mapPoints(1);
        $this->assertCount(1, $points);
        $this->assertArrayHasKey('lat', $points[0]);
        $this->assertArrayHasKey('lon', $points[0]);
        $this->assertSame('9M2A', $points[0]['callsign']);
    }

    public function testRetentionShape(): void
    {
        $r = $this->metrics()->retention($this->userId, 'MARTS Daily Net');
        $this->assertArrayHasKey('regulars', $r);
        $this->assertArrayHasKey('retention', $r);
        $this->assertArrayHasKey('sessions', $r);
    }

    public function testSessionStatsNewAndRate(): void
    {
        // Session A (older) — has 9M2A and 9M2B.
        $sidA = $this->seedSession($this->userId, 'ended', '2026-05-21 12:00:00', '2026-05-21 13:00:00');
        $this->seedCheckin($sidA, '9M2A', null, '59');
        $this->seedCheckin($sidA, '9M2B', null, '57');

        // Session B (newer, live) — has 9M2A (repeat) and 9M2C (new).
        // 60 min elapsed with 2 check-ins → rate = 2/60 ≈ 0.0, round to 1dp = 0.0.
        $sidB = $this->seedSession($this->userId, 'live', '2026-05-22 12:00:00', '2026-05-22 13:00:00');
        $this->seedCheckin($sidB, '9M2A', null, '59'); // repeat — NOT new
        $this->seedCheckin($sidB, '9M2C', null, '55'); // first ever — IS new

        $stats = $this->metrics()->sessionStats($sidB);

        $this->assertArrayHasKey('new', $stats, '"new" key must be present');
        $this->assertArrayHasKey('rate', $stats, '"rate" key must be present');

        // 9M2A appeared in session A → not new. 9M2C is first ever → new = 1.
        $this->assertSame(1, $stats['new'], '9M2C is the only truly new callsign');

        // 2 check-ins over 60 minutes → 2/60 = 0.0333… → round(,1) = 0.0
        $this->assertSame(0.0, $stats['rate'], 'rate should be 0.0 for 2 check-ins over 60 min');
    }

    public function testSessionStatsNewWhenNoSiblings(): void
    {
        // Single session with no prior sessions → all callsigns are "new".
        $sid = $this->seedSession($this->userId, 'live', '2026-05-22 12:00:00', null);
        $this->seedCheckin($sid, '9M2X', null, '59');
        $this->seedCheckin($sid, '9M2Y', null, '57');

        $stats = $this->metrics()->sessionStats($sid);
        $this->assertSame(2, $stats['new'], 'both callsigns are new when no sibling sessions exist');
    }

    public function testSessionStatsRateZeroWhenNoStartedAt(): void
    {
        // Session with no started_at (e.g. scheduled) → rate = 0.
        $sid = $this->seedSession($this->userId, 'scheduled', null, null);
        $this->seedCheckin($sid, '9M2Z', null, '59');

        $stats = $this->metrics()->sessionStats($sid);
        $this->assertSame(0.0, $stats['rate'], 'rate must be 0 when started_at is null');
    }
}
