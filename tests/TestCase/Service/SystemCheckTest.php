<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\SystemCheck;
use Cake\TestSuite\TestCase;

final class SystemCheckTest extends TestCase
{
    public function testReportsAllRequirements(): void
    {
        $check = new SystemCheck();
        $report = $check->run();
        $this->assertArrayHasKey('php_version', $report);
        $this->assertArrayHasKey('gd', $report);
        $this->assertArrayHasKey('pdo_mysql', $report);
        $this->assertArrayHasKey('writable_config', $report);
        $this->assertArrayHasKey('writable_files', $report);
        foreach ($report as $row) {
            $this->assertArrayHasKey('ok', $row);
            $this->assertArrayHasKey('detail', $row);
        }
    }

    public function testPassesOnCurrentEnvironment(): void
    {
        $report = (new SystemCheck())->run();
        $this->assertTrue($report['php_version']['ok']);
        $this->assertTrue($report['gd']['ok']);
    }
}
