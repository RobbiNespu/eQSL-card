<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class NetSessionsTableTest extends TestCase
{
    protected array $fixtures = ['app.NetSessions', 'app.NetSessionLoggers', 'app.Users'];

    private function table(): \App\Model\Table\NetSessionsTable
    {
        return TableRegistry::getTableLocator()->get('NetSessions');
    }

    public function testTitleRequired(): void
    {
        $t = $this->table();
        $e = $t->newEntity(['net_title' => '']);
        $this->assertNotEmpty($e->getError('net_title'));
    }

    public function testOwnerIsLogger(): void
    {
        $this->assertTrue($this->table()->isLogger(1, 1));
    }

    public function testCoLoggerIsLogger(): void
    {
        $this->assertTrue($this->table()->isLogger(1, 2));
    }

    public function testStrangerIsNotLogger(): void
    {
        $this->assertFalse($this->table()->isLogger(1, 999));
    }

    public function testFindUpcomingReturnsScheduledOnly(): void
    {
        $rows = $this->table()->findUpcomingForUser(1)->all()->toList();
        $this->assertSame([], $rows);
    }
}
