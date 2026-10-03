<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    private const COLUMNS = ['id', 'sku', 'name', 'unit_cost', 'stock_quantity', 'is_active', 'updated_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->active()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->trim()->value();
                $query->where(fn ($q) => $q
                    ->where('sku', 'like', $term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%'));
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15), self::COLUMNS)
            ->withQueryString();

        return ProductResource::collection($products)
            ->additional(['meta' => ['low_stock_threshold' => Product::lowStockThreshold()]]);
    }

    public function lowStock(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->active()
            ->lowStock()
            ->orderBy('stock_quantity')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15), self::COLUMNS)
            ->withQueryString();

        return ProductResource::collection($products)
            ->additional(['meta' => ['low_stock_threshold' => Product::lowStockThreshold()]]);
    }
}
