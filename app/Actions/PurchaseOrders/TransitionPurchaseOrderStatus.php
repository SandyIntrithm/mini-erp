<?php

namespace App\Actions\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderStateException;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class TransitionPurchaseOrderStatus
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(PurchaseOrder $order, PurchaseOrderStatus $target, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $target, $user) {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->status->canTransitionTo($target)) {
                throw PurchaseOrderStateException::invalidTransition($locked->status, $target);
            }

            if ($target === PurchaseOrderStatus::Received) {
                $this->inventory->receivePurchaseOrder($locked, $user?->id);
            }

            $locked->status = $target;
            match ($target) {
                PurchaseOrderStatus::Approved => $locked->approved_at = now(),
                PurchaseOrderStatus::Received => $locked->received_at = now(),
                PurchaseOrderStatus::Cancelled => $locked->cancelled_at = now(),
                default => null,
            };
            $locked->save();

            return $locked;
        }, self::TRANSACTION_ATTEMPTS);
    }
}
