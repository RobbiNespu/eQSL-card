<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\CallsignLookup;

use App\Service\CallsignLookup\DirectoryCsvImporter;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * CSV importer unit tests. Each test writes a CSV to a tmp file, runs the
 * importer, and asserts on both the summary it returned and the row state
 * in the DB afterwards.
 */
final class DirectoryCsvImporterTest extends TestCase
{
    protected array $fixtures = ['app.CallsignDirectory'];

    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/eqsl-csv-' . uniqid();
        mkdir($this->tmpDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    private function writeCsv(string $content): string
    {
        $path = $this->tmpDir . '/in.csv';
        file_put_contents($path, $content);
        return $path;
    }

    public function testBasicImport(): void
    {
        $csv = "callsign,name,qth,country\n"
             . "W1AW,Hiram Maxim,\"Newington, CT\",United States\n"
             . "9W2NSP,Robbi Nespu,Penang,Malaysia\n";
        $summary = (new DirectoryCsvImporter())->import($this->writeCsv($csv), 'TestBatch');
        $this->assertSame(2, $summary['imported']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(0, $summary['skipped']);

        $table = TableRegistry::getTableLocator()->get('CallsignDirectory');
        $row = $table->find()->where(['callsign' => 'W1AW'])->first();
        $this->assertSame('Hiram Maxim', $row->name);
        $this->assertSame('Newington, CT', $row->qth);
        $this->assertSame('United States', $row->country);
        $this->assertSame('TestBatch', $row->source_label);
    }

    public function testHeaderAliasesAndCaseInsensitive(): void
    {
        // Aliases: 'call' → callsign, 'operator' → name, 'location' → qth.
        // Mixed case + spaces in header should still match.
        $csv = "Call,Operator,Location,Country\n"
             . "VK2DEF,Sam Adams,Sydney,Australia\n";
        $summary = (new DirectoryCsvImporter())->import($this->writeCsv($csv));
        $this->assertSame(1, $summary['imported']);

        $row = TableRegistry::getTableLocator()->get('CallsignDirectory')
            ->find()->where(['callsign' => 'VK2DEF'])->first();
        $this->assertSame('Sam Adams', $row->name);
        $this->assertSame('Sydney', $row->qth);
        $this->assertSame('Australia', $row->country);
    }

    public function testUtf8BomStripped(): void
    {
        // Windows Excel exports start with EF BB BF.
        $csv = "\xEF\xBB\xBFcallsign,name\n9M4ABC,Aiman\n";
        $summary = (new DirectoryCsvImporter())->import($this->writeCsv($csv));
        $this->assertSame(1, $summary['imported']);
        $this->assertNotNull(
            TableRegistry::getTableLocator()->get('CallsignDirectory')
                ->find()->where(['callsign' => '9M4ABC'])->first(),
            'BOM-prefixed callsign column should still match'
        );
    }

    public function testCallsignNormalisedToUppercase(): void
    {
        $csv = "callsign,name\n9w2nsp,robbi\n";
        (new DirectoryCsvImporter())->import($this->writeCsv($csv));
        $row = TableRegistry::getTableLocator()->get('CallsignDirectory')
            ->find()->first();
        $this->assertSame('9W2NSP', $row->callsign);
    }

    public function testReImportUpdatesExistingButDoesNotClobberWithEmpty(): void
    {
        // First import: full row with name + qth.
        (new DirectoryCsvImporter())->import($this->writeCsv(
            "callsign,name,qth,country\nW1AW,Hiram Maxim,Newington,USA\n"
        ));
        // Second import: same callsign, only name changes; qth blank.
        $summary = (new DirectoryCsvImporter())->import($this->writeCsv(
            "callsign,name,qth\nW1AW,ARRL HQ,\n"
        ));
        $this->assertSame(0, $summary['imported']);
        $this->assertSame(1, $summary['updated']);

        $row = TableRegistry::getTableLocator()->get('CallsignDirectory')
            ->find()->where(['callsign' => 'W1AW'])->first();
        $this->assertSame('ARRL HQ', $row->name, 'name should be overwritten');
        $this->assertSame(
            'Newington', $row->qth,
            'qth should NOT be clobbered by empty value in the new CSV'
        );
        $this->assertSame('USA', $row->country, 'country should survive — was absent in 2nd CSV');
    }

    public function testRowWithoutCallsignSkipped(): void
    {
        $csv = "callsign,name\n,Anonymous\nW1AW,Hiram\n";
        $summary = (new DirectoryCsvImporter())->import($this->writeCsv($csv));
        $this->assertSame(1, $summary['imported']);
        $this->assertSame(1, $summary['skipped']);
    }

    public function testMissingCallsignColumnThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/callsign/i');
        (new DirectoryCsvImporter())->import($this->writeCsv("name,qth\nHiram,Newington\n"));
    }

    public function testEmptyFileReturnsEmptySummary(): void
    {
        $summary = (new DirectoryCsvImporter())->import($this->writeCsv(''));
        $this->assertSame(0, $summary['imported']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertNotEmpty($summary['errors']);
    }
}
