<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .metric-card .metric-icon { font-size: 2rem; opacity: .85; }
        .table td, .table th { vertical-align: middle; }
        .row-low-stock { --bs-table-bg: #fff3cd; }
    </style>
    @stack('styles')
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">
            <i class="bi bi-box-seam me-1"></i> {{ config('app.name') }}
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('dashboard')) active @endif" href="{{ route('dashboard') }}">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('purchase-orders.*')) active @endif" href="{{ route('purchase-orders.index') }}">Purchase Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('inventory.*', 'products.*')) active @endif" href="{{ route('inventory.index') }}">Inventory</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('suppliers.*')) active @endif" href="{{ route('suppliers.index') }}">Suppliers</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('purchase-orders.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i> New PO
                </a>
                <span class="navbar-text small"><i class="bi bi-person-circle"></i> {{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container pb-5">
    @include('partials.flash')
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
