@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Suppliers</h1>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Supplier</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th class="text-center">Orders</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($suppliers as $supplier)
                    <tr @class(['text-muted' => ! $supplier->is_active])>
                        <td class="font-monospace">{{ $supplier->code }}</td>
                        <td>{{ $supplier->name }}</td>
                        <td>{{ $supplier->email ?? '—' }}</td>
                        <td>{{ $supplier->phone ?? '—' }}</td>
                        <td class="text-center">{{ $supplier->purchase_orders_count }}</td>
                        <td>
                            @if ($supplier->is_active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-light border">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No suppliers yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $suppliers->links() }}</div>
@endsection
