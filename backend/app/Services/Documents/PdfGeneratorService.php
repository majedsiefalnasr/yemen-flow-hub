<?php

namespace App\Services\Documents;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfGeneratorService
{
    private function makeMpdf(): Mpdf
    {
        $defaultFontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $defaultFontData = (new FontVariables)->getDefaults()['fontdata'];

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'ibmplexsansarabic',
            'default_font_size' => 11,
            'margin_top' => 20,
            'margin_right' => 20,
            'margin_bottom' => 20,
            'margin_left' => 20,
            'directionality' => 'rtl',
            'fontDir' => array_merge($defaultFontDirs, [resource_path('fonts')]),
            'fontdata' => $defaultFontData + [
                'ibmplexsansarabic' => [
                    'R' => 'IBMPlexSansArabic-Regular.ttf',
                    'B' => 'IBMPlexSansArabic-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
        ]);
    }

    public function generate(string $view, array $data, ?string $watermarkText = null): string
    {
        $html = view($view, $data)->render();

        $mpdf = $this->makeMpdf();
        $mpdf->WriteHTML($html);

        if ($watermarkText !== null) {
            $mpdf->SetWatermarkText($watermarkText);
            $mpdf->showWatermarkText = true;
            $mpdf->watermarkTextAlpha = 0.055;
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    public function download(string $filename, string $view, array $data): StreamedResponse
    {
        $pdfBytes = $this->generate($view, $data);

        return response()->streamDownload(
            static function () use ($pdfBytes): void {
                echo $pdfBytes;
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /** Stream a PDF inline in the browser (Content-Disposition: inline). */
    public function inline(string $filename, string $view, array $data, ?string $watermarkText = null): StreamedResponse
    {
        $pdfBytes = $this->generate($view, $data, $watermarkText);

        return response()->stream(
            static function () use ($pdfBytes): void {
                echo $pdfBytes;
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]
        );
    }
}
