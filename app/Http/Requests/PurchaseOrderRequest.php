<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseOrderRequest extends FormRequest
{
    public const MAX_LINES = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'order_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'items.*' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $items = $this->input('items');

                if (! is_array($items) || $items === []) {
                    return;
                }

                $ids = collect($items)
                    ->map(fn ($item) => is_array($item) ? ($item['product_id'] ?? null) : null)
                    ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
                    ->map(fn ($id) => (int) $id);

                if ($ids->isEmpty()) {
                    return;
                }

                $valid = Product::query()->active()->whereKey($ids->unique()->values())->pluck('id')->flip();

                foreach ($ids as $index => $id) {
                    if (! $valid->has($id)) {
                        $validator->errors()->add("items.{$index}.product_id", 'The selected product is invalid or inactive.');
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'items' => 'line items',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.unit_price' => 'unit price',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'A purchase order needs at least one line item.',
            'items.min' => 'A purchase order needs at least one line item.',
            'items.*.product_id.distinct' => 'Each product may only appear once per order.',
        ];
    }
}
