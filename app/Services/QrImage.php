<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * The QR image is never stored (spec section 6.1). It is a pure function of the
 * asset tag, drawn on demand wherever it is needed, so there is no file that
 * can go missing and nothing that can fall out of sync.
 */
class QrImage
{
    public function svg(string $data, int $size = 220): string
    {
        return (new Builder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 0,
        ))->build()->getString();
    }

    /** dompdf handles a PNG data URI far more reliably than inline SVG. */
    public function pngDataUri(string $data, int $size = 300): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 0,
        ))->build()->getDataUri();
    }
}
