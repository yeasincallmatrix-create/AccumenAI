<div>
    <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" wire:submit.prevent="apply">
        <div>
            <label class="form-label mb-1">Range</label>
            <select class="form-select form-select-sm" wire:model.live="preset">
                @foreach ($presets as $option)
                    <option value="{{ $option }}">{{ ucwords(str_replace('_', ' ', $option)) }}</option>
                @endforeach
            </select>
        </div>
        @if ($preset === 'custom')
            <div>
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="from">
            </div>
            <div>
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="to">
            </div>
        @endif
        @if (count($branches) > 0)
            <div>
                <label class="form-label mb-1">Branch</label>
                <select class="form-select form-select-sm" wire:model.live="branchId">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label class="form-label mb-1">Fiscal year</label>
            <select class="form-select form-select-sm" wire:model.live="fiscalYearId">
                <option value="">— None —</option>
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy['id'] }}">{{ $fy['name'] }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
    </form>
</div>
