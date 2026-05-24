<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\OperationLog;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders an HTML net report to a PDF byte string via dompdf.
 *
 * Pure-PHP — no system binaries required, suitable for shared hosting.
 * Output is A4 portrait with remote resources disabled so the render is
 * self-contained and deterministic regardless of network availability.
 */
final class NetReportPdf
{
    /**
     * Convert an HTML string to a PDF byte string.
     *
     * @param string $html Full HTML document for the net report.
     * @return string Raw PDF bytes, ready to stream or write to disk.
     */
    public function fromHtml(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = (string)$dompdf->output();

        OperationLog::event('net.report.pdf.export', ['bytes' => strlen($pdf)]);

        return $pdf;
    }
}
