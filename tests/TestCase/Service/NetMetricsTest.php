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
}
