<?php

namespace App\Http\Requests;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(PurchaseOrderStatus::targetValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'The status must be one of: '.implode(', ', PurchaseOrderStatus::targetValues()).'.',
        ];
    }

    public function targetStatus(): PurchaseOrderStatus
    {
        return PurchaseOrderStatus::from($this->validated('status'));
    }
}
