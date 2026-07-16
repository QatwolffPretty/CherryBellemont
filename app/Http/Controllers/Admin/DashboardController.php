<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboardRequest;
use App\Services\AdminAnalyticsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardRequest $request, AdminAnalyticsService $analytics): View
    {
        $filters = $request->filters();

        return view('admin.dashboard', [
            'dashboard' => $analytics->dashboard($filters),
            'filters' => $filters,
            'rangeOptions' => $analytics->rangeOptions(),
        ]);
    }
}
