<?php

namespace App\Actions\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseOrderCalculator;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrder
{
    use PersistsLineItems;

    public function __construct(private readonly PurchaseOrderCalculator $calculator) {}

    public function handle(array $data, ?User $user = null): PurchaseOrder
    {
        $calculated = $this->calculator->calculate($data['items']);

        return DB::transaction(function () use ($data, $user, $calculated) {
            $order = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'created_by' => $user?->id,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'total_amount' => $calculated['total'],
            ]);

            $this->insertLineItems($order, $calculated['lines']);

            return $order->load(['supplier', 'items.product']);
        });
    }
}
