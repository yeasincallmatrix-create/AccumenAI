<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(($suppliers ?? \App\Models\Party::withoutGlobalScopes()->where('institute_id',$institute->id)->whereIn('type',['supplier','both'])->orderBy('name')->limit(100)->get()) as $s)
                        <option value="{{ $s->id }}" {{ (string)request('supplier_id')===(string)$s->id ? 'selected':'' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Product</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @php $items = \App\Models\InventoryItem::withoutGlobalScopes()->where('institute_id',$institute->id)->orderBy('name')->limit(50)->get(); @endphp
                    @foreach($items as $it)
                        <option value="{{ $it->id }}" {{ (string)request('product_id')===(string)$it->id ? 'selected':'' }}>{{ $it->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="posted" {{ request('status')==='posted'?'selected':'' }}>Posted</option>
                    <option value="draft" {{ request('status')==='draft'?'selected':'' }}>Draft</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit">Filter</button>
                <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>
