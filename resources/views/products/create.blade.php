@extends('layouts.app')

@section('title', 'New Product')

@section('content')
    <h1 class="h3 mb-3">New Product</h1>
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('products.store') }}">
                @include('products._form')
            </form>
        </div>
    </div>
@endsection
