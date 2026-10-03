<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class ReportService
{
    public function dashboardMetrics(): array
    {
        $threshold = Product::lowStockThreshold();

        $products = Product::query()
            ->active()
            ->toBase()
            ->selectRaw('COUNT(*) as active_products')
            ->selectRaw('COALESCE(SUM(CASE WHEN stock_quantity < ? THEN 1 ELSE 0 END), 0) as low_stock_count', [$threshold])
            ->first();

        $expenditure = PurchaseOrder::query()
            ->status(PurchaseOrderStatus::Received)
            ->sum('total_amount');

        return [
            'active_products' => (int) $products->active_products,
            'low_stock_count' => (int) $products->low_stock_count,
            'total_expenditure' => $this->money($expenditure),
            'low_stock_threshold' => $threshold,
        ];
    }

    public function supplierSpend(): Collection
    {
        $spend = PurchaseOrder::query()
            ->toBase()
            ->select('supplier_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_amount) as total_spend')
            ->where('status', PurchaseOrderStatus::Received->value)
            ->groupBy('supplier_id');

        return Supplier::query()
            ->toBase()
            ->joinSub($spend, 'spend', 'spend.supplier_id', '=', 'suppliers.id')
            ->orderByDesc('spend.total_spend')
            ->orderBy('suppliers.name')
            ->get([
                'suppliers.id',
                'suppliers.code',
                'suppliers.name',
                'spend.orders_count',
                'spend.total_spend',
            ])
            ->map(fn (object $row) => [
                'supplier_id' => (int) $row->id,
                'supplier_code' => $row->code,
                'supplier_name' => $row->name,
                'orders_count' => (int) $row->orders_count,
                'total_spend' => $this->money($row->total_spend),
            ]);
    }

    private function money(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
