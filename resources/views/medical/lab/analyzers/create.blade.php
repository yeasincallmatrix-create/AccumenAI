@extends('layouts.institute')

@section('title', 'Add Analyzer — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Add Lab Analyzer</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.laboratory.analyzers.index') }}">Back</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('medical.laboratory.analyzers.store') }}">
            @csrf

            <h6 class="mb-3">Basic Info</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label" for="code">Code *</label>
                    <input type="text" id="code" name="code" value="{{ old('code') }}" class="form-control @error('code') is-invalid @enderror" required>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="name">Name *</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="manufacturer">Manufacturer</label>
                    <input type="text" id="manufacturer" name="manufacturer" value="{{ old('manufacturer') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="model">Model</label>
                    <input type="text" id="model" name="model" value="{{ old('model') }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="serial_no">Serial No</label>
                    <input type="text" id="serial_no" name="serial_no" value="{{ old('serial_no') }}" class="form-control">
                </div>
            </div>

            <h6 class="mb-3">Type &amp; Protocol</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label" for="instrument_type">Instrument Type *</label>
                    <select id="instrument_type" name="instrument_type" class="form-select" required>
                        @foreach(\App\Models\LabIntegration\LabAnalyzer::INSTRUMENT_TYPES as $type)
                            <option value="{{ $type }}" @selected(old('instrument_type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="protocol">Protocol *</label>
                    <select id="protocol" name="protocol" class="form-select" required>
                        @foreach(\App\Models\LabIntegration\LabAnalyzer::PROTOCOLS as $protocol)
                            <option value="{{ $protocol }}" @selected(old('protocol', 'astm') === $protocol)>{{ strtoupper($protocol) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="adapter_key">Adapter *</label>
                    <select id="adapter_key" name="adapter_key" class="form-select" required>
                        @foreach($adapterKeys as $key)
                            <option value="{{ $key }}" @selected(old('adapter_key') === $key)>{{ $key }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="adapter_version">Adapter Version</label>
                    <input type="text" id="adapter_version" name="adapter_version" value="{{ old('adapter_version', 'v1') }}" class="form-control">
                </div>
            </div>

            <h6 class="mb-3">Connection</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Connection Type *</label>
                    @foreach(\App\Models\LabIntegration\LabAnalyzer::CONNECTION_TYPES as $conn)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="connection_type" id="conn_{{ $conn }}" value="{{ $conn }}" @checked(old('connection_type', 'tcp') === $conn)>
                            <label class="form-check-label" for="conn_{{ $conn }}">{{ strtoupper($conn) }}</label>
                        </div>
                    @endforeach
                </div>
                <div class="col-md-9">
                    <div id="tcp_fields" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="host">Host</label>
                            <input type="text" id="host" name="host" value="{{ old('host') }}" class="form-control" placeholder="192.168.1.50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="port">Port</label>
                            <input type="number" id="port" name="port" value="{{ old('port', 5000) }}" class="form-control">
                        </div>
                    </div>
                    <div id="serial_fields" class="row g-3" style="display:none">
                        <div class="col-md-3">
                            <label class="form-label" for="serial_port">Serial Port</label>
                            <input type="text" id="serial_port" name="serial_port" value="{{ old('serial_port') }}" class="form-control" placeholder="COM3">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="baud_rate">Baud Rate</label>
                            <select id="baud_rate" name="baud_rate" class="form-select">
                                @foreach([9600, 19200, 38400, 57600, 115200] as $baud)
                                    <option value="{{ $baud }}" @selected((int) old('baud_rate', 9600) === $baud)>{{ $baud }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="parity">Parity</label>
                            <select id="parity" name="parity" class="form-select">
                                @foreach(['none', 'even', 'odd'] as $parity)
                                    <option value="{{ $parity }}" @selected(old('parity', 'none') === $parity)>{{ $parity }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="stop_bits">Stop Bits</label>
                            <select id="stop_bits" name="stop_bits" class="form-select">
                                <option value="1" @selected(old('stop_bits', 1) == 1)>1</option>
                                <option value="2" @selected(old('stop_bits') == 2)>2</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="data_bits">Data Bits</label>
                            <select id="data_bits" name="data_bits" class="form-select">
                                <option value="7" @selected(old('data_bits') == 7)>7</option>
                                <option value="8" @selected(old('data_bits', 8) == 8)>8</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <h6 class="mb-3">Capabilities</h6>
            <div class="row g-3 mb-4">
                @foreach(['result_upload' => 'Result Upload', 'worklist' => 'Worklist', 'query' => 'Query', 'bidirectional' => 'Bidirectional'] as $cap => $label)
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="capabilities[{{ $cap }}]" id="cap_{{ $cap }}" value="1" @checked(old("capabilities.{$cap}", $cap === 'result_upload'))>
                            <label class="form-check-label" for="cap_{{ $cap }}">{{ $label }}</label>
                        </div>
                    </div>
                @endforeach
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" value="1" @checked(old('is_enabled'))>
                        <label class="form-check-label" for="is_enabled">Enabled</label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">Create Analyzer</button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('input[name="connection_type"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.getElementById('tcp_fields').style.display = this.value === 'tcp' ? '' : 'none';
        document.getElementById('serial_fields').style.display = this.value === 'serial' ? '' : 'none';
    });
});
(function () {
    var checked = document.querySelector('input[name="connection_type"]:checked');
    if (checked) { checked.dispatchEvent(new Event('change')); }
})();
</script>
@endsection
