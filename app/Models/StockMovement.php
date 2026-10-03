<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'purchase_order_id', 'user_id', 'type', 'quantity', 'balance_after'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_PURCHASE_RECEIPT = 'purchase_receipt';

    public const TYPE_OPENING_BALANCE = 'opening_balance';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
