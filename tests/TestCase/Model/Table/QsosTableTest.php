<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class QsosTableTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.Qsos'];

    private function seedUser(): int
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $user = $users->newEntity([
            'name' => 'OP', 'email' => 'op@x.com', 'role' => 'user',
            'callsign' => 'AA1AA', 'password' => 'CorrectHorseBatteryStaple1',
        ]);
        $users->saveOrFail($user);
        return $user->id;
    }

    public function testCallWorkedRequired(): void
    {
        $qsos = TableRegistry::getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity([
            'user_id' => $this->seedUser(),
            'call_worked' => '',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
        ]);
        $this->assertNotEmpty($entity->getErrors()['call_worked'] ?? []);
    }

    public function testDuplicateImportBlocked(): void
    {
        $qsos = TableRegistry::getTableLocator()->get('Qsos');
        $userId = $this->seedUser();
        $row = [
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
            'band' => '20m',
            'mode' => 'SSB',
        ];
        $first = $qsos->newEntity($row);
        $first->user_id = $userId;
        $qsos->saveOrFail($first);
        $second = $qsos->newEntity($row);
        $second->user_id = $userId;
        $this->assertFalse($qsos->save($second), 'duplicate (user, call_worked, datetime, band) should be rejected');
    }

    public function testFkToUsersEnforced(): void
    {
        $qsos = TableRegistry::getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity([
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
        ]);
        $entity->user_id = 999999;
        $this->assertFalse($qsos->save($entity), 'orphan user_id should be rejected by buildRules');
    }

    public function testCallsignNormalizedToUppercase(): void
    {
        $qsos = TableRegistry::getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity([
            'user_id' => $this->seedUser(),
            'call_worked' => '  w1aw  ',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
        ]);
        $this->assertSame('W1AW', $entity->call_worked);
    }

    public function testGridSquareNormalized(): void
    {
        $qsos = TableRegistry::getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity([
            'user_id' => $this->seedUser(),
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
            'grid_square' => 'fn31pr',
        ]);
        $this->assertSame('FN31pr', $entity->grid_square);
    }

    public function testUserIdIsNotMassAssignable(): void
    {
        $qsos = TableRegistry::getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity([
            'user_id' => 999, // attacker tries to inject
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
        ]);
        $this->assertNull($entity->user_id, 'user_id must NOT be mass-assignable');
    }
}
