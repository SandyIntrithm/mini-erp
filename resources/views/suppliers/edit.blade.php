@extends('layouts.app')

@section('title', 'Edit '.$supplier->name)

@section('content')
    <h1 class="h3 mb-3">Edit Supplier <span class="text-muted fs-5">{{ $supplier->name }}</span></h1>
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
                @method('PUT')
                @include('suppliers._form')
            </form>
        </div>
    </div>
@endsection
