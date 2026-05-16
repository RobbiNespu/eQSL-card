<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AppSettings;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for the AppSettings runtime loader (M4-T18).
 *
 * Covers:
 *  - JSON round-trip (set + get for scalars and arrays).
 *  - Default fallback when key missing.
 *  - setMany() multi-key persistence.
 *  - Cache invalidation: an out-of-band write is hidden until clear().
 */
final class AppSettingsTest extends TestCase
{
    protected array $fixtures = ['app.AppSettings'];

    protected function setUp(): void
    {
        parent::setUp();
        // Each test starts with a cold cache so the static singleton from a
        // prior test cannot leak in.
        (new AppSettings())->clear();
    }

    public function testSetAndGet(): void
    {
        $s = new AppSettings();
        $s->set('site_name', 'Bugcatcher');
        $this->assertSame('Bugcatcher', $s->get('site_name'));
    }

    public function testGetWithDefault(): void
    {
        $s = new AppSettings();
        $this->assertSame('default', $s->get('missing', 'default'));
    }

    public function testJsonValueRoundTrip(): void
    {
        $s = new AppSettings();
        $s->set('numbers', [1, 2, 3]);
        $this->assertSame([1, 2, 3], $s->get('numbers'));
    }

    public function testSetManyAtomically(): void
    {
        $s = new AppSettings();
        $s->setMany(['a' => 'one', 'b' => 'two']);
        $this->assertSame('one', $s->get('a'));
        $this->assertSame('two', $s->get('b'));
    }

    public function testClearInvalidatesCache(): void
    {
        $s = new AppSettings();
        $s->set('k', 'first');
        $this->assertSame('first', $s->get('k'));
        // Mutate via raw table to bypass the service's cache invalidation.
        $table = $this->getTableLocator()->get('AppSettings');
        $row = $table->find()->where(['key' => 'k'])->first();
        $row->set('value', json_encode('second'), ['guard' => false]);
        $table->saveOrFail($row);
        // Without clear(), cache still returns 'first'.
        $this->assertSame('first', $s->get('k'));
        $s->clear();
        $this->assertSame('second', $s->get('k'));
    }
}
