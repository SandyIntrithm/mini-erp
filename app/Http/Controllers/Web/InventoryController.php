<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'low_stock' => ['nullable', 'boolean'],
        ]);

        $products = Product::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->trim()->value();
                $query->where(fn ($q) => $q
                    ->where('sku', 'like', $term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%'));
            })
            ->when($request->boolean('low_stock'), fn ($q) => $q->active()->lowStock())
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.index', [
            'products' => $products,
            'threshold' => Product::lowStockThreshold(),
        ]);
    }
}
