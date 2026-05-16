<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;

/**
 * ActivationsTable — M5 T12 unit coverage.
 *
 * Covers the public surface QsosController::quick() and the future
 * ActivationsController will depend on:
 *   - findActiveForUser() returns the open activation (ended_at IS NULL)
 *   - findActiveForUser() returns null when there's no open row
 *   - findRecentForUser() returns rows newest-first, scoped to the user
 *   - Maidenhead grid_square validator accepts 4/6-char codes, rejects junk
 */
final class ActivationsTableTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.Activations'];

    private function seedActivation(int $userId, array $extras = []): int
    {
        $tbl = $this->getTableLocator()->get('Activations');
        $row = $tbl->saveOrFail($tbl->newEntity(array_merge([
            'code' => 'POTA-K-1234',
            'name' => 'Test Park',
        ], $extras), ['accessibleFields' => ['*' => true]]));
        return (int)$row->id;
    }

    private function seedUser(string $email): int
    {
        $users = $this->getTableLocator()->get('Users');
        $u = $users->saveOrFail($users->newEntity([
            'name' => 'OP', 'email' => $email, 'role' => 'user',
            'callsign' => 'AA1AA', 'password_hash' => 'h',
        ], ['accessibleFields' => ['*' => true]]));
        return (int)$u->id;
    }

    public function testFindActiveForUserReturnsOpenRow(): void
    {
        $userId = $this->seedUser('a@x.com');
        $this->seedActivation($userId, [
            'user_id' => $userId,
            'started_at' => '2026-05-16 08:00:00',
            'ended_at'   => null,
        ]);

        $active = $this->getTableLocator()->get('Activations')->findActiveForUser($userId);
        $this->assertNotNull($active);
        $this->assertSame('POTA-K-1234', $active->code);
        $this->assertTrue($active->isActive());
    }

    public function testFindActiveForUserReturnsNullWhenAllClosed(): void
    {
        $userId = $this->seedUser('b@x.com');
        $this->seedActivation($userId, [
            'user_id' => $userId,
            'started_at' => '2026-05-15 08:00:00',
            'ended_at'   => '2026-05-15 12:00:00',
        ]);

        $active = $this->getTableLocator()->get('Activations')->findActiveForUser($userId);
        $this->assertNull($active);
    }

    public function testFindActiveForUserScopedToUser(): void
    {
        $userA = $this->seedUser('a@x.com');
        $userB = $this->seedUser('b@x.com');
        $this->seedActivation($userA, [
            'user_id' => $userA,
            'started_at' => '2026-05-16 08:00:00',
            'ended_at'   => null,
        ]);

        $activeForB = $this->getTableLocator()->get('Activations')->findActiveForUser($userB);
        $this->assertNull($activeForB, 'Activation owned by user A must not surface for user B');
    }

    public function testFindRecentForUserOrdersNewestFirst(): void
    {
        $userId = $this->seedUser('a@x.com');
        $oldId = $this->seedActivation($userId, [
            'user_id' => $userId,
            'code' => 'SOTA-9M2/PR-001',
            'started_at' => '2026-05-10 08:00:00',
            'ended_at' => '2026-05-10 12:00:00',
        ]);
        $newId = $this->seedActivation($userId, [
            'user_id' => $userId,
            'code' => 'POTA-K-1234',
            'started_at' => '2026-05-16 08:00:00',
        ]);

        $rows = $this->getTableLocator()->get('Activations')->findRecentForUser($userId)->all()->toList();
        $this->assertCount(2, $rows);
        $this->assertSame($newId, (int)$rows[0]->id, 'Newest first');
        $this->assertSame($oldId, (int)$rows[1]->id);
    }

    public function testGridSquareValidatorAcceptsMaidenhead(): void
    {
        $tbl = $this->getTableLocator()->get('Activations');
        foreach (['OJ02', 'OJ02wx', 'AA00', 'RR99xx'] as $grid) {
            $errors = $tbl->newEntity([
                'code' => 'X', 'name' => 'Y', 'grid_square' => $grid,
                'started_at' => '2026-05-16 08:00:00',
            ], ['accessibleFields' => ['*' => true]])->getErrors();
            $this->assertArrayNotHasKey('grid_square', $errors, "Valid grid '$grid' should pass");
        }
    }

    public function testGridSquareValidatorRejectsJunk(): void
    {
        $tbl = $this->getTableLocator()->get('Activations');
        foreach (['XX99', 'OJ', '12345', 'OJ02wxyz'] as $grid) {
            $errors = $tbl->newEntity([
                'code' => 'X', 'name' => 'Y', 'grid_square' => $grid,
                'started_at' => '2026-05-16 08:00:00',
            ], ['accessibleFields' => ['*' => true]])->getErrors();
            $this->assertArrayHasKey('grid_square', $errors, "Invalid grid '$grid' should fail");
        }
    }

    public function testGridSquareValidatorAllowsEmpty(): void
    {
        $tbl = $this->getTableLocator()->get('Activations');
        $errors = $tbl->newEntity([
            'code' => 'X', 'name' => 'Y', 'grid_square' => '',
            'started_at' => '2026-05-16 08:00:00',
        ], ['accessibleFields' => ['*' => true]])->getErrors();
        $this->assertArrayNotHasKey('grid_square', $errors);
    }

    public function testQsosBelongsToActivationsAssociation(): void
    {
        $qsos = $this->getTableLocator()->get('Qsos');
        $this->assertNotNull($qsos->getAssociation('Activations'), 'Qsos->belongsTo(Activations) must be wired');
    }
}
