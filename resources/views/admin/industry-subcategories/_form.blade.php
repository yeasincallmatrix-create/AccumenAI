@csrf

@if ($errors->any())
    <div class="alert alert-danger py-2">
        @foreach ($errors->all() as $error)
            <div class="small">{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="admin-card p-4">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Industry <span class="text-danger">*</span></label>
            <select name="industry_key" class="form-select" required>
                <option value="">Select industry…</option>
                @foreach ($industries as $key)
                    <option value="{{ $key }}" {{ old('industry_key', $subcategory->industry_key ?? '') === $key ? 'selected' : '' }}>{{ $key }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Sub-Category Key <span class="text-danger">*</span></label>
            <input type="text" name="subcategory_key" class="form-control" required maxlength="60"
                   pattern="[a-z0-9_]+" title="lowercase letters, numbers, underscores"
                   placeholder="e.g. pharmacy" value="{{ old('subcategory_key', $subcategory->subcategory_key ?? '') }}">
            <div class="form-text">Unique per industry. Used by Layer 3 resolution.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Display Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required maxlength="100"
                   placeholder="e.g. Pharmacy" value="{{ old('name', $subcategory->name ?? '') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Icon (bi-*)</label>
            <input type="text" name="icon" class="form-control" maxlength="60"
                   placeholder="bi-capsule" value="{{ old('icon', $subcategory->icon ?? '') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" min="0" max="9999"
                   value="{{ old('sort_order', $subcategory->sort_order ?? 0) }}">
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2" maxlength="255">{{ old('description', $subcategory->description ?? '') }}</textarea>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                       {{ old('is_active', $subcategory->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Active (available for tenant assignment)</label>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> {{ $submitLabel ?? 'Save' }}</button>
    <a href="{{ route('admin.industry-subcategories.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
