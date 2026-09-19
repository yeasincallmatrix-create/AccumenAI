@extends('layouts.institute')

@section('title', 'Edit Analyzer — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit {{ $analyzer->name }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.laboratory.analyzers.show', $analyzer) }}">Back</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('medical.laboratory.analyzers.update', $analyzer) }}">
            @csrf
            @method('PUT')

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Code (immutable)</label>
                    <input type="text" class="form-control" value="{{ $analyzer->code }}" readonly disabled>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="name">Name *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $analyzer->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="manufacturer">Manufacturer</label>
                    <input type="text" id="manufacturer" name="manufacturer" value="{{ old('manufacturer', $analyzer->manufacturer) }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="model">Model</label>
                    <input type="text" id="model" name="model" value="{{ old('model', $analyzer->model) }}" class="form-control">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="instrument_type">Instrument Type *</label>
                    <select id="instrument_type" name="instrument_type" class="form-select" required>
                        @foreach(\App\Models\LabIntegration\LabAnalyzer::INSTRUMENT_TYPES as $type)
                            <option value="{{ $type }}" @selected(old('instrument_type', $analyzer->instrument_type) === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="protocol">Protocol *</label>
                    <select id="protocol" name="protocol" class="form-select" required>
                        @foreach(\App\Models\LabIntegration\LabAnalyzer::PROTOCOLS as $protocol)
                            <option value="{{ $protocol }}" @selected(old('protocol', $analyzer->protocol) === $protocol)>{{ strtoupper($protocol) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="adapter_key">Adapter *</label>
                    <select id="adapter_key" name="adapter_key" class="form-select" required>
                        @foreach($adapterKeys as $key)
                            <option value="{{ $key }}" @selected(old('adapter_key', $analyzer->adapter_key) === $key)>{{ $key }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="connection_type">Connection *</label>
                    <select id="connection_type" name="connection_type" class="form-select" required>
                        @foreach(\App\Models\LabIntegration\LabAnalyzer::CONNECTION_TYPES as $conn)
                            <option value="{{ $conn }}" @selected(old('connection_type', $analyzer->connection_type) === $conn)>{{ strtoupper($conn) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="host">Host</label>
                    <input type="text" id="host" name="host" value="{{ old('host', $analyzer->host) }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="port">Port</label>
                    <input type="number" id="port" name="port" value="{{ old('port', $analyzer->port) }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="serial_port">Serial Port</label>
                    <input type="text" id="serial_port" name="serial_port" value="{{ old('serial_port', $analyzer->serial_port) }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" value="1" @checked(old('is_enabled', $analyzer->is_enabled))>
                        <label class="form-check-label" for="is_enabled">Enabled</label>
                    </div>
                </div>
            </div>

            <h6 class="mb-3">Capabilities</h6>
            <div class="row g-3 mb-3">
                @foreach(['result_upload' => 'Result Upload', 'worklist' => 'Worklist', 'query' => 'Query', 'bidirectional' => 'Bidirectional'] as $cap => $label)
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="capabilities[{{ $cap }}]" id="cap_{{ $cap }}" value="1" @checked(old("capabilities.{$cap}", $analyzer->capabilities[$cap] ?? false))>
                            <label class="form-check-label" for="cap_{{ $cap }}">{{ $label }}</label>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mb-3">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $analyzer->notes) }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>

        <hr>
        <form method="POST" action="{{ route('medical.laboratory.analyzers.destroy', $analyzer) }}" onsubmit="return confirm('Delete this analyzer?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">Delete Analyzer</button>
        </form>
    </div>
</div>
@endsection
