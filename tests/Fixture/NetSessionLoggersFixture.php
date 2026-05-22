<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NetSessionLoggersFixture extends TestFixture
{
    public array $records = [
        ['id' => 1, 'net_session_id' => 1, 'user_id' => 2, 'added_via' => 'owner', 'created_at' => '2026-05-22 12:01:00'],
    ];
}
