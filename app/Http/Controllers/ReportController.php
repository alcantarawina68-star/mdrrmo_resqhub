<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $from = $request->input('from');
        $to = $request->input('to');

        $summary = $this->reports->summary($from, $to);
        $trend = $this->reports->dailyTrend(30, $from, $to);
        $barangays = $this->reports->barangayBreakdown($from, $to);

        return view('dashboard.reports', compact('summary', 'trend', 'barangays', 'from', 'to'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'resqhub-incidents-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            echo $this->reports->exportCsv($request->all());
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
