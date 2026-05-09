<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class AuditLogsTableTest extends TestCase
{
    protected array $fixtures = ['app.AuditLogs'];

    public function testInsertWithDirectSet(): void
    {
        $logs = TableRegistry::getTableLocator()->get('AuditLogs');
        $entity = $logs->newEmptyEntity();
        $entity->set('event', 'card.generated', ['guard' => false]);
        $entity->set('target_type', 'Cards', ['guard' => false]);
        $entity->set('target_id', 42, ['guard' => false]);
        $logs->saveOrFail($entity);
        $this->assertNotNull($entity->id);
    }

    public function testEventRequired(): void
    {
        $logs = TableRegistry::getTableLocator()->get('AuditLogs');
        $entity = $logs->newEmptyEntity();
        $entity->set('event', '', ['guard' => false]);
        $errors = $entity->getErrors();
        $entity = $logs->patchEntity($entity, []);
        // Event empty → save fails
        $this->assertFalse($logs->save($entity));
    }

    public function testMassAssignmentRejected(): void
    {
        $logs = TableRegistry::getTableLocator()->get('AuditLogs');
        $entity = $logs->newEntity(['event' => 'malicious']);
        $this->assertNull($entity->event, 'event must NOT be mass-assignable');
    }
}
