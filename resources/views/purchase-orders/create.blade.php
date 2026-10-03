@extends('layouts.app')

@section('title', 'New Purchase Order')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">New Purchase Order</h1>
        <span class="badge text-bg-secondary">Will be saved as DRAFT</span>
    </div>

    <form method="POST" action="{{ route('purchase-orders.store') }}" novalidate>
        @include('purchase-orders._form', [
            'submitLabel' => 'Create Purchase Order',
            'cancelUrl' => route('purchase-orders.index'),
        ])
    </form>
@endsection
