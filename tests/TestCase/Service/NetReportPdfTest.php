<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\NetReportPdf;
use Cake\TestSuite\TestCase;

final class NetReportPdfTest extends TestCase
{
    public function testRendersNonEmptyPdf(): void
    {
        $html = '<h1>MARTS Daily Net</h1><p>2 check-ins</p>';
        $pdf = (new NetReportPdf())->fromHtml($html);
        $this->assertNotEmpty($pdf);
        $this->assertSame('%PDF', substr($pdf, 0, 4));
    }
}
