<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPurchaseOrders;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use ManagesPurchaseOrders;
    use RefreshDatabase;

    #[Test]
    public function it_returns_a_paginated_inventory_list(): void
    {
        $this->apiUser();
        Product::factory()->count(20)->create();
        Product::factory()->inactive()->create();

        $this->getJson('/api/inventory?per_page=5')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'sku', 'name', 'unit_cost', 'stock_quantity', 'is_low_stock', 'is_active']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'low_stock_threshold'],
            ])
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }

    #[Test]
    public function per_page_is_bounded(): void
    {
        $this->apiUser();

        $this->getJson('/api/inventory?per_page=5000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    #[Test]
    public function it_returns_only_active_items_below_the_low_stock_threshold(): void
    {
        $this->apiUser();
        config(['inventory.low_stock_threshold' => 10]);

        $empty = Product::factory()->stock(0)->create();
        $nine = Product::factory()->stock(9)->create();
        Product::factory()->stock(10)->create();
        Product::factory()->stock(50)->create();
        Product::factory()->inactive()->stock(1)->create();

        $response = $this->getJson('/api/inventory/low-stock')
            ->assertOk()
            ->assertJsonPath('meta.low_stock_threshold', 10)
            ->assertJsonPath('meta.total', 2);

        $this->assertSame([$empty->id, $nine->id], array_column($response->json('data'), 'id'));
        $this->assertSame([true, true], array_column($response->json('data'), 'is_low_stock'));
    }

    #[Test]
    public function the_low_stock_threshold_is_configurable(): void
    {
        $this->apiUser();
        config(['inventory.low_stock_threshold' => 3]);

        Product::factory()->stock(2)->create();
        Product::factory()->stock(5)->create();

        $this->getJson('/api/inventory/low-stock')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.low_stock_threshold', 3);
    }

    #[Test]
    public function inventory_reflects_received_stock(): void
    {
        $this->apiUser();
        $product = Product::factory()->stock(2)->create();

        $this->receivedOrder([[$product, 40, '1.00']]);

        $this->getJson('/api/inventory')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.stock_quantity', 42)
            ->assertJsonPath('data.0.is_low_stock', false);
    }
}
