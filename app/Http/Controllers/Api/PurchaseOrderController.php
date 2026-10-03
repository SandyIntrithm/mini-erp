<?php

namespace App\Http\Controllers\Api;

use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\TransitionPurchaseOrderStatus;
use App\Actions\PurchaseOrders\UpdatePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderStatusRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(PurchaseOrderStatus::class)],
            'supplier_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->status(PurchaseOrderStatus::from($request->input('status'))))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return PurchaseOrderResource::collection($orders);
    }

    public function store(PurchaseOrderRequest $request, CreatePurchaseOrder $action): JsonResponse
    {
        $order = $action->handle($request->validated(), $request->user());

        return (new PurchaseOrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder->load(['supplier', 'items.product']));
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, UpdatePurchaseOrder $action): PurchaseOrderResource
    {
        return new PurchaseOrderResource($action->handle($purchaseOrder, $request->validated()));
    }

    public function updateStatus(
        UpdatePurchaseOrderStatusRequest $request,
        PurchaseOrder $purchaseOrder,
        TransitionPurchaseOrderStatus $action,
    ): PurchaseOrderResource {
        $order = $action->handle($purchaseOrder, $request->targetStatus(), $request->user());

        return new PurchaseOrderResource($order->load(['supplier', 'items.product']));
    }
}
