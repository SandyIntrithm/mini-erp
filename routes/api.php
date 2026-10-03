<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.login');

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');

    Route::apiResource('purchase-orders', PurchaseOrderController::class)->except('destroy');
    Route::patch('/purchase-orders/{purchase_order}/status', [PurchaseOrderController::class, 'updateStatus'])
        ->name('purchase-orders.status');

    Route::get('/reports/supplier-spend', [ReportController::class, 'supplierSpend'])->name('reports.supplier-spend');
});
