<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\PurchaseOrderController;
use App\Http\Controllers\Web\SupplierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');

    Route::resource('products', ProductController::class)->only(['create', 'store', 'edit', 'update']);
    Route::resource('suppliers', SupplierController::class)->except(['show', 'destroy']);

    Route::resource('purchase-orders', PurchaseOrderController::class)->except('destroy');
    Route::patch('/purchase-orders/{purchase_order}/status', [PurchaseOrderController::class, 'updateStatus'])
        ->name('purchase-orders.status');
});
