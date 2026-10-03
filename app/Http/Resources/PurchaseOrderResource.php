<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'order_date' => $this->order_date?->toDateString(),
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            'items_count' => $this->whenCounted('items'),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
