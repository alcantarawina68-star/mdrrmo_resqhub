<?php

namespace App\Support\Reports;

use App\Models\User;

interface Report
{
    /**
     * Human title printed at the top of the PDF.
     */
    public function title(): string;

    /**
     * Download filename prefix, e.g. "resqhub-incidents".
     */
    public function filenamePrefix(): string;

    /**
     * Column headings in display order. Every row carries one cell per heading.
     *
     * @return array<int, string>
     */
    public function headings(): array;

    /**
     * Shown in place of the table when the report matches nothing.
     */
    public function emptyMessage(): string;

    /**
     * Rows for the given filters, yielded lazily so a large export never holds
     * the whole result set in memory.
     *
     * @param  array<string, mixed>  $filters
     * @param  string  $dateFormat  Caller picks the rendering: machine-readable
     *                              for CSV, human-readable for the PDF page.
     * @return \Generator<int, array<int, string|null>>
     */
    public function rows(array $filters, ?User $viewer = null, ?int $limit = null, string $dateFormat = 'Y-m-d H:i:s'): \Generator;
}
