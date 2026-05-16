<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AdifExporter;
use Cake\TestSuite\TestCase;

/**
 * AdifExporter — pure-function unit tests. No DB / no Cake bootstrap.
 *
 * Asserts the structural properties of the output rather than the exact
 * byte stream — ADIF allows interleaved whitespace and the spec doesn't
 * mandate field order within a record. We check:
 *
 *  - Header block has the required version + program markers + EOH
 *  - Each record terminates with EOR
 *  - Length-prefixed tags use byte length (`<CALL:5>W1AW ` → 5)
 *  - Empty/null fields don't emit empty tags
 *  - Activator MY_* fields derive from activation metadata
 *  - POTA/SOTA/IOTA prefix detection on activation.code
 */
final class AdifExporterTest extends TestCase
{
    private function fakeActivation(array $overrides = []): object
    {
        return (object)array_merge([
            'code' => 'POTA-K-1234',
            'name' => 'Test Park',
            'grid_square' => 'OJ02wx',
            'started_at' => new \DateTimeImmutable('2026-05-16 08:00:00', new \DateTimeZone('UTC')),
            'ended_at' => new \DateTimeImmutable('2026-05-16 12:00:00', new \DateTimeZone('UTC')),
            'notes' => 'Sunday morning',
        ], $overrides);
    }

    private function fakeQso(array $overrides = []): object
    {
        return (object)array_merge([
            'call_worked' => 'W1AW',
            'qso_datetime_utc' => new \DateTimeImmutable('2026-05-16 09:15:30', new \DateTimeZone('UTC')),
            'band' => '20m',
            'mode' => 'SSB',
            'frequency_mhz' => '14.20000',
            'rst_sent' => '59',
            'rst_received' => '59',
            'grid_square' => 'FN31',
            'operator_name' => 'ARRL HQ',
            'operator_qth' => 'Newington, CT',
            'notes' => '',
        ], $overrides);
    }

    public function testHeaderHasRequiredMarkers(): void
    {
        $out = (new AdifExporter())->export($this->fakeActivation(), [], '9W2NSP');
        $this->assertStringContainsString('<ADIF_VER:5>3.1.4', $out);
        $this->assertStringContainsString('<PROGRAMID:9>eQSL Card', $out);
        $this->assertStringContainsString('<EOH>', $out);
    }

    public function testCommentBlockCarriesActivationMetadata(): void
    {
        $out = (new AdifExporter())->export($this->fakeActivation(), [], '9W2NSP');
        $this->assertStringContainsString('## Activation: Test Park (POTA-K-1234)', $out);
        $this->assertStringContainsString('## Grid: OJ02wx', $out);
        $this->assertStringContainsString('## Operator: 9W2NSP', $out);
    }

    public function testEmptyActivationStillProducesValidAdif(): void
    {
        $out = (new AdifExporter())->export($this->fakeActivation(), [], '9W2NSP');
        $this->assertStringContainsString('<EOH>', $out);
        $this->assertStringNotContainsString('<EOR>', $out, 'No QSOs → no records');
    }

    public function testQsoRecordHasCoreFields(): void
    {
        $out = (new AdifExporter())->export($this->fakeActivation(), [$this->fakeQso()], '9W2NSP');
        $this->assertStringContainsString('<CALL:4>W1AW', $out);
        $this->assertStringContainsString('<QSO_DATE:8>20260516', $out);
        $this->assertStringContainsString('<TIME_ON:6>091530', $out);
        $this->assertStringContainsString('<BAND:3>20m', $out);
        $this->assertStringContainsString('<MODE:3>SSB', $out);
        $this->assertStringContainsString('<EOR>', $out);
    }

    public function testActivatorFieldsAppearOnEveryRecord(): void
    {
        $qsos = [$this->fakeQso(['call_worked' => 'W1AW']), $this->fakeQso(['call_worked' => 'JA1ABC'])];
        $out = (new AdifExporter())->export($this->fakeActivation(), $qsos, '9W2NSP');
        $this->assertSame(2, substr_count($out, '<STATION_CALLSIGN:6>9W2NSP'));
        $this->assertSame(2, substr_count($out, '<MY_GRIDSQUARE:6>OJ02wx'));
        $this->assertSame(2, substr_count($out, '<MY_POTA_REF:6>K-1234'));
    }

    public function testPotaPrefixDetection(): void
    {
        $cases = [
            'POTA-K-1234'   => 'K-1234',
            'POTA K-1234'   => 'K-1234',
            'POTA_K-1234'   => 'K-1234',
            'pota-K-1234'   => 'K-1234',  // case-insensitive
        ];
        foreach ($cases as $code => $expected) {
            $out = (new AdifExporter())->export(
                $this->fakeActivation(['code' => $code]),
                [$this->fakeQso()],
                '9W2NSP'
            );
            $this->assertStringContainsString('<MY_POTA_REF:' . strlen($expected) . '>' . $expected, $out,
                "Failed POTA detection for '$code'");
        }
    }

    public function testSotaPrefixDetection(): void
    {
        $out = (new AdifExporter())->export(
            $this->fakeActivation(['code' => 'SOTA-9M2/PR-001']),
            [$this->fakeQso()],
            '9W2NSP'
        );
        $this->assertStringContainsString('<MY_SOTA_REF:10>9M2/PR-001', $out);
        $this->assertStringNotContainsString('<MY_POTA_REF:', $out);
    }

    public function testNonAwardCodeOmitsAllRefFields(): void
    {
        $out = (new AdifExporter())->export(
            $this->fakeActivation(['code' => 'BL-2026-05-16']),
            [$this->fakeQso()],
            '9W2NSP'
        );
        $this->assertStringNotContainsString('<MY_POTA_REF:', $out);
        $this->assertStringNotContainsString('<MY_SOTA_REF:', $out);
        $this->assertStringNotContainsString('<MY_IOTA:', $out);
    }

    public function testEmptyFieldsAreOmitted(): void
    {
        $qso = $this->fakeQso([
            'operator_name' => '', 'operator_qth' => '', 'notes' => '', 'grid_square' => '',
        ]);
        $out = (new AdifExporter())->export($this->fakeActivation(), [$qso], '9W2NSP');
        $this->assertStringNotContainsString('<NAME:', $out);
        $this->assertStringNotContainsString('<QTH:', $out);
        $this->assertStringNotContainsString('<NOTES:', $out);
        $this->assertStringNotContainsString('<GRIDSQUARE:', $out);
    }

    public function testEmptyActivationGridOmitsMyGridsquare(): void
    {
        $out = (new AdifExporter())->export(
            $this->fakeActivation(['grid_square' => null]),
            [$this->fakeQso()],
            '9W2NSP'
        );
        $this->assertStringNotContainsString('<MY_GRIDSQUARE:', $out);
    }

    public function testFrequencyFormatStripsTrailingZeroes(): void
    {
        $tests = [
            '14.20000' => '14.2',
            '14.07415' => '14.07415',
            '7.000'    => '7.0',
            '7'        => '7.0',
            '145.625'  => '145.625',
        ];
        foreach ($tests as $input => $expected) {
            $out = (new AdifExporter())->export(
                $this->fakeActivation(),
                [$this->fakeQso(['frequency_mhz' => $input])],
                '9W2NSP'
            );
            $this->assertStringContainsString('<FREQ:' . strlen($expected) . '>' . $expected, $out,
                "Frequency '$input' should format as '$expected'");
        }
    }

    public function testLengthPrefixUsesByteCount(): void
    {
        $out = (new AdifExporter())->export(
            $this->fakeActivation(),
            [$this->fakeQso(['call_worked' => '9W2NSP'])],
            '9W2NSP'
        );
        // 9W2NSP is 6 bytes
        $this->assertStringContainsString('<CALL:6>9W2NSP', $out);
    }

    public function testStillActiveAppearsInHeaderWhenNoEndedAt(): void
    {
        $out = (new AdifExporter())->export(
            $this->fakeActivation(['ended_at' => null]),
            [],
            '9W2NSP'
        );
        $this->assertStringContainsString('(still active)', $out);
    }
}
