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
            'user_id' => $userId,
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
            'band' => '20m',
            'mode' => 'SSB',
        ];
        $qsos->saveOrFail($qsos->newEntity($row));
        $second = $qsos->save($qsos->newEntity($row));
        $this->assertFalse($second, 'duplicate (user, call_worked, datetime, band) should be rejected');
    }

    public function testFkToUsersEnforced(): void
    {
        $qsos = TableRegistry::getTableLocator()->get('Qsos');
        $entity = $qsos->newEntity([
            'user_id' => 999999,
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => '2026-05-09 14:32:00',
        ]);
        $this->assertFalse($qsos->save($entity), 'orphan user_id should be rejected by buildRules');
    }
}
