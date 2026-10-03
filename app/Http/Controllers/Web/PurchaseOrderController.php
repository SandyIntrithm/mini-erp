<?php

namespace App\Http\Controllers\Web;

use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\TransitionPurchaseOrderStatus;
use App\Actions\PurchaseOrders\UpdatePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderStatusRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(PurchaseOrderStatus::class)],
        ]);

        $status = $request->enum('status', PurchaseOrderStatus::class);

        return view('purchase-orders.index', [
            'orders' => PurchaseOrder::query()
                ->with('supplier:id,name')
                ->withCount('items')
                ->when($status, fn ($q) => $q->status($status))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'statuses' => PurchaseOrderStatus::cases(),
            'currentStatus' => $status,
        ]);
    }

    public function create(): View
    {
        return view('purchase-orders.create', $this->formData() + [
            'order' => new PurchaseOrder(['order_date' => now()]),
            'lines' => [],
        ]);
    }

    public function store(PurchaseOrderRequest $request, CreatePurchaseOrder $action): RedirectResponse
    {
        $order = $action->handle($request->validated(), $request->user());

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', "Purchase order {$order->reference} created as DRAFT.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        return view('purchase-orders.show', [
            'order' => $purchaseOrder->load(['supplier', 'creator:id,name', 'items.product:id,sku,name']),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if (! $purchaseOrder->status->isEditable()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', PurchaseOrderStateException::notEditable($purchaseOrder->status)->getMessage());
        }

        $purchaseOrder->load('items:id,purchase_order_id,product_id,quantity,unit_price');

        return view('purchase-orders.edit', $this->formData() + [
            'order' => $purchaseOrder,
            'lines' => $purchaseOrder->items
                ->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ])
                ->all(),
        ]);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, UpdatePurchaseOrder $action): RedirectResponse
    {
        $order = $action->handle($purchaseOrder, $request->validated());

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', "Purchase order {$order->reference} updated.");
    }

    public function updateStatus(
        UpdatePurchaseOrderStatusRequest $request,
        PurchaseOrder $purchaseOrder,
        TransitionPurchaseOrderStatus $action,
    ): RedirectResponse {
        $order = $action->handle($purchaseOrder, $request->targetStatus(), $request->user());

        $message = match ($order->status) {
            PurchaseOrderStatus::Received => "Purchase order {$order->reference} received. Inventory has been updated.",
            default => "Purchase order {$order->reference} is now {$order->status->value}.",
        };

        return redirect()->route('purchase-orders.show', $order)->with('success', $message);
    }

    private function formData(): array
    {
        return [
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'sku', 'name', 'unit_cost', 'stock_quantity']),
        ];
    }
}
