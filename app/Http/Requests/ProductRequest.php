<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => strtoupper(trim((string) $this->input('sku'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['sku' => 'SKU'];
    }
}
