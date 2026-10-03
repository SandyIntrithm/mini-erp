<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_price' => fake()->randomFloat(2, 1, 500),
            'line_total' => fn (array $attributes) => number_format($attributes['quantity'] * $attributes['unit_price'], 2, '.', ''),
        ];
    }
}
