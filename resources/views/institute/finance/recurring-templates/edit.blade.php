@extends('layouts.standalone')

@section('title', 'Edit Template ' . $template->template_number . ' — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Edit Template {{ $template->template_number }}</h4>
    <p>Update recurring template configuration.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('finance.recurring-templates.update', $template) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="name" value="{{ old('name', $template->name) }}" required maxlength="200">
            </div>
            <div class="col-md-3">
                <label class="form-label">Frequency <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="frequency" id="frequency" required>
                    @foreach (['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'semiannual', 'annual', 'custom'] as $f)
                        <option value="{{ $f }}" @selected(old('frequency', $template->frequency) === $f)>{{ ucfirst($f) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Every N</label>
                <input type="number" class="form-control form-control-sm" name="interval_count" value="{{ old('interval_count', $template->interval_count) }}" min="1" max="365">
            </div>
            <div class="col-md-3" id="cronGroup" style="{{ $template->frequency !== 'custom' ? 'display:none;' : '' }}">
                <label class="form-label">Custom Cron</label>
                <input type="text" class="form-control form-control-sm" name="custom_cron" value="{{ old('custom_cron', $template->custom_cron) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control form-control-sm" name="end_date" value="{{ old('end_date', $template->end_date?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Max Occurrences</label>
                <input type="number" class="form-control form-control-sm" name="max_occurrences" value="{{ old('max_occurrences', $template->max_occurrences) }}" min="1">
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="auto_post" id="auto_post" value="1" @checked(old('auto_post', $template->auto_post))>
                    <label class="form-check-label" for="auto_post">Auto-post</label>
                </div>
            </div>

            <div class="col-12"><hr><h6>Template Data</h6></div>

            <div class="col-12">
                <label class="form-label">Template Data (JSON)</label>
                <textarea class="form-control form-control-sm font-monospace" name="template_data" rows="10">{{ is_array(old('template_data', $template->template_data)) ? json_encode(old('template_data', $template->template_data), JSON_PRETTY_PRINT) : old('template_data', json_encode($template->template_data, JSON_PRETTY_PRINT)) }}</textarea>
                <small class="text-muted">JSON payload for this transaction type. Edit with care.</small>
            </div>

            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control form-control-sm" name="notes" rows="2">{{ old('notes', $template->notes) }}</textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Update template</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.recurring-templates.show', $template) }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script>
(function () {
    const frequency = document.getElementById('frequency');
    const cronGroup = document.getElementById('cronGroup');
    frequency.addEventListener('change', function () {
        cronGroup.style.display = this.value === 'custom' ? '' : 'none';
    });
})();
</script>
@endsection
