@csrf
<div class="row g-3">
    <div class="col-md-3">
        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" id="code" name="code" value="{{ old('code', $supplier->code) }}" maxlength="20" required
               class="form-control font-monospace text-uppercase @error('code') is-invalid @enderror">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-9">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" value="{{ old('name', $supplier->name) }}" maxlength="150" required
               class="form-control @error('name') is-invalid @enderror">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $supplier->email) }}" maxlength="150"
               class="form-control @error('email') is-invalid @enderror">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" maxlength="30"
               class="form-control @error('phone') is-invalid @enderror">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-9">
        <label for="address" class="form-label">Address</label>
        <input type="text" id="address" name="address" value="{{ old('address', $supplier->address) }}" maxlength="255"
               class="form-control @error('address') is-invalid @enderror">
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <div class="form-check form-switch mt-3">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $supplier->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>
<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">Save Supplier</button>
    <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
