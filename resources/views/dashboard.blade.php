@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Executive Dashboard</h1>
        <span class="text-muted small">{{ now()->format('d M Y') }}</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card metric-card shadow-sm border-0 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase">Total Active Products</div>
                        <div class="fs-2 fw-bold" data-metric="active-products">{{ number_format($metrics['active_products']) }}</div>
                    </div>
                    <i class="bi bi-boxes metric-icon text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <a href="{{ route('inventory.index', ['low_stock' => 1]) }}" class="text-decoration-none text-reset">
                <div class="card metric-card shadow-sm border-0 h-100 @if($metrics['low_stock_count'] > 0) border-start border-4 border-warning @endif">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase">Low Stock Alerts</div>
                            <div class="fs-2 fw-bold @if($metrics['low_stock_count'] > 0) text-warning-emphasis @endif" data-metric="low-stock">
                                {{ number_format($metrics['low_stock_count']) }}
                            </div>
                            <div class="text-muted small">Below {{ $metrics['low_stock_threshold'] }} units</div>
                        </div>
                        <i class="bi bi-exclamation-triangle metric-icon text-warning"></i>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <div class="card metric-card shadow-sm border-0 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase">Total Expenditure</div>
                        <div class="fs-2 fw-bold" data-metric="expenditure">{{ number_format((float) $metrics['total_expenditure'], 2) }}</div>
                        <div class="text-muted small">Sum of RECEIVED orders</div>
                    </div>
                    <i class="bi bi-cash-stack metric-icon text-success"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Latest Purchase Orders</h2>
            <a href="{{ route('purchase-orders.index') }}" class="small">View all</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th class="text-center">Lines</th>
                    <th>Status</th>
                    <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($latestOrders as $order)
                    <tr>
                        <td><a href="{{ route('purchase-orders.show', $order) }}" class="fw-semibold">{{ $order->reference }}</a></td>
                        <td>{{ $order->supplier->name }}</td>
                        <td>{{ $order->order_date->format('d M Y') }}</td>
                        <td class="text-center">{{ $order->items_count }}</td>
                        <td>@include('partials.status-badge', ['status' => $order->status])</td>
                        <td class="text-end">{{ number_format((float) $order->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No purchase orders yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
