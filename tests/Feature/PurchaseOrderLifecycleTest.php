<?php

namespace Tests\Feature;

use App\Actions\PurchaseOrders\TransitionPurchaseOrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderStateException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\ManagesPurchaseOrders;
use Tests\TestCase;

class PurchaseOrderLifecycleTest extends TestCase
{
    use ManagesPurchaseOrders;
    use RefreshDatabase;

    #[Test]
    public function receiving_an_order_increments_stock_for_every_line(): void
    {
        $user = $this->apiUser();
        $bolts = Product::factory()->stock(5)->create();
        $nuts = Product::factory()->stock(0)->create();
        $untouched = Product::factory()->stock(7)->create();

        $order = $this->createOrder([[$bolts, 10, '2.50'], [$nuts, 3, '9.99']]);
        $this->moveTo($order, PurchaseOrderStatus::Approved);

        $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => 'RECEIVED'])
            ->assertOk()
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.total_amount', '54.97');

        $this->assertSame(15, $bolts->fresh()->stock_quantity);
        $this->assertSame(3, $nuts->fresh()->stock_quantity);
        $this->assertSame(7, $untouched->fresh()->stock_quantity);

        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::Received, $order->status);
        $this->assertNotNull($order->received_at);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $bolts->id,
            'purchase_order_id' => $order->id,
            'user_id' => $user->id,
            'type' => StockMovement::TYPE_PURCHASE_RECEIPT,
            'quantity' => 10,
            'balance_after' => 15,
        ]);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    #[Test]
    public function stock_does_not_change_on_create_approve_or_cancel(): void
    {
        $this->apiUser();
        $product = Product::factory()->stock(4)->create();

        $order = $this->createOrder([[$product, 50, '1.00']]);
        $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => 'APPROVED'])->assertOk();
        $this->assertSame(4, $product->fresh()->stock_quantity);

        $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => 'CANCELLED'])->assertOk();
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[Test]
    public function an_order_cannot_be_received_twice(): void
    {
        $this->apiUser();
        $product = Product::factory()->stock(0)->create();
        $order = $this->moveTo($this->createOrder([[$product, 8, '1.00']]), PurchaseOrderStatus::Approved);

        $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => 'RECEIVED'])->assertOk();
        $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => 'RECEIVED'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    #[Test]
    public function a_draft_cannot_skip_approval_and_be_received(): void
    {
        $this->apiUser();
        $product = Product::factory()->stock(2)->create();
        $order = $this->createOrder([[$product, 8, '1.00']]);

        $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => 'RECEIVED'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot change purchase order status from DRAFT to RECEIVED.');

        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertSame(PurchaseOrderStatus::Draft, $order->fresh()->status);
    }

    public static function finalStateTransitions(): array
    {
        return [
            'received → approved' => ['RECEIVED', 'APPROVED'],
            'received → cancelled' => ['RECEIVED', 'CANCELLED'],
            'received → received' => ['RECEIVED', 'RECEIVED'],
            'cancelled → approved' => ['CANCELLED', 'APPROVED'],
            'cancelled → received' => ['CANCELLED', 'RECEIVED'],
            'cancelled → cancelled' => ['CANCELLED', 'CANCELLED'],
        ];
    }

    #[Test]
    #[DataProvider('finalStateTransitions')]
    public function final_orders_reject_every_further_transition_with_422(string $from, string $to): void
    {
        $this->apiUser();
        $product = Product::factory()->stock(0)->create();
        $order = $this->createOrder([[$product, 5, '1.00']]);
        $order = $from === 'RECEIVED'
            ? $this->moveTo($order, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Received)
            : $this->moveTo($order, PurchaseOrderStatus::Cancelled);
        $stockBefore = $product->fresh()->stock_quantity;

        $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => $to])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath('message', "Cannot change purchase order status from {$from} to {$to}.");

        $this->assertSame($from, $order->fresh()->status->value);
        $this->assertSame($stockBefore, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function transitioning_back_to_draft_is_rejected_by_validation(): void
    {
        $this->apiUser();
        $order = $this->receivedOrder([[Product::factory()->create(), 1, '1.00']]);

        $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => 'DRAFT'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'The status must be one of: APPROVED, RECEIVED, CANCELLED.']);

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
    }

    public static function lockedStates(): array
    {
        return [
            'received' => ['RECEIVED'],
            'cancelled' => ['CANCELLED'],
            'approved' => ['APPROVED'],
        ];
    }

    #[Test]
    #[DataProvider('lockedStates')]
    public function line_items_of_a_non_draft_order_cannot_be_modified(string $status): void
    {
        $this->apiUser();
        $product = Product::factory()->stock(0)->create();
        $order = $this->createOrder([[$product, 5, '10.00']]);
        $order = match ($status) {
            'RECEIVED' => $this->moveTo($order, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Received),
            'CANCELLED' => $this->moveTo($order, PurchaseOrderStatus::Cancelled),
            'APPROVED' => $this->moveTo($order, PurchaseOrderStatus::Approved),
        };

        $this->putJson("/api/purchase-orders/{$order->id}", [
            'supplier_id' => $order->supplier_id,
            'items' => [['product_id' => $product->id, 'quantity' => 999, 'unit_price' => '1.00']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $order->refresh()->load('items');
        $this->assertSame('50.00', $order->total_amount);
        $this->assertCount(1, $order->items);
        $this->assertSame(5, $order->items->first()->quantity);
    }

    #[Test]
    public function a_draft_order_can_be_edited_and_totals_are_recalculated(): void
    {
        $this->apiUser();
        [$a, $b] = Product::factory()->count(2)->create();
        $order = $this->createOrder([[$a, 5, '10.00']]);

        $this->putJson("/api/purchase-orders/{$order->id}", [
            'supplier_id' => $order->supplier_id,
            'notes' => 'Revised quantities',
            'items' => [
                ['product_id' => $a->id, 'quantity' => 2, 'unit_price' => '10.00'],
                ['product_id' => $b->id, 'quantity' => 3, 'unit_price' => '1.50'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.total_amount', '24.50')
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseCount('purchase_order_items', 2);
    }

    #[Test]
    public function the_action_throws_a_domain_exception_for_illegal_transitions(): void
    {
        $order = $this->receivedOrder([[Product::factory()->create(), 1, '1.00']]);

        $this->expectException(PurchaseOrderStateException::class);
        $this->expectExceptionMessage('Cannot change purchase order status from RECEIVED to CANCELLED.');

        app(TransitionPurchaseOrderStatus::class)->handle($order, PurchaseOrderStatus::Cancelled);
    }

    #[Test]
    public function the_model_refuses_direct_updates_to_a_received_order(): void
    {
        $order = $this->receivedOrder([[Product::factory()->create(), 1, '1.00']]);

        $this->expectException(PurchaseOrderStateException::class);

        $order->update(['notes' => 'tampered']);
    }

    #[Test]
    public function the_model_refuses_to_delete_a_cancelled_order(): void
    {
        $order = $this->moveTo($this->createOrder([[Product::factory()->create(), 1, '1.00']]), PurchaseOrderStatus::Cancelled);

        try {
            $order->delete();
            $this->fail('A cancelled order was deleted.');
        } catch (PurchaseOrderStateException) {
            $this->assertModelExists($order);
        }
    }

    #[Test]
    public function a_failed_receipt_rolls_back_stock_and_status_atomically(): void
    {
        $first = Product::factory()->stock(1)->create();
        $second = Product::factory()->stock(1)->create();
        $order = $this->moveTo($this->createOrder([[$first, 5, '1.00'], [$second, 5, '1.00']]), PurchaseOrderStatus::Approved);

        $this->app->instance(InventoryService::class, new class extends InventoryService
        {
            public function receivePurchaseOrder(PurchaseOrder $order, ?int $userId = null): void
            {
                parent::receivePurchaseOrder($order, $userId);

                throw new RuntimeException('Simulated failure after stock update');
            }
        });

        try {
            app(TransitionPurchaseOrderStatus::class)->handle($order, PurchaseOrderStatus::Received);
            $this->fail('Expected the simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated failure after stock update', $e->getMessage());
        }

        $this->assertSame(1, $first->fresh()->stock_quantity);
        $this->assertSame(1, $second->fresh()->stock_quantity);
        $this->assertSame(PurchaseOrderStatus::Approved, $order->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[Test]
    public function receiving_uses_the_quantities_stored_on_the_order_not_client_input(): void
    {
        $this->apiUser();
        $product = Product::factory()->stock(0)->create();
        $order = $this->moveTo($this->createOrder([[$product, 4, '1.00']]), PurchaseOrderStatus::Approved);

        $this->patchJson("/api/purchase-orders/{$order->id}/status", [
            'status' => 'RECEIVED',
            'items' => [['product_id' => $product->id, 'quantity' => 1000]],
        ])->assertOk();

        $this->assertSame(4, $product->fresh()->stock_quantity);
    }
}
