<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sku', 'name', 'description', 'unit_cost', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $attributes = [
        'stock_quantity' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query, ?int $threshold = null): void
    {
        $query->where('stock_quantity', '<', $threshold ?? static::lowStockThreshold());
    }

    public static function lowStockThreshold(): int
    {
        return (int) config('inventory.low_stock_threshold', 10);
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity < static::lowStockThreshold();
    }
}
