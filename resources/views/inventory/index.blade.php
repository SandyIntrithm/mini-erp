@extends('layouts.app')

@section('title', 'Inventory')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Inventory Overview</h1>
        <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Product</a>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-sm-6 col-md-4">
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search SKU or name">
        </div>
        <div class="col-auto d-flex align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="low_stock" @checked(request()->boolean('low_stock'))>
                <label class="form-check-label" for="low_stock">Low stock only (&lt; {{ $threshold }})</label>
            </div>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">Filter</button>
            @if (request()->hasAny(['search', 'low_stock']))
                <a href="{{ route('inventory.index') }}" class="btn btn-link">Reset</a>
            @endif
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th class="text-end">Stock</th>
                    <th class="text-end">Unit Cost</th>
                    <th>Flag</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($products as $product)
                    @php($low = $product->is_active && $product->isLowStock())
                    <tr @class(['row-low-stock' => $low, 'text-muted' => ! $product->is_active])>
                        <td class="font-monospace">{{ $product->sku }}</td>
                        <td>{{ $product->name }}</td>
                        <td class="text-end fw-semibold">{{ number_format($product->stock_quantity) }}</td>
                        <td class="text-end">{{ number_format((float) $product->unit_cost, 2) }}</td>
                        <td>
                            @if (! $product->is_active)
                                <span class="badge text-bg-light border">Inactive</span>
                            @elseif ($low)
                                <span class="badge text-bg-warning" data-flag="low-stock"><i class="bi bi-exclamation-triangle"></i> Low Stock</span>
                            @else
                                <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">OK</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No products found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $products->links() }}</div>
@endsection
