<?php

namespace App\Actions\PurchaseOrders;

use App\Exceptions\PurchaseOrderStateException;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderCalculator;
use Illuminate\Support\Facades\DB;

class UpdatePurchaseOrder
{
    use PersistsLineItems;

    public function __construct(private readonly PurchaseOrderCalculator $calculator) {}

    public function handle(PurchaseOrder $order, array $data): PurchaseOrder
    {
        $calculated = $this->calculator->calculate($data['items']);

        return DB::transaction(function () use ($order, $data, $calculated) {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->status->isEditable()) {
                throw PurchaseOrderStateException::notEditable($locked->status);
            }

            $locked->update([
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'] ?? $locked->order_date,
                'notes' => $data['notes'] ?? null,
                'total_amount' => $calculated['total'],
            ]);

            $locked->items()->delete();
            $this->insertLineItems($locked, $calculated['lines']);

            return $locked->load(['supplier', 'items.product']);
        });
    }
}
