<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NetSessionsFixture extends TestFixture
{
    public array $records = [
        [
            'id' => 1, 'owner_id' => 1, 'net_title' => 'MARTS Daily Net',
            'net_organisation' => 'MARTS', 'frequency_mhz' => '7.110', 'band' => '40m',
            'mode' => 'SSB', 'status' => 'live', 'public_slug' => 'live-net-slug',
            'is_public' => true, 'logger_token' => 'logtok1',
            'started_at' => '2026-05-22 12:00:00', 'ended_at' => null, 'notes' => null,
            'created_at' => '2026-05-22 11:59:00', 'updated_at' => '2026-05-22 12:00:00',
        ],
        [
            'id' => 2, 'owner_id' => 1, 'net_title' => 'MARTS Daily Net',
            'net_organisation' => 'MARTS', 'frequency_mhz' => '7.110', 'band' => '40m',
            'mode' => 'SSB', 'status' => 'ended', 'public_slug' => 'ended-net-slug',
            'is_public' => true, 'logger_token' => null,
            'started_at' => '2026-05-21 12:00:00', 'ended_at' => '2026-05-21 13:00:00',
            'notes' => null, 'created_at' => '2026-05-21 11:59:00', 'updated_at' => '2026-05-21 13:00:00',
        ],
    ];
}
