<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminReportsRequest;
use App\Services\AdminReportsService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    /** @var array<int, string> */
    private const EXPORTS = ['sales', 'orders', 'products', 'payments', 'customers', 'coupons', 'inventory', 'newsletter', 'returns'];

    public function index(AdminReportsRequest $request, AdminReportsService $reports): View
    {
        $filters = $request->filters();

        return view('admin.reports.index', [
            'report' => $reports->report($filters),
            'filters' => $filters,
            'rangeOptions' => $reports->rangeOptions(),
            'exports' => self::EXPORTS,
        ]);
    }

    public function export(string $report, AdminReportsRequest $request, AdminReportsService $reports): StreamedResponse
    {
        abort_unless(in_array($report, self::EXPORTS, true), 404);
        $export = $reports->export($report, $request->filters());

        return response()->streamDownload(function () use ($export): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $export['headings']);

            foreach ($export['rows'] as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $export['filename'], ['Content-Type' => 'text/csv']);
    }
}
