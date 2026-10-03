<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function supplierSpend(ReportService $reports): JsonResponse
    {
        $rows = $reports->supplierSpend();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'status' => 'RECEIVED',
                'suppliers_count' => $rows->count(),
                'grand_total' => number_format($rows->sum(fn (array $row) => (float) $row['total_spend']), 2, '.', ''),
            ],
        ]);
    }
}
