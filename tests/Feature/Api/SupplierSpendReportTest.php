<?php

namespace Tests\Feature\Api;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPurchaseOrders;
use Tests\TestCase;

class SupplierSpendReportTest extends TestCase
{
    use ManagesPurchaseOrders;
    use RefreshDatabase;

    #[Test]
    public function it_sums_received_orders_per_supplier_only(): void
    {
        $this->apiUser();
        $product = Product::factory()->create();
        $acme = Supplier::factory()->create(['name' => 'Acme']);
        $globex = Supplier::factory()->create(['name' => 'Globex']);
        $idle = Supplier::factory()->create(['name' => 'Idle Supplier']);

        $this->receivedOrder([[$product, 10, '10.00']], $acme);
        $this->receivedOrder([[$product, 1, '0.55']], $acme);
        $this->receivedOrder([[$product, 3, '100.00']], $globex);

        $this->createOrder([[$product, 1, '999.00']], $acme);
        $this->moveTo($this->createOrder([[$product, 1, '999.00']], $globex), PurchaseOrderStatus::Approved);
        $this->moveTo($this->createOrder([[$product, 1, '999.00']], $idle), PurchaseOrderStatus::Cancelled);

        $this->getJson('/api/reports/supplier-spend')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['supplier_id' => $globex->id, 'supplier_code' => $globex->code, 'supplier_name' => 'Globex', 'orders_count' => 1, 'total_spend' => '300.00'],
                    ['supplier_id' => $acme->id, 'supplier_code' => $acme->code, 'supplier_name' => 'Acme', 'orders_count' => 2, 'total_spend' => '100.55'],
                ],
                'meta' => [
                    'status' => 'RECEIVED',
                    'suppliers_count' => 2,
                    'grand_total' => '400.55',
                ],
            ]);
    }

    #[Test]
    public function it_returns_an_empty_report_when_nothing_was_received(): void
    {
        $this->apiUser();

        $this->getJson('/api/reports/supplier-spend')
            ->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['status' => 'RECEIVED', 'suppliers_count' => 0, 'grand_total' => '0.00']]);
    }

    #[Test]
    public function the_report_is_a_single_aggregate_query(): void
    {
        $this->apiUser();
        $product = Product::factory()->create();
        Supplier::factory()->count(5)->create()
            ->each(fn (Supplier $supplier) => $this->receivedOrder([[$product, 1, '1.00']], $supplier));

        DB::enableQueryLog();
        $this->getJson('/api/reports/supplier-spend')->assertOk()->assertJsonCount(5, 'data');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries, 'Supplier spend should be computed in one SQL statement.');
        $this->assertStringContainsStringIgnoringCase('group by', $queries[0]['query']);
    }
}
