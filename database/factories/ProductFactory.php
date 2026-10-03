<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'name' => ucwords(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'unit_cost' => fake()->randomFloat(2, 1, 500),
            'stock_quantity' => fake()->numberBetween(20, 200),
            'is_active' => true,
        ];
    }

    public function stock(int $quantity): static
    {
        return $this->state(fn () => ['stock_quantity' => $quantity]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => fake()->numberBetween(0, Product::lowStockThreshold() - 1)]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
