<?php

namespace App\Support\Reports;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExporter
{
    /**
     * Machine-readable dates, so a spreadsheet can sort and filter on them.
     */
    public const CSV_DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Human-readable dates, for the printed page.
     */
    public const PDF_DATE_FORMAT = 'M j, Y g:i A';

    /**
     * DomPDF builds the whole document in memory, so PDF exports are capped.
     * CSV exports stream straight to the response and are not capped.
     */
    public const PDF_ROW_LIMIT = 5000;

    /**
     * Stream a CSV export straight to the browser.
     *
     * @param  array<string, mixed>  $filters
     */
    public function csvResponse(Report $report, array $filters, ?User $viewer = null): StreamedResponse
    {
        return response()->streamDownload(function () use ($report, $filters, $viewer): void {
            $handle = fopen('php://output', 'w');

            // Byte order mark, so Excel opens the export as UTF-8 rather than
            // mangling every accented barangay name.
            fwrite($handle, "\xEF\xBB\xBF");
            $this->writeRow($handle, $report->headings());

            foreach ($report->rows($filters, $viewer, null, self::CSV_DATE_FORMAT) as $row) {
                $this->writeRow($handle, $row);
            }

            fclose($handle);
        }, $this->filename($report, 'csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Build a CSV export as a string, for callers that cannot stream.
     *
     * @param  array<string, mixed>  $filters
     */
    public function csvString(Report $report, array $filters, ?User $viewer = null): string
    {
        $handle = fopen('php://temp', 'r+');

        fwrite($handle, "\xEF\xBB\xBF");
        $this->writeRow($handle, $report->headings());

        foreach ($report->rows($filters, $viewer, null, self::CSV_DATE_FORMAT) as $row) {
            $this->writeRow($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Render a PDF export and send it as a download.
     *
     * @param  array<string, mixed>  $filters
     */
    public function pdfResponse(Report $report, array $filters, ?User $viewer = null): Response
    {
        return $this->pdf($report, $filters, $viewer)->download($this->filename($report, 'pdf'));
    }

    /**
     * Render a PDF export, for callers that want to set the filename themselves.
     *
     * @param  array<string, mixed>  $filters
     */
    public function pdf(Report $report, array $filters, ?User $viewer = null): PdfDocument
    {
        $rows = iterator_to_array(
            $report->rows($filters, $viewer, self::PDF_ROW_LIMIT, self::PDF_DATE_FORMAT),
            false,
        );

        return Pdf::loadView('reports.table', [
            'title' => $report->title(),
            'headings' => $report->headings(),
            'rows' => $rows,
            'emptyMessage' => $report->emptyMessage(),
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'recordCount' => count($rows),
            'truncated' => count($rows) >= self::PDF_ROW_LIMIT,
            'generatedAt' => now(),
        ]);
    }

    private function filename(Report $report, string $extension): string
    {
        return $report->filenamePrefix().'-'.now()->format('Ymd-His').'.'.$extension;
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string|null>  $cells
     */
    private function writeRow($handle, array $cells): void
    {
        // The empty escape string is mandatory from PHP 8.4 on, and it keeps a
        // literal backslash inside a description from being swallowed on the way
        // out. Leaving it to the default also emits a deprecation notice.
        fputcsv($handle, $cells, ',', '"', '', "\n");
    }
}
