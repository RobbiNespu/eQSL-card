<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\CsvParser;
use Cake\TestSuite\TestCase;

final class CsvParserTest extends TestCase
{
    public function testParsesCommaDelimitedWithFullDatetime(): void
    {
        $csv = "call,qso_datetime_utc,band,mode,rst_sent,rst_rcvd\n"
             . "W1AW,2026-05-09 14:32:00,20m,SSB,59,59\n"
             . "K2DST,2026-05-08 14:32:00,40m,CW,599,599\n";
        $r = (new CsvParser())->parse($csv);
        $this->assertCount(2, $r['records']);
        $this->assertSame('W1AW', $r['records'][0]['call_worked']);
        $this->assertSame('2026-05-09 14:32:00', $r['records'][0]['qso_datetime_utc']);
    }

    public function testStripsBomAndDetectsSemicolonDelimiter(): void
    {
        $csv = "\xEF\xBB\xBFCall;Date;Time;Band;Mode\n"
             . "W1AW;2026-05-09;1432;20m;SSB\n";
        $r = (new CsvParser())->parse($csv);
        $this->assertCount(1, $r['records']);
        $this->assertSame('2026-05-09 14:32:00', $r['records'][0]['qso_datetime_utc']);
    }

    public function testHandlesQuotedFieldsWithCommas(): void
    {
        $csv = "call,qso_datetime_utc,notes\n"
             . "\"W1AW\",2026-05-09 14:32:00,\"Hello, this is a comma in notes\"\n";
        $r = (new CsvParser())->parse($csv);
        $this->assertSame('Hello, this is a comma in notes', $r['records'][0]['notes']);
    }

    public function testTabDelimited(): void
    {
        $csv = "Call\tQSO_Date\tTime_On\tBand\n"
             . "W1AW\t20260509\t143200\t20m\n";
        $r = (new CsvParser())->parse($csv);
        $this->assertSame('2026-05-09 14:32:00', $r['records'][0]['qso_datetime_utc']);
        $this->assertSame('20m', $r['records'][0]['band']);
    }

    public function testRejectsRowsMissingCallOrDatetime(): void
    {
        $csv = "call,qso_datetime_utc,band\n"
             . ",2026-05-09 14:32:00,20m\n"   // missing call
             . "W1AW,,40m\n"                   // missing datetime
             . "K2DST,2026-05-08 14:32:00,15m\n";
        $r = (new CsvParser())->parse($csv);
        $this->assertCount(1, $r['records']);
        $this->assertSame(2, $r['invalid']);
        $this->assertSame('K2DST', $r['records'][0]['call_worked']);
    }

    public function testIgnoresUnknownColumns(): void
    {
        $csv = "call,qso_datetime_utc,internal_id,satellite,band\n"
             . "W1AW,2026-05-09 14:32:00,42,FO-29,20m\n";
        $r = (new CsvParser())->parse($csv);
        $rec = $r['records'][0];
        $this->assertSame('W1AW', $rec['call_worked']);
        $this->assertSame('20m', $rec['band']);
        $this->assertArrayNotHasKey('satellite', $rec);
        $this->assertArrayNotHasKey('internal_id', $rec);
    }

    public function testEmptyFileReturnsErrorNotCrash(): void
    {
        $r = (new CsvParser())->parse('');
        $this->assertCount(0, $r['records']);
        $this->assertSame(0, $r['invalid']);
        $this->assertNotEmpty($r['errors']);
    }
}
