<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\Reports\AnnouncementReport;
use App\Support\Reports\IncidentReport;
use App\Support\Reports\MyIncidentReport;
use App\Support\Reports\ReportExporter;
use App\Support\Reports\SessionReport;
use App\Support\Reports\SmsReport;
use App\Support\Reports\UserReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The reports hub and every CSV/PDF export in the operations dashboard.
 *
 * Each action narrows the request with the report's own filter list before
 * handing it over, so no query-string key can reach a query builder unless the
 * report declares it.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExporter $exporter,
    ) {}

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
        return $this->exporter->csvResponse(new IncidentReport, $request->only(IncidentReport::filters()));
    }

    public function exportPdf(Request $request): Response
    {
        return $this->exporter->pdfResponse(new IncidentReport, $request->only(IncidentReport::filters()));
    }

    public function users(Request $request): StreamedResponse
    {
        return $this->exporter->csvResponse(new UserReport, $request->only(UserReport::filters()), $request->user());
    }

    public function usersPdf(Request $request): Response
    {
        return $this->exporter->pdfResponse(new UserReport, $request->only(UserReport::filters()), $request->user());
    }

    public function sessions(Request $request): StreamedResponse
    {
        return $this->exporter->csvResponse(new SessionReport, $request->only(SessionReport::filters()), $request->user());
    }

    public function sessionsPdf(Request $request): Response
    {
        return $this->exporter->pdfResponse(new SessionReport, $request->only(SessionReport::filters()), $request->user());
    }

    public function announcements(Request $request): StreamedResponse
    {
        return $this->exporter->csvResponse(new AnnouncementReport, $request->only(AnnouncementReport::filters()), $request->user());
    }

    public function announcementsPdf(Request $request): Response
    {
        return $this->exporter->pdfResponse(new AnnouncementReport, $request->only(AnnouncementReport::filters()), $request->user());
    }

    public function sms(Request $request): StreamedResponse
    {
        return $this->exporter->csvResponse(new SmsReport, $request->only(SmsReport::filters()), $request->user());
    }

    public function smsPdf(Request $request): Response
    {
        return $this->exporter->pdfResponse(new SmsReport, $request->only(SmsReport::filters()), $request->user());
    }

    public function myReports(Request $request): StreamedResponse
    {
        return $this->exporter->csvResponse(
            new MyIncidentReport,
            $request->only(MyIncidentReport::filters()),
            $request->user(),
        );
    }

    public function myReportsPdf(Request $request): Response
    {
        return $this->exporter->pdfResponse(
            new MyIncidentReport,
            $request->only(MyIncidentReport::filters()),
            $request->user(),
        );
    }
}
