<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\CallsignLookup\Providers;

use App\Service\CallsignLookup\Providers\RadioIdDatabaseDumpProvider;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * Provider tests: seed a handful of rows into radioid_registry, then
 * call lookup() and assert the returned CallsignLookupResult shape.
 * Zero network — the whole point of this provider's redesign was to
 * cut that dependency.
 */
final class RadioIdDatabaseDumpProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $conn = ConnectionManager::get('default');
        $conn->execute('DELETE FROM radioid_registry');
        $now = '2026-05-16 00:00:00';
        $conn->execute(
            'INSERT INTO radioid_registry (radio_id, callsign, first_name, last_name, city, state, country, imported_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [5020558, '9W2NSP', 'Robbi', 'Nespu', 'Johor', 'West Malaysia', 'Malaysia', $now]
        );
        $conn->execute(
            'INSERT INTO radioid_registry (radio_id, callsign, first_name, last_name, city, state, country, imported_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [1023007, 'VA3BOC', 'Hans Juergen', '', 'Cornwall', 'Ontario', 'Canada', $now]
        );
    }

    public function testLookupReturnsRowFromLocalMirror(): void
    {
        $p = new RadioIdDatabaseDumpProvider();
        $r = $p->lookup('9W2NSP');

        $this->assertNotNull($r);
        $this->assertSame('9W2NSP', $r->callsign);
        $this->assertSame('radioid_database_dump', $r->source);
        $this->assertSame('Robbi Nespu', $r->name);
        $this->assertSame('Johor, West Malaysia', $r->qth);
        $this->assertSame('Malaysia', $r->country);
        $this->assertNull($r->gridSquare);
        $this->assertStringContainsString('radioid.net/database/view?id=5020558', (string)$r->sourceUrl);
    }

    public function testLookupReturnsNullForUnknownCallsign(): void
    {
        $this->assertNull((new RadioIdDatabaseDumpProvider())->lookup('NOSUCH'));
    }

    public function testQthOmitsCommaWhenStateMissing(): void
    {
        ConnectionManager::get('default')->execute(
            'INSERT INTO radioid_registry (radio_id, callsign, first_name, last_name, city, state, country, imported_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [42, 'CITYONLY', 'A', 'B', 'JustACity', '', 'Somewhere', '2026-05-16 00:00:00']
        );
        $r = (new RadioIdDatabaseDumpProvider())->lookup('CITYONLY');
        $this->assertSame('JustACity', $r->qth);
    }

    public function testSupportsAcceptsValidShapeRejectsOthers(): void
    {
        $p = new RadioIdDatabaseDumpProvider();
        $this->assertTrue($p->supports('9W2NSP'));
        $this->assertTrue($p->supports('W1AW'));
        $this->assertTrue($p->supports('VA3/W1AW'));
        $this->assertFalse($p->supports('AB'));            // too short
        $this->assertFalse($p->supports('THIS_IS_TOO_LONG_FOR_A_CALL'));
        $this->assertFalse($p->supports('NO SPACES'));
    }

    public function testCodeAndLabel(): void
    {
        $p = new RadioIdDatabaseDumpProvider();
        $this->assertSame('radioid_database_dump', $p->code());
        $this->assertSame('RadioID registry (local lookup cache)', $p->label());
    }
}
