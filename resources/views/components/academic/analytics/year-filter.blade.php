@props(['filters', 'options', 'withFromTo' => false])

<form method="GET" class="mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label small mb-1">Academic Year</label>
            <select name="academic_year_id" class="form-select form-select-sm">
                <option value="">All years</option>
                @foreach ($options['years'] as $year)
                    <option value="{{ $year->id }}" @selected((string) ($filters['academic_year_id'] ?? '') === (string) $year->id)>{{ $year->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($withFromTo)
            <div class="col-auto">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
            </div>
        @endif
        <div class="col-auto">
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        </div>
    </div>
</form>