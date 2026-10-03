@extends('layouts.app')

@section('title', 'Edit '.$order->reference)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Edit {{ $order->reference }}</h1>
        @include('partials.status-badge', ['status' => $order->status])
    </div>

    <form method="POST" action="{{ route('purchase-orders.update', $order) }}" novalidate>
        @method('PUT')
        @include('purchase-orders._form', [
            'submitLabel' => 'Save Changes',
            'cancelUrl' => route('purchase-orders.show', $order),
        ])
    </form>
@endsection
