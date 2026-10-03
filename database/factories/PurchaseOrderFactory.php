<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseOrderStatus::Draft,
            'order_date' => fake()->dateTimeBetween('-30 days')->format('Y-m-d'),
            'total_amount' => fake()->randomFloat(2, 100, 5000),
        ];
    }

    public function status(PurchaseOrderStatus $status): static
    {
        return $this->state(fn () => array_filter([
            'status' => $status,
            'approved_at' => in_array($status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Received], true) ? now() : null,
            'received_at' => $status === PurchaseOrderStatus::Received ? now() : null,
            'cancelled_at' => $status === PurchaseOrderStatus::Cancelled ? now() : null,
        ]));
    }

    public function received(): static
    {
        return $this->status(PurchaseOrderStatus::Received);
    }
}
