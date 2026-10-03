<?php

namespace App\Exceptions;

use App\Enums\PurchaseOrderStatus;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseOrderStateException extends DomainException
{
    public static function invalidTransition(PurchaseOrderStatus $from, PurchaseOrderStatus $to): self
    {
        return new self("Cannot change purchase order status from {$from->value} to {$to->value}.");
    }

    public static function notEditable(PurchaseOrderStatus $status): self
    {
        return new self("Purchase order is {$status->value} and can no longer be modified. Only DRAFT orders can be edited.");
    }

    public static function immutable(PurchaseOrderStatus $status): self
    {
        return new self("Purchase order is {$status->value} and is immutable.");
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => ['status' => [$this->getMessage()]],
            ], 422);
        }

        return back()->with('error', $this->getMessage());
    }
}
