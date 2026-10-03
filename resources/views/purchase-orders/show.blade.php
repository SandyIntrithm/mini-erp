@extends('layouts.app')

@section('title', $order->reference)

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
            <h1 class="h3 mb-0">{{ $order->reference }}</h1>
            @include('partials.status-badge', ['status' => $order->status])
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if ($order->status->isEditable())
                <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif

            @foreach ($order->status->allowedTransitions() as $next)
                <form method="POST" action="{{ route('purchase-orders.status', $order) }}" class="m-0"
                      @if ($next->isFinal())
                          onsubmit="return confirm('{{ $next->actionLabel() }} {{ $order->reference }}? This cannot be undone.');"
                      @endif>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $next->value }}">
                    <button type="submit" class="btn {{ $next->actionButtonClass() }}">{{ $next->actionLabel() }}</button>
                </form>
            @endforeach
        </div>
    </div>

    @if ($order->status->isFinal())
        <div class="alert alert-light border small">
            <i class="bi bi-lock"></i> This order is <strong>{{ $order->status->value }}</strong> and can no longer be modified.
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 text-muted text-uppercase">Supplier</h2>
                    <div class="fw-semibold">{{ $order->supplier->name }}</div>
                    <div class="small text-muted font-monospace">{{ $order->supplier->code }}</div>
                    @if ($order->supplier->email)<div class="small">{{ $order->supplier->email }}</div>@endif
                    @if ($order->supplier->phone)<div class="small">{{ $order->supplier->phone }}</div>@endif
                    @if ($order->supplier->address)<div class="small">{{ $order->supplier->address }}</div>@endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 text-muted text-uppercase">Order Summary</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Order date</dt><dd class="col-7">{{ $order->order_date->format('d M Y') }}</dd>
                        <dt class="col-5">Created by</dt><dd class="col-7">{{ $order->creator?->name ?? '—' }}</dd>
                        <dt class="col-5">Created at</dt><dd class="col-7">{{ $order->created_at->format('d M Y H:i') }}</dd>
                        @if ($order->approved_at)
                            <dt class="col-5">Approved at</dt><dd class="col-7">{{ $order->approved_at->format('d M Y H:i') }}</dd>
                        @endif
                        @if ($order->received_at)
                            <dt class="col-5">Received at</dt><dd class="col-7">{{ $order->received_at->format('d M Y H:i') }}</dd>
                        @endif
                        @if ($order->cancelled_at)
                            <dt class="col-5">Cancelled at</dt><dd class="col-7">{{ $order->cancelled_at->format('d M Y H:i') }}</dd>
                        @endif
                        <dt class="col-5">Total amount</dt><dd class="col-7 fw-bold fs-5">{{ number_format((float) $order->total_amount, 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Line Items</h2></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Subtotal</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td class="font-monospace">{{ $item->product->sku }}</td>
                        <td>{{ $item->product->name }}</td>
                        <td class="text-end">{{ number_format($item->quantity) }}</td>
                        <td class="text-end">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr class="table-light">
                    <th colspan="4" class="text-end">Grand Total</th>
                    <th class="text-end">{{ number_format((float) $order->total_amount, 2) }}</th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if ($order->notes)
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase">Notes</h2>
                <p class="mb-0" style="white-space: pre-line">{{ $order->notes }}</p>
            </div>
        </div>
    @endif
@endsection
