<?php

namespace App\Services;

use App\Exceptions\InvalidStockAdjustmentException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use LogicException;

class InventoryService
{
    public static function assertValidIncrement(int $quantity): void
    {
        if ($quantity <= 0) {
            throw InvalidStockAdjustmentException::nonPositiveQuantity($quantity);
        }
    }

    public function increaseStock(Product $product, int $quantity, string $type, ?int $userId = null): StockMovement
    {
        static::assertValidIncrement($quantity);

        return DB::transaction(function () use ($product, $quantity, $type, $userId) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey(), ['id', 'stock_quantity']);

            Product::query()->whereKey($locked->id)->increment('stock_quantity', $quantity);

            $product->stock_quantity = $locked->stock_quantity + $quantity;
            $product->syncOriginalAttribute('stock_quantity');

            return StockMovement::create([
                'product_id' => $locked->id,
                'user_id' => $userId,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $product->stock_quantity,
            ]);
        });
    }

    public function receivePurchaseOrder(PurchaseOrder $order, ?int $userId = null): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Purchase order receipts must be applied inside a database transaction.');
        }

        $quantities = $order->items()
            ->toBase()
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->pluck('quantity', 'product_id')
            ->map(fn ($quantity) => (int) $quantity)
            ->all();

        if ($quantities === []) {
            throw new InvalidStockAdjustmentException('Purchase order has no line items to receive.');
        }

        foreach ($quantities as $quantity) {
            static::assertValidIncrement($quantity);
        }

        $current = Product::query()
            ->whereKey(array_keys($quantities))
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('stock_quantity', 'id');

        $now = now();
        $movements = [];

        foreach ($current as $productId => $stock) {
            $quantity = $quantities[$productId];

            Product::query()->whereKey($productId)->increment('stock_quantity', $quantity);

            $movements[] = [
                'product_id' => $productId,
                'purchase_order_id' => $order->id,
                'user_id' => $userId,
                'type' => StockMovement::TYPE_PURCHASE_RECEIPT,
                'quantity' => $quantity,
                'balance_after' => (int) $stock + $quantity,
                'created_at' => $now,
            ];
        }

        StockMovement::query()->insert($movements);
    }
}
