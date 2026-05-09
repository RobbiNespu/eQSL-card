<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Installer;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class InstallerSeedRegressionTest extends TestCase
{
    protected array $fixtures = ['app.Templates'];

    public function testM1SeedFileExists(): void
    {
        $this->assertFileExists(CONFIG . 'seeds/default_system_template.json');
    }

    public function testSeededTemplateHasCorrectFlags(): void
    {
        $installer = new Installer();
        $installer->seedDefaultTemplate(CONFIG . 'seeds/default_system_template.json');

        $row = TableRegistry::getTableLocator()->get('Templates')
            ->find()
            ->where(['is_system' => true])
            ->firstOrFail();

        $this->assertTrue((bool)$row->is_system, 'is_system must be true');
        $this->assertTrue((bool)$row->is_public, 'is_public must be true so guests can pick it');
        $this->assertTrue((bool)$row->is_approved, 'is_approved must be true (no admin gate for system templates)');
        $this->assertNull($row->user_id, 'system template has no owner');
        $this->assertNotEmpty($row->layout_json);

        $layout = json_decode($row->layout_json, true);
        $this->assertArrayHasKey('fields', $layout);
        $this->assertGreaterThan(0, count($layout['fields']));
    }

    public function testSeedIsIdempotent(): void
    {
        $installer = new Installer();
        $installer->seedDefaultTemplate(CONFIG . 'seeds/default_system_template.json');
        $installer->seedDefaultTemplate(CONFIG . 'seeds/default_system_template.json');

        $count = TableRegistry::getTableLocator()->get('Templates')
            ->find()
            ->where(['is_system' => true])
            ->count();
        $this->assertSame(1, $count, 'Seeding twice must not produce a duplicate');
    }
}
