<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AdifParser;
use Cake\TestSuite\TestCase;

final class AdifParserTest extends TestCase
{
    private static function tag(string $name, string $value): string
    {
        return sprintf('<%s:%d>%s', strtoupper($name), strlen($value), $value);
    }

    /**
     * @param array<int, array<string, string>> $records
     */
    private function buildAdif(array $records, string $programIdHeader = ''): string
    {
        $out = "Test export\n<ADIF_VER:5>3.1.4\n";
        if ($programIdHeader !== '') {
            $out .= self::tag('PROGRAMID', $programIdHeader) . "\n";
        }
        $out .= "<EOH>\n";
        foreach ($records as $r) {
            foreach ($r as $k => $v) {
                $out .= self::tag($k, (string)$v) . ' ';
            }
            $out .= "<EOR>\n";
        }
        return $out;
    }

    public function testParsesBasicRecord(): void
    {
        $adif = $this->buildAdif([
            ['CALL' => 'W1AW', 'QSO_DATE' => '20260509', 'TIME_ON' => '143200',
             'BAND' => '20m', 'MODE' => 'SSB', 'FREQ' => '14.205', 'RST_SENT' => '59', 'RST_RCVD' => '59'],
        ]);
        $r = (new AdifParser())->parse($adif);
        $this->assertCount(1, $r['records']);
        $this->assertSame(0, $r['invalid']);
        $rec = $r['records'][0];
        $this->assertSame('W1AW', $rec['call_worked']);
        $this->assertSame('2026-05-09 14:32:00', $rec['qso_datetime_utc']);
        $this->assertSame('14.205', $rec['frequency_mhz']);
        $this->assertSame('20m', $rec['band']);
        $this->assertSame('SSB', $rec['mode']);
        $this->assertSame('59', $rec['rst_sent']);
        $this->assertSame('59', $rec['rst_received']);
    }

    public function testHandlesMultipleRecordsAndOptionalFields(): void
    {
        $adif = $this->buildAdif([
            ['CALL' => 'W1AW', 'QSO_DATE' => '20260509', 'TIME_ON' => '143200', 'BAND' => '20m', 'MODE' => 'SSB'],
            ['CALL' => 'K2DST', 'QSO_DATE' => '20260508', 'TIME_ON' => '1432', 'BAND' => '40m', 'MODE' => 'CW'],
        ]);
        $r = (new AdifParser())->parse($adif);
        $this->assertCount(2, $r['records']);
        $this->assertSame('2026-05-08 14:32:00', $r['records'][1]['qso_datetime_utc'], 'TIME_ON of length 4 should pad to 6');
        $this->assertNull($r['records'][1]['frequency_mhz']);
    }

    public function testIgnoresHeaderAndUnknownTags(): void
    {
        $adif = $this->buildAdif([
            ['CALL' => 'W1AW', 'QSO_DATE' => '20260509', 'TIME_ON' => '143200', 'CONTEST_ID' => 'IGNORED'],
        ], programIdHeader: 'N1MM Logger+');
        $r = (new AdifParser())->parse($adif);
        $this->assertCount(1, $r['records']);
        $this->assertArrayNotHasKey('contest_id', $r['records'][0]);
    }

    public function testRejectsRecordMissingCallOrDate(): void
    {
        $adif = "<EOH>\n" . self::tag('QSO_DATE', '20260509') . " <EOR>\n";
        $r = (new AdifParser())->parse($adif);
        $this->assertSame(0, count($r['records']));
        $this->assertSame(1, $r['invalid']);
    }

    public function testHandlesCommentAsNotesFallback(): void
    {
        $adif = $this->buildAdif([
            ['CALL' => 'VK3GX', 'QSO_DATE' => '20260507', 'TIME_ON' => '110000', 'COMMENT' => 'EU side QSO'],
        ]);
        $r = (new AdifParser())->parse($adif);
        $this->assertSame('EU side QSO', $r['records'][0]['notes']);
    }

    public function testHandlesGridSquareAndFt8RstNumbers(): void
    {
        $adif = $this->buildAdif([
            ['CALL' => 'EA8AB', 'QSO_DATE' => '20260505', 'TIME_ON' => '090000', 'MODE' => 'FT8',
             'BAND' => '17m', 'FREQ' => '18.1000', 'RST_SENT' => '-15', 'RST_RCVD' => '-18',
             'GRIDSQUARE' => 'IL18'],
        ]);
        $r = (new AdifParser())->parse($adif);
        $rec = $r['records'][0];
        $this->assertSame('IL18', $rec['grid_square']);
        $this->assertSame('-15', $rec['rst_sent']);
        $this->assertSame('-18', $rec['rst_received']);
    }
}
