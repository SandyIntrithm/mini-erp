@extends('layouts.app')

@section('title', 'New Supplier')

@section('content')
    <h1 class="h3 mb-3">New Supplier</h1>
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('suppliers.store') }}">
                @include('suppliers._form')
            </form>
        </div>
    </div>
@endsection
