<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        return view('dashboard', [
            'metrics' => $reports->dashboardMetrics(),
            'latestOrders' => PurchaseOrder::query()
                ->with('supplier:id,name')
                ->withCount('items')
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
