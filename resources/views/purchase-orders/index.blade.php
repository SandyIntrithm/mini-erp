@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Purchase Orders</h1>
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Purchase Order</a>
    </div>

    <ul class="nav nav-pills mb-3 flex-wrap">
        <li class="nav-item">
            <a class="nav-link @if(! $currentStatus) active @endif" href="{{ route('purchase-orders.index') }}">All</a>
        </li>
        @foreach ($statuses as $status)
            <li class="nav-item">
                <a class="nav-link @if($currentStatus === $status) active @endif"
                   href="{{ route('purchase-orders.index', ['status' => $status->value]) }}">{{ $status->label() }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card shadow-sm border-0">
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
                @forelse ($orders as $order)
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
                        <td colspan="6" class="text-center text-muted py-4">No purchase orders found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
