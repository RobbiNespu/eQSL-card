<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Installer;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class InstallerTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.Templates'];

    public function testRunningMigrationsAndSeedingIsIdempotent(): void
    {
        $installer = new Installer();
        $installer->runMigrations(); // should be a no-op once already migrated by fixtures
        $installer->seedDefaultTemplate(CONFIG . 'seeds/default_system_template.json');
        $count1 = TableRegistry::getTableLocator()->get('Templates')->find()->count();

        $installer->seedDefaultTemplate(CONFIG . 'seeds/default_system_template.json');
        $count2 = TableRegistry::getTableLocator()->get('Templates')->find()->count();

        $this->assertSame($count1, $count2, 'seed must be idempotent');
        $this->assertGreaterThanOrEqual(1, $count1);
    }

    public function testCreateAdminUser(): void
    {
        $installer = new Installer();
        $user = $installer->createAdmin([
            'name' => 'Robbi', 'email' => 'r@x.com',
            'callsign' => 'AA1AA', 'password' => 'CorrectHorseBatteryStaple1',
        ]);
        $this->assertSame('admin', $user->role);
        $this->assertNotEmpty($user->password_hash);
    }
}
