<?php

namespace Tests\Feature\Web;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPurchaseOrders;
use Tests\TestCase;

class WebInterfaceTest extends TestCase
{
    use ManagesPurchaseOrders;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);
    }

    public static function protectedPages(): array
    {
        return [
            ['/dashboard'],
            ['/inventory'],
            ['/purchase-orders'],
            ['/purchase-orders/create'],
            ['/suppliers'],
        ];
    }

    #[Test]
    #[DataProvider('protectedPages')]
    public function guests_are_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    #[Test]
    public function a_user_can_log_in_and_out(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function login_with_wrong_password_shows_an_error(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'admin@example.com', 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function the_dashboard_shows_metrics_and_the_latest_five_orders(): void
    {
        config(['inventory.low_stock_threshold' => 10]);
        $product = Product::factory()->stock(100)->create();
        Product::factory()->stock(2)->create();
        Product::factory()->stock(9)->create();
        Product::factory()->inactive()->stock(0)->create();

        $this->receivedOrder([[$product, 10, '100.00']]);
        $this->receivedOrder([[$product, 1, '234.56']]);
        $this->moveTo($this->createOrder([[$product, 1, '5000.00']]), PurchaseOrderStatus::Cancelled);
        $orders = collect(range(1, 4))->map(fn () => $this->createOrder([[$product, 1, '1.00']]));
        $oldest = PurchaseOrder::query()->orderBy('id')->first();
        $newest = $orders->last();

        $response = $this->actingAs($this->user)->get('/dashboard')->assertOk();

        $response->assertSeeInOrder(['data-metric="active-products"', '3'], false);
        $response->assertSeeInOrder(['data-metric="low-stock"', '2'], false);
        $response->assertSeeInOrder(['data-metric="expenditure"', '1,234.56'], false);
        $response->assertSee($newest->reference);
        $response->assertDontSee($oldest->reference);
        $response->assertSee('text-bg-danger', false);
        $response->assertSee('text-bg-secondary', false);
        $this->assertCount(5, $response->viewData('latestOrders'));
    }

    #[Test]
    public function the_create_screen_lists_suppliers_and_products(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Visible Supplier']);
        Supplier::factory()->inactive()->create(['name' => 'Hidden Supplier']);
        $product = Product::factory()->create(['sku' => 'SKU-VISIBLE']);

        $this->actingAs($this->user)->get('/purchase-orders/create')
            ->assertOk()
            ->assertSee('Visible Supplier')
            ->assertDontSee('Hidden Supplier')
            ->assertSee('SKU-VISIBLE')
            ->assertSee('data-price="'.$product->unit_cost.'"', false)
            ->assertSee('id="grand-total"', false);
    }

    #[Test]
    public function a_purchase_order_can_be_created_from_the_web_form(): void
    {
        $supplier = Supplier::factory()->create();
        [$a, $b] = Product::factory()->count(2)->create();

        $response = $this->actingAs($this->user)->post('/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-02',
            'items' => [
                0 => ['product_id' => $a->id, 'quantity' => '2', 'unit_price' => '15.00'],
                3 => ['product_id' => $b->id, 'quantity' => '1', 'unit_price' => '0.99'],
            ],
        ]);

        $order = PurchaseOrder::query()->sole();
        $response->assertRedirect("/purchase-orders/{$order->id}")->assertSessionHas('success');
        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertSame('30.99', $order->total_amount);
        $this->assertSame($this->user->id, $order->created_by);
    }

    #[Test]
    public function web_form_validation_errors_are_reported_per_line(): void
    {
        $this->actingAs($this->user)
            ->from('/purchase-orders/create')
            ->post('/purchase-orders', [
                'supplier_id' => '',
                'items' => [['product_id' => '', 'quantity' => '-1', 'unit_price' => '']],
            ])
            ->assertRedirect('/purchase-orders/create')
            ->assertSessionHasErrors(['supplier_id', 'items.0.product_id', 'items.0.quantity', 'items.0.unit_price']);

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    #[Test]
    public function the_details_page_shows_actions_for_the_current_state(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-LINE']);
        $order = $this->createOrder([[$product, 4, '2.50']]);

        $this->actingAs($this->user)->get("/purchase-orders/{$order->id}")
            ->assertOk()
            ->assertSee($order->supplier->name)
            ->assertSee('SKU-LINE')
            ->assertSee('10.00')
            ->assertSee('Approve')
            ->assertSee('Cancel Order')
            ->assertDontSee('Mark as Received');

        $this->moveTo($order, PurchaseOrderStatus::Approved);

        $this->get("/purchase-orders/{$order->id}")
            ->assertSee('Mark as Received')
            ->assertSee('Cancel Order')
            ->assertDontSee('>Approve<', false);

        $this->moveTo($order, PurchaseOrderStatus::Received);

        $this->get("/purchase-orders/{$order->id}")
            ->assertSee('can no longer be modified')
            ->assertDontSee('Mark as Received')
            ->assertDontSee('Cancel Order');
    }

    #[Test]
    public function marking_as_received_from_the_web_updates_inventory(): void
    {
        $product = Product::factory()->stock(1)->create();
        $order = $this->moveTo($this->createOrder([[$product, 6, '1.00']]), PurchaseOrderStatus::Approved);

        $this->actingAs($this->user)
            ->patch("/purchase-orders/{$order->id}/status", ['status' => 'RECEIVED'])
            ->assertRedirect("/purchase-orders/{$order->id}")
            ->assertSessionHas('success');

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
    }

    #[Test]
    public function an_illegal_web_transition_is_reported_as_a_flash_error(): void
    {
        $order = $this->receivedOrder([[Product::factory()->create(), 1, '1.00']]);

        $this->actingAs($this->user)
            ->from("/purchase-orders/{$order->id}")
            ->patch("/purchase-orders/{$order->id}/status", ['status' => 'CANCELLED'])
            ->assertRedirect("/purchase-orders/{$order->id}")
            ->assertSessionHas('error', 'Cannot change purchase order status from RECEIVED to CANCELLED.');

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
    }

    #[Test]
    public function the_edit_screen_is_only_available_for_drafts(): void
    {
        $product = Product::factory()->create();
        $draft = $this->createOrder([[$product, 1, '1.00']]);
        $received = $this->receivedOrder([[$product, 1, '1.00']]);

        $this->actingAs($this->user)->get("/purchase-orders/{$draft->id}/edit")->assertOk();
        $this->get("/purchase-orders/{$received->id}/edit")
            ->assertRedirect("/purchase-orders/{$received->id}")
            ->assertSessionHas('error');
    }

    #[Test]
    public function the_inventory_page_flags_low_stock_items(): void
    {
        config(['inventory.low_stock_threshold' => 10]);
        Product::factory()->stock(3)->create(['sku' => 'SKU-LOW']);
        Product::factory()->stock(80)->create(['sku' => 'SKU-PLENTY']);

        $this->actingAs($this->user)->get('/inventory')
            ->assertOk()
            ->assertSee('SKU-LOW')
            ->assertSee('SKU-PLENTY')
            ->assertSee('data-flag="low-stock"', false);

        $this->get('/inventory?low_stock=1')
            ->assertSee('SKU-LOW')
            ->assertDontSee('SKU-PLENTY');
    }

    #[Test]
    public function stock_cannot_be_set_through_the_product_form(): void
    {
        $this->actingAs($this->user)->post('/products', [
            'sku' => 'new-sku-1',
            'name' => 'New Product',
            'unit_cost' => '9.99',
            'is_active' => '1',
            'stock_quantity' => 999,
        ])->assertRedirect('/inventory');

        $product = Product::query()->where('sku', 'NEW-SKU-1')->sole();
        $this->assertSame(0, $product->stock_quantity);
        $this->assertSame('9.99', $product->unit_cost);
    }

    #[Test]
    public function suppliers_can_be_created_and_updated(): void
    {
        $this->actingAs($this->user)->post('/suppliers', [
            'code' => 'newco',
            'name' => 'NewCo Ltd',
            'email' => 'buy@newco.test',
            'is_active' => '1',
        ])->assertRedirect('/suppliers');

        $supplier = Supplier::query()->where('code', 'NEWCO')->sole();

        $this->put("/suppliers/{$supplier->id}", [
            'code' => 'NEWCO',
            'name' => 'NewCo Holdings',
            'is_active' => '0',
        ])->assertRedirect('/suppliers');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'NewCo Holdings', 'is_active' => false]);
        $this->get('/suppliers')->assertOk()->assertSee('NewCo Holdings');
    }
}
