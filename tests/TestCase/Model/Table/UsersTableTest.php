<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class UsersTableTest extends TestCase
{
    protected array $fixtures = ['app.Users'];

    public function testCallsignRequired(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $entity = $users->newEntity([
            'name' => 'Robbi',
            'email' => 'r@example.com',
            'password_hash' => 'x',
            'role' => 'admin',
            'callsign' => '',
        ]);
        $this->assertNotEmpty($entity->getErrors()['callsign'] ?? []);
    }

    public function testEmailMustBeUnique(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $users->saveOrFail($users->newEntity([
            'name' => 'A', 'email' => 'a@x.com', 'password_hash' => 'h',
            'role' => 'user', 'callsign' => 'AA1AA',
        ]));
        $dupe = $users->newEntity([
            'name' => 'B', 'email' => 'a@x.com', 'password_hash' => 'h',
            'role' => 'user', 'callsign' => 'BB1BB',
        ]);
        $this->assertNotTrue($users->save($dupe));
    }
}
