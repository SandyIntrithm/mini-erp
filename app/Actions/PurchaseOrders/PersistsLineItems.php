<?php

namespace App\Actions\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

trait PersistsLineItems
{
    protected function insertLineItems(PurchaseOrder $order, array $lines): void
    {
        $now = now();

        PurchaseOrderItem::query()->insert(array_map(fn (array $line) => $line + [
            'purchase_order_id' => $order->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $lines));
    }
}
