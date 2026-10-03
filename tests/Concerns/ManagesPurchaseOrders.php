<?php

namespace Tests\Concerns;

use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\TransitionPurchaseOrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait ManagesPurchaseOrders
{
    protected function apiUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function createOrder(array $lines, ?Supplier $supplier = null): PurchaseOrder
    {
        return app(CreatePurchaseOrder::class)->handle([
            'supplier_id' => ($supplier ?? Supplier::factory()->create())->id,
            'items' => array_map(fn (array $line) => [
                'product_id' => $line[0]->id,
                'quantity' => $line[1],
                'unit_price' => $line[2],
            ], $lines),
        ]);
    }

    protected function moveTo(PurchaseOrder $order, PurchaseOrderStatus ...$statuses): PurchaseOrder
    {
        foreach ($statuses as $status) {
            $order = app(TransitionPurchaseOrderStatus::class)->handle($order, $status);
        }

        return $order;
    }

    protected function receivedOrder(array $lines, ?Supplier $supplier = null): PurchaseOrder
    {
        return $this->moveTo($this->createOrder($lines, $supplier), PurchaseOrderStatus::Approved, PurchaseOrderStatus::Received);
    }
}
