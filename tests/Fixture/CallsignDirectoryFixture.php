<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CallsignDirectoryFixture extends TestFixture
{
    // Singular table name — Cake's inflector would otherwise look for
    // `callsign_directories`. The migration uses the singular form because
    // "directory" reads more naturally as a singular collection noun.
    public string $table = 'callsign_directory';
    public array $records = [];
}
