<?php

namespace Database\Seeders;

use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\TransitionPurchaseOrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(InventoryService $inventory, CreatePurchaseOrder $createOrder, TransitionPurchaseOrderStatus $transition): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => 'password', 'email_verified_at' => now()],
        );

        $suppliers = collect([
            ['code' => 'ACME', 'name' => 'Acme Industrial Supplies', 'email' => 'sales@acme.test', 'phone' => '+1 555 0100', 'address' => '12 Foundry Road, Springfield'],
            ['code' => 'GLOBEX', 'name' => 'Globex Components Ltd', 'email' => 'orders@globex.test', 'phone' => '+1 555 0101', 'address' => '400 Market Street, Shelbyville'],
            ['code' => 'INITECH', 'name' => 'Initech Office Solutions', 'email' => 'procurement@initech.test', 'phone' => '+1 555 0102', 'address' => '9 Tech Park, Capital City'],
            ['code' => 'UMBRELLA', 'name' => 'Umbrella Packaging Co', 'email' => 'hello@umbrella.test', 'phone' => '+1 555 0103', 'address' => '77 Harbour Way, Ogdenville'],
        ])->map(fn (array $data) => Supplier::query()->create($data));

        $catalog = [
            ['SKU-1001', 'Steel Bolt M8 (Box of 100)', '12.50', 250],
            ['SKU-1002', 'Steel Nut M8 (Box of 100)', '8.75', 180],
            ['SKU-1003', 'Hydraulic Hose 1/2"', '45.00', 6],
            ['SKU-1004', 'Industrial Safety Gloves', '4.20', 400],
            ['SKU-1005', 'Hard Hat (Class E)', '18.90', 3],
            ['SKU-1006', 'Barcode Scanner Battery', '29.99', 0],
            ['SKU-1007', 'Corrugated Box 60x40x40', '1.35', 1200],
            ['SKU-1008', 'Stretch Wrap Roll 500m', '22.40', 8],
            ['SKU-1009', 'Pallet Jack Wheel', '65.00', 14],
            ['SKU-1010', 'LED Work Light 50W', '39.50', 25],
            ['SKU-1011', 'Thermal Label Roll 4x6', '11.80', 9],
            ['SKU-1012', 'A4 Copy Paper (Ream)', '5.60', 75],
        ];

        $products = collect($catalog)->map(function (array $row) use ($inventory, $admin) {
            [$sku, $name, $cost, $opening] = $row;

            $product = Product::query()->create(['sku' => $sku, 'name' => $name, 'unit_cost' => $cost]);

            if ($opening > 0) {
                $inventory->increaseStock($product, $opening, StockMovement::TYPE_OPENING_BALANCE, $admin->id);
            }

            return $product;
        });

        $order = fn (int $supplier, array $lines, string $date) => $createOrder->handle([
            'supplier_id' => $suppliers[$supplier]->id,
            'order_date' => $date,
            'items' => array_map(fn (array $line) => [
                'product_id' => $products[$line[0]]->id,
                'quantity' => $line[1],
                'unit_price' => $line[2],
            ], $lines),
        ], $admin);

        $received1 = $order(0, [[0, 100, '12.00'], [1, 100, '8.50']], now()->subDays(20)->toDateString());
        $received2 = $order(1, [[2, 10, '44.00'], [8, 5, '62.50']], now()->subDays(12)->toDateString());
        $received3 = $order(0, [[3, 200, '4.00']], now()->subDays(9)->toDateString());
        $approved = $order(2, [[11, 50, '5.40'], [10, 30, '11.50']], now()->subDays(4)->toDateString());
        $cancelled = $order(3, [[6, 500, '1.30']], now()->subDays(3)->toDateString());
        $order(3, [[7, 20, '22.00'], [4, 15, '18.50'], [5, 10, '29.00']], now()->subDay()->toDateString());

        foreach ([$received1, $received2, $received3, $approved] as $po) {
            $transition->handle($po, PurchaseOrderStatus::Approved, $admin);
        }

        foreach ([$received1, $received2, $received3] as $po) {
            $transition->handle($po, PurchaseOrderStatus::Received, $admin);
        }

        $transition->handle($cancelled, PurchaseOrderStatus::Cancelled, $admin);
    }
}
