<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->reports->summary($request->input('from'), $request->input('to')),
        );
    }

    public function trend(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->reports->dailyTrend(
                min(max($request->integer('days', 30), 1), 90),
                $request->input('from'),
                $request->input('to'),
            ),
        );
    }

    public function barangays(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->reports->barangayBreakdown($request->input('from'), $request->input('to')),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'resqhub-incidents-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            echo $this->reports->exportCsv($request->all());
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
