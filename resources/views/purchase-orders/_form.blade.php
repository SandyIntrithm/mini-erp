@php
    $rows = old('items', $lines);
    if (empty($rows)) {
        $rows = [['product_id' => '', 'quantity' => 1, 'unit_price' => '']];
    }
    $nextIndex = max(array_map('intval', array_keys($rows))) + 1;
@endphp

@csrf
@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix the highlighted errors.</strong>
        @error('items')<div>{{ $message }}</div>@enderror
    </div>
@endif

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                <select id="supplier_id" name="supplier_id" required class="form-select @error('supplier_id') is-invalid @enderror">
                    <option value="">— Select supplier —</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $order->supplier_id) === (string) $supplier->id)>
                            {{ $supplier->name }} ({{ $supplier->code }})
                        </option>
                    @endforeach
                </select>
                @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="order_date" class="form-label">Order Date</label>
                <input type="date" id="order_date" name="order_date"
                       value="{{ old('order_date', $order->order_date?->toDateString()) }}"
                       class="form-control @error('order_date') is-invalid @enderror">
                @error('order_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="notes" class="form-label">Notes</label>
                <textarea id="notes" name="notes" rows="2" maxlength="1000"
                          class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $order->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">Line Items</h2>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="bi bi-plus-lg"></i> Add line</button>
    </div>
    <div class="table-responsive">
        <table class="table mb-0" id="line-items">
            <thead class="table-light">
            <tr>
                <th style="min-width: 260px">Product</th>
                <th style="width: 130px">Quantity</th>
                <th style="width: 160px">Unit Price</th>
                <th style="width: 150px" class="text-end">Subtotal</th>
                <th style="width: 60px"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $i => $row)
                <tr class="line-row">
                    <td>
                        <select name="items[{{ $i }}][product_id]" required
                                class="form-select line-product @error("items.$i.product_id") is-invalid @enderror">
                            <option value="">— Select product —</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-price="{{ $product->unit_cost }}"
                                    @selected((string) ($row['product_id'] ?? '') === (string) $product->id)>
                                    {{ $product->sku }} — {{ $product->name }} (stock: {{ $product->stock_quantity }})
                                </option>
                            @endforeach
                        </select>
                        @error("items.$i.product_id")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </td>
                    <td>
                        <input type="number" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" min="1" step="1" required
                               class="form-control line-qty @error("items.$i.quantity") is-invalid @enderror">
                        @error("items.$i.quantity")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </td>
                    <td>
                        <input type="number" name="items[{{ $i }}][unit_price]" value="{{ $row['unit_price'] ?? '' }}" min="0" step="0.01" required
                               class="form-control line-price @error("items.$i.unit_price") is-invalid @enderror">
                        @error("items.$i.unit_price")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </td>
                    <td class="text-end fw-semibold line-subtotal">0.00</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove line" aria-label="Remove line">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr class="table-light">
                <th colspan="3" class="text-end">Grand Total</th>
                <th class="text-end fs-5" id="grand-total">0.00</th>
                <th></th>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ $submitLabel }}</button>
    <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">Cancel</a>
</div>

<template id="line-template">
    <tr class="line-row">
        <td>
            <select name="items[__INDEX__][product_id]" required class="form-select line-product">
                <option value="">— Select product —</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" data-price="{{ $product->unit_cost }}">
                        {{ $product->sku }} — {{ $product->name }} (stock: {{ $product->stock_quantity }})
                    </option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="items[__INDEX__][quantity]" value="1" min="1" step="1" required class="form-control line-qty"></td>
        <td><input type="number" name="items[__INDEX__][unit_price]" min="0" step="0.01" required class="form-control line-price"></td>
        <td class="text-end fw-semibold line-subtotal">0.00</td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove line" aria-label="Remove line">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
(function () {
    const tbody = document.querySelector('#line-items tbody');
    const template = document.getElementById('line-template');
    const grandTotalEl = document.getElementById('grand-total');
    let nextIndex = {{ $nextIndex }};

    const format = (cents) => (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const toCents = (value) => {
        const n = parseFloat(value);
        return Number.isFinite(n) && n >= 0 ? Math.round(n * 100) : 0;
    };

    function recalculate() {
        let total = 0;
        const seen = new Map();

        tbody.querySelectorAll('.line-row').forEach((row) => {
            const qty = parseInt(row.querySelector('.line-qty').value, 10);
            const subtotal = Number.isInteger(qty) && qty > 0 ? qty * toCents(row.querySelector('.line-price').value) : 0;
            row.querySelector('.line-subtotal').textContent = format(subtotal);
            total += subtotal;

            const select = row.querySelector('.line-product');
            if (select.value) {
                seen.set(select.value, (seen.get(select.value) || 0) + 1);
            }
        });

        tbody.querySelectorAll('.line-product').forEach((select) => {
            select.classList.toggle('border-warning', !!select.value && seen.get(select.value) > 1);
        });

        grandTotalEl.textContent = format(total);
        const rows = tbody.querySelectorAll('.line-row');
        rows.forEach((row) => row.querySelector('.remove-line').disabled = rows.length === 1);
    }

    document.getElementById('add-line').addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', nextIndex++);
        tbody.insertAdjacentHTML('beforeend', html);
        recalculate();
        tbody.lastElementChild.querySelector('.line-product').focus();
    });

    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-line');
        if (button && tbody.querySelectorAll('.line-row').length > 1) {
            button.closest('.line-row').remove();
            recalculate();
        }
    });

    tbody.addEventListener('change', (event) => {
        if (event.target.classList.contains('line-product')) {
            const option = event.target.selectedOptions[0];
            const priceInput = event.target.closest('.line-row').querySelector('.line-price');
            if (option && option.dataset.price !== undefined) {
                priceInput.value = option.dataset.price;
            }
        }
        recalculate();
    });

    tbody.addEventListener('input', recalculate);
    recalculate();
})();
</script>
@endpush
