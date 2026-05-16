<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\CallsignLookup;

use App\Service\CallsignLookup\RadioIdRegistryImporter;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * Importer tests cover only the local-file half of the pipeline
 * (`import()`) — exercising `download()` would hit the live RadioID
 * endpoint, which makes the suite slow and externally-flaky. The
 * download() helper is a thin wrapper around stream_copy_to_stream
 * with a size cap; the parsing/insertion logic that import() owns is
 * where bugs would actually hide.
 */
final class RadioIdRegistryImporterTest extends TestCase
{
    protected array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Migrator already created radioid_registry by the time we
        // arrive; just make sure it's empty between tests.
        ConnectionManager::get('default')->execute('DELETE FROM radioid_registry');
    }

    public function testImportParsesValidCsvAndInsertsRows(): void
    {
        $csv = "RADIO_ID,CALLSIGN,FIRST_NAME,LAST_NAME,CITY,STATE,COUNTRY\n"
             . "1023007,VA3BOC,Hans Juergen,Smith,Cornwall,Ontario,Canada\n"
             . "5020558,9W2NSP,Robbi,Nespu,Johor,West Malaysia,Malaysia\n"
             . "1234567,W1AW,Hiram Percy,Maxim,Newington,Connecticut,United States\n";
        $path = $this->writeTempCsv($csv);

        $imported = (new RadioIdRegistryImporter())->import($path);
        @unlink($path);

        $this->assertSame(3, $imported);

        $conn = ConnectionManager::get('default');
        $rows = $conn->execute('SELECT callsign, first_name, last_name, city, state, country FROM radioid_registry ORDER BY callsign')->fetchAll('assoc');
        $this->assertCount(3, $rows);
        $this->assertSame('9W2NSP', $rows[0]['callsign']);
        $this->assertSame('Robbi', $rows[0]['first_name']);
        $this->assertSame('Johor', $rows[0]['city']);
        $this->assertSame('Malaysia', $rows[0]['country']);
        $this->assertSame('VA3BOC', $rows[1]['callsign']);
        $this->assertSame('W1AW', $rows[2]['callsign']);
    }

    public function testImportTruncatesBeforeReplacing(): void
    {
        // Seed a row that shouldn't survive a second import.
        ConnectionManager::get('default')->execute(
            'INSERT INTO radioid_registry (callsign, imported_at) VALUES (?, ?)',
            ['STALE', '2020-01-01 00:00:00']
        );

        $csv = "RADIO_ID,CALLSIGN,FIRST_NAME,LAST_NAME,CITY,STATE,COUNTRY\n"
             . "1,FRESH,New,Row,X,Y,Z\n";
        $path = $this->writeTempCsv($csv);
        (new RadioIdRegistryImporter())->import($path);
        @unlink($path);

        $count = ConnectionManager::get('default')->execute('SELECT COUNT(*) AS c FROM radioid_registry')->fetch('assoc');
        $this->assertSame(1, (int)$count['c']);
        $row = ConnectionManager::get('default')->execute('SELECT callsign FROM radioid_registry')->fetch('assoc');
        $this->assertSame('FRESH', $row['callsign']);
    }

    public function testImportSkipsMalformedRows(): void
    {
        $csv = "RADIO_ID,CALLSIGN,FIRST_NAME,LAST_NAME,CITY,STATE,COUNTRY\n"
             . "1,VALID,Name,Last,City,State,Country\n"
             . "2,,EmptyCallsign,Last,City,State,Country\n"      // empty callsign — skipped
             . "3,SHORT,Only,Three\n"                              // too few columns — skipped
             . "4,VALID2,Another,Op,City,State,Country\n";
        $path = $this->writeTempCsv($csv);

        $imported = (new RadioIdRegistryImporter())->import($path);
        @unlink($path);

        $this->assertSame(2, $imported);
    }

    public function testImportRejectsUnexpectedHeader(): void
    {
        $csv = "FOO,BAR,BAZ\n1,2,3\n";
        $path = $this->writeTempCsv($csv);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unexpected CSV header/');
        try {
            (new RadioIdRegistryImporter())->import($path);
        } finally {
            @unlink($path);
        }
    }

    public function testImportUpsertsRepeatedCallsignsWithLastWins(): void
    {
        // VE3ZXN appears twice in the live upstream — once on radio_id
        // 1023020 and once on 1023021 (same operator, two DMR registrations).
        // The UPSERT path lets the second row replace the first, so the
        // final cache holds one VE3ZXN row carrying the LATER radio_id.
        // The unique-callsign cardinality of the cache reflects that.
        $csv = "RADIO_ID,CALLSIGN,FIRST_NAME,LAST_NAME,CITY,STATE,COUNTRY\n"
             . "1023020,VE3ZXN,Denis,,Bradford,Ontario,Canada\n"
             . "1023021,VE3ZXN,Denis Renamed,Surname,Bradford,Ontario,Canada\n"
             . "1106003,KH7Y,Frederic K,,Pine Grove,California,United States\n";
        $path = $this->writeTempCsv($csv);

        $cacheSize = (new RadioIdRegistryImporter())->import($path);
        @unlink($path);

        $this->assertSame(2, $cacheSize, 'Cache should hold two distinct callsigns after upsert.');
        $row = \Cake\Datasource\ConnectionManager::get('default')
            ->execute('SELECT radio_id, first_name, last_name FROM radioid_registry WHERE callsign = ?', ['VE3ZXN'])
            ->fetch('assoc');
        // Last-occurrence wins → radio_id 1023021 and the updated names.
        $this->assertSame(1023021, (int)$row['radio_id']);
        $this->assertSame('Denis Renamed', $row['first_name']);
        $this->assertSame('Surname', $row['last_name']);
    }

    public function testRefreshStreamsLineByLineFromLocalFileStream(): void
    {
        // Exercise the streaming path without hitting the network.
        // refresh() opens an HTTP stream and hands it to a private
        // importFromStream; we use reflection to call that private
        // method directly with a local file:// stream — same code
        // path, no upstream dependency.
        $csv = "RADIO_ID,CALLSIGN,FIRST_NAME,LAST_NAME,CITY,STATE,COUNTRY\n"
             . "1,VA3BOC,Hans,Smith,Cornwall,Ontario,Canada\n"
             . "2,9W2NSP,Robbi,Nespu,Johor,West Malaysia,Malaysia\n"
             . "3,VA3BOC,Hans Renamed,Smith,Cornwall,Ontario,Canada\n"; // last-wins dupe
        $path = $this->writeTempCsv($csv);

        $progressLines = [];
        $emit = function (string $msg) use (&$progressLines): void {
            $progressLines[] = $msg;
        };

        $importer = new RadioIdRegistryImporter();
        $ref = new \ReflectionMethod($importer, 'importFromStream');
        $ref->setAccessible(true);

        $stream = fopen($path, 'r');
        try {
            $cacheSize = $ref->invoke($importer, $stream, $emit);
        } finally {
            fclose($stream);
            @unlink($path);
        }

        // Two unique callsigns (VA3BOC was upserted, last row wins).
        $this->assertSame(2, $cacheSize);
        $row = \Cake\Datasource\ConnectionManager::get('default')
            ->execute('SELECT first_name FROM radioid_registry WHERE callsign = ?', ['VA3BOC'])
            ->fetch('assoc');
        $this->assertSame('Hans Renamed', $row['first_name']);

        // Progress emitter should fire at least the "Stream complete"
        // summary on a small file; for files larger than BATCH_SIZE it
        // would also fire mid-stream.
        $tail = end($progressLines);
        $this->assertStringContainsString('Stream complete', (string)$tail);
        $this->assertStringContainsString('cache now holds 2 unique callsigns', (string)$tail);
    }

    public function testImportRejectsMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        (new RadioIdRegistryImporter())->import('/no/such/file.csv');
    }

    public function testImportUppercasesCallsign(): void
    {
        $csv = "RADIO_ID,CALLSIGN,FIRST_NAME,LAST_NAME,CITY,STATE,COUNTRY\n"
             . "1,9w2nsp,Robbi,Nespu,Johor,WM,Malaysia\n";
        $path = $this->writeTempCsv($csv);
        (new RadioIdRegistryImporter())->import($path);
        @unlink($path);

        $row = ConnectionManager::get('default')->execute('SELECT callsign FROM radioid_registry')->fetch('assoc');
        $this->assertSame('9W2NSP', $row['callsign']);
    }

    private function writeTempCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'radioid_test_');
        file_put_contents($path, $content);
        return $path;
    }
}
