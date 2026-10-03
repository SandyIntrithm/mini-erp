<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPurchaseOrders;
use Tests\TestCase;

class PurchaseOrderApiTest extends TestCase
{
    use ManagesPurchaseOrders;
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_draft_purchase_order_and_returns_201(): void
    {
        $user = $this->apiUser();
        $supplier = Supplier::factory()->create();
        $a = Product::factory()->stock(3)->create();
        $b = Product::factory()->stock(0)->create();

        $response = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-01',
            'notes' => 'Urgent restock',
            'items' => [
                ['product_id' => $a->id, 'quantity' => 12, 'unit_price' => 4.25],
                ['product_id' => $b->id, 'quantity' => 1, 'unit_price' => '199.99'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => [
                'id', 'reference', 'status', 'order_date', 'total_amount',
                'supplier' => ['id', 'name'],
                'items' => [['id', 'product_id', 'product' => ['id', 'sku', 'name'], 'quantity', 'unit_price', 'line_total']],
            ]])
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.supplier.id', $supplier->id)
            ->assertJsonPath('data.order_date', '2026-10-01')
            ->assertJsonPath('data.total_amount', '250.99')
            ->assertJsonPath('data.items.0.line_total', '51.00')
            ->assertJsonPath('data.items.1.line_total', '199.99');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $response->json('data.id'),
            'status' => 'DRAFT',
            'created_by' => $user->id,
            'total_amount' => '250.99',
        ]);
        $this->assertSame(3, $a->fresh()->stock_quantity, 'Creating an order must not change stock.');
    }

    #[Test]
    public function client_supplied_status_and_totals_are_ignored(): void
    {
        $this->apiUser();
        $product = Product::factory()->stock(0)->create();

        $this->postJson('/api/purchase-orders', [
            'supplier_id' => Supplier::factory()->create()->id,
            'status' => 'RECEIVED',
            'total_amount' => '0.01',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '10.00', 'line_total' => '1.00']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonPath('data.items.0.line_total', '20.00');

        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function missing_required_fields_fail_validation(): void
    {
        $this->apiUser();

        $this->postJson('/api/purchase-orders', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['supplier_id', 'items']])
            ->assertJsonValidationErrors(['supplier_id', 'items']);

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    #[Test]
    public function an_empty_items_array_fails_validation(): void
    {
        $this->apiUser();

        $this->postJson('/api/purchase-orders', [
            'supplier_id' => Supplier::factory()->create()->id,
            'items' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items' => 'A purchase order needs at least one line item.'])
            ->assertJsonMissingValidationErrors('supplier_id');
    }

    #[Test]
    public function each_line_item_is_validated_with_indexed_error_keys(): void
    {
        $this->apiUser();
        $valid = Product::factory()->create();

        $this->postJson('/api/purchase-orders', [
            'supplier_id' => Supplier::factory()->create()->id,
            'items' => [
                ['product_id' => $valid->id, 'quantity' => 1, 'unit_price' => '1.00'],
                ['product_id' => 999999, 'quantity' => 0, 'unit_price' => -5],
                ['quantity' => -3, 'unit_price' => '1.999'],
                [],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.1.product_id',
                'items.1.quantity',
                'items.1.unit_price',
                'items.2.product_id',
                'items.2.quantity',
                'items.2.unit_price',
                'items.3',
            ])
            ->assertJsonMissingValidationErrors(['items.0.product_id', 'items.0.quantity', 'items.0.unit_price']);

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    #[Test]
    public function duplicate_products_inactive_products_and_inactive_suppliers_are_rejected(): void
    {
        $this->apiUser();
        $product = Product::factory()->create();
        $inactiveProduct = Product::factory()->inactive()->create();

        $this->postJson('/api/purchase-orders', [
            'supplier_id' => Supplier::factory()->inactive()->create()->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '1.00'],
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '1.00'],
                ['product_id' => $inactiveProduct->id, 'quantity' => 2, 'unit_price' => '1.00'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'supplier_id',
                'items.1.product_id' => 'Each product may only appear once per order.',
                'items.2.product_id' => 'The selected product is invalid or inactive.',
            ]);
    }

    #[Test]
    public function it_lists_purchase_orders_with_pagination_and_status_filter(): void
    {
        $this->apiUser();
        $product = Product::factory()->create();
        $this->createOrder([[$product, 1, '1.00']]);
        $this->receivedOrder([[$product, 1, '1.00']]);

        $this->getJson('/api/purchase-orders')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'reference', 'status', 'supplier', 'items_count', 'total_amount']], 'links', 'meta' => ['current_page', 'per_page', 'total']])
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/purchase-orders?status=RECEIVED')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'RECEIVED');

        $this->getJson('/api/purchase-orders?status=BOGUS')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function listing_orders_uses_a_constant_number_of_queries(): void
    {
        $this->apiUser();
        $products = Product::factory()->count(3)->create();

        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/purchase-orders')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->createOrder([[$products[0], 1, '1.00'], [$products[1], 1, '1.00']]);
        $withOneOrder = $countQueries();

        foreach (range(1, 10) as $_) {
            $this->createOrder([[$products[0], 1, '1.00'], [$products[2], 1, '1.00']]);
        }
        $withElevenOrders = $countQueries();

        $this->assertSame($withOneOrder, $withElevenOrders, 'Query count grows with the number of orders (N+1).');
    }

    #[Test]
    public function it_shows_a_single_order_with_its_lines(): void
    {
        $this->apiUser();
        $order = $this->createOrder([[Product::factory()->create(), 3, '2.00']]);

        $this->getJson("/api/purchase-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.reference', $order->reference)
            ->assertJsonPath('data.total_amount', '6.00')
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function unknown_orders_return_a_json_404(): void
    {
        $this->apiUser();

        $this->getJson('/api/purchase-orders/987654')->assertNotFound()->assertJsonStructure(['message']);
        $this->patchJson('/api/purchase-orders/987654/status', ['status' => 'APPROVED'])->assertNotFound();
    }

    #[Test]
    public function the_status_endpoint_requires_a_valid_status(): void
    {
        $this->apiUser();
        $order = $this->createOrder([[Product::factory()->create(), 1, '1.00']]);

        $this->patchJson("/api/purchase-orders/{$order->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => 'SHIPPED'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('DRAFT', PurchaseOrder::find($order->id)->status->value);
    }
}
