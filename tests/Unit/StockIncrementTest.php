<?php

namespace Tests\Unit;

use App\Exceptions\InvalidStockAdjustmentException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockIncrementTest extends TestCase
{
    public static function invalidIncrements(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'large negative' => [-1000],
        ];
    }

    #[Test]
    #[DataProvider('invalidIncrements')]
    public function it_rejects_non_positive_stock_increments(int $quantity): void
    {
        $this->expectException(InvalidStockAdjustmentException::class);
        $this->expectExceptionMessage("Quantity must be a positive integer, {$quantity} given.");

        InventoryService::assertValidIncrement($quantity);
    }

    #[Test]
    #[DataProvider('invalidIncrements')]
    public function increase_stock_rejects_invalid_quantities_before_querying(int $quantity): void
    {
        $product = (new Product)->forceFill(['id' => 1, 'stock_quantity' => 10]);

        try {
            app(InventoryService::class)->increaseStock($product, $quantity, StockMovement::TYPE_OPENING_BALANCE);
            $this->fail('An invalid stock increment was accepted.');
        } catch (InvalidStockAdjustmentException) {
            $this->assertSame(10, $product->stock_quantity);
        }
    }

    #[Test]
    public function it_accepts_positive_increments(): void
    {
        InventoryService::assertValidIncrement(1);
        InventoryService::assertValidIncrement(PHP_INT_MAX);

        $this->addToAssertionCount(2);
    }

    #[Test]
    public function product_low_stock_flag_uses_the_configured_threshold(): void
    {
        config(['inventory.low_stock_threshold' => 10]);

        $this->assertTrue((new Product)->forceFill(['stock_quantity' => 9])->isLowStock());
        $this->assertFalse((new Product)->forceFill(['stock_quantity' => 10])->isLowStock());

        config(['inventory.low_stock_threshold' => 3]);

        $this->assertFalse((new Product)->forceFill(['stock_quantity' => 9])->isLowStock());
    }
}
