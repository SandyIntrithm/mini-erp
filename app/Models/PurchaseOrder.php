<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderStateException;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplier_id', 'created_by', 'order_date', 'notes', 'total_amount'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'DRAFT',
        'total_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PurchaseOrder $order) {
            $original = PurchaseOrderStatus::from($order->getRawOriginal('status'));

            if ($original->isFinal()) {
                throw PurchaseOrderStateException::immutable($original);
            }
        });

        static::deleting(function (PurchaseOrder $order) {
            $original = PurchaseOrderStatus::from($order->getRawOriginal('status'));

            if ($original->isFinal()) {
                throw PurchaseOrderStateException::immutable($original);
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeStatus(Builder $query, PurchaseOrderStatus $status): void
    {
        $query->where('status', $status->value);
    }

    protected function reference(): Attribute
    {
        return Attribute::get(fn () => 'PO-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT));
    }
}
