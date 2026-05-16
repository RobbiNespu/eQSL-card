<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\Providers\LocalDirectoryProvider;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class LocalDirectoryProviderTest extends TestCase
{
    protected array $fixtures = ['app.CallsignDirectory'];

    public function testReturnsHitFromDirectory(): void
    {
        $table = TableRegistry::getTableLocator()->get('CallsignDirectory');
        $table->saveOrFail($table->newEntity([
            'callsign' => 'W1AW', 'name' => 'Hiram Maxim',
            'qth' => 'Newington', 'country' => 'United States',
            'grid_square' => 'FN31', 'license_class' => 'Extra',
            'source_label' => 'TestBatch', 'imported_at' => DateTime::now(),
        ], ['accessibleFields' => ['*' => true]]));

        $p = new LocalDirectoryProvider();
        $r = $p->lookup('W1AW');
        $this->assertNotNull($r);
        $this->assertSame('local', $r->source);
        $this->assertSame('Hiram Maxim', $r->name);
        $this->assertSame('FN31', $r->gridSquare);
        $this->assertSame('Extra', $r->licenseClass);
    }

    public function testReturnsNullWhenNotInDirectory(): void
    {
        $this->assertNull((new LocalDirectoryProvider())->lookup('XX0XX'));
    }

    public function testReturnsNullWhenRowHasNoUsefulFields(): void
    {
        // Edge: a CSV row with only a callsign + no name/qth/etc. shouldn't
        // surface as a "useful" hit; the orchestrator should fall through
        // to external providers.
        $table = TableRegistry::getTableLocator()->get('CallsignDirectory');
        $table->saveOrFail($table->newEntity([
            'callsign' => 'NOFIELDS',
            'imported_at' => DateTime::now(),
        ], ['accessibleFields' => ['*' => true]]));

        $this->assertNull((new LocalDirectoryProvider())->lookup('NOFIELDS'));
    }

    public function testSupportsValidCallsignsOnly(): void
    {
        $p = new LocalDirectoryProvider();
        $this->assertTrue($p->supports('W1AW'));
        $this->assertTrue($p->supports('9W2NSP/P'));
        $this->assertFalse($p->supports(''));
        $this->assertFalse($p->supports('!@#'));
    }
}
