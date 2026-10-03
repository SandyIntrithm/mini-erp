@extends('layouts.app')

@section('title', 'Edit '.$product->sku)

@section('content')
    <h1 class="h3 mb-3">Edit Product <span class="text-muted font-monospace fs-5">{{ $product->sku }}</span></h1>
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('products.update', $product) }}">
                @method('PUT')
                @include('products._form')
            </form>
        </div>
    </div>
@endsection
