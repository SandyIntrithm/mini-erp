@csrf
<div class="row g-3">
    <div class="col-md-4">
        <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
        <input type="text" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" maxlength="50" required
               class="form-control font-monospace text-uppercase @error('sku') is-invalid @enderror">
        @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" maxlength="150" required
               class="form-control @error('name') is-invalid @enderror">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="unit_cost" class="form-label">Baseline Unit Cost <span class="text-danger">*</span></label>
        <input type="number" id="unit_cost" name="unit_cost" value="{{ old('unit_cost', $product->unit_cost) }}" min="0" step="0.01" required
               class="form-control @error('unit_cost') is-invalid @enderror">
        @error('unit_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Current Stock</label>
        <input type="text" class="form-control" value="{{ number_format($product->stock_quantity ?? 0) }}" disabled>
        <div class="form-text">Stock changes only when purchase orders are received.</div>
    </div>
    <div class="col-md-4 d-flex align-items-center">
        <div class="form-check form-switch mt-3">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $product->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea id="description" name="description" rows="3" maxlength="2000"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">Save Product</button>
    <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
