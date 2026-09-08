@extends('layouts.institute')

@section('title', 'Regulatory Snapshot — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Regulatory Snapshot</h4>
        <p class="text-muted small mb-0">Census, outcomes and service volumes for statutory reporting.</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="from_date">From</label>
                <x-tdate-input name="from_date" :value="$fromDate" id="from_date" class="form-control" onchange="if(window.tdateReady&&window.tdateReady('from_date'))this.form.submit()" />
            </div>
            <div class="col-md-4">
                <label class="form-label" for="to_date">To</label>
                <x-tdate-input name="to_date" :value="$toDate" id="to_date" class="form-control" onchange="if(window.tdateReady&&window.tdateReady('to_date'))this.form.submit()" />
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">Admissions (period)</h6><h2 class="card-text">{{ $stats['admissions'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white"><div class="card-body">
            <h6 class="card-title">Discharges (period)</h6><h2 class="card-text">{{ $stats['discharges'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white"><div class="card-body">
            <h6 class="card-title">Deaths (period)</h6><h2 class="card-text">{{ $stats['deaths'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-4 mt-3">
        <div class="card bg-info text-dark"><div class="card-body">
            <h6 class="card-title">Current IPD Census</h6><h2 class="card-text">{{ $stats['current_ipd'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-4 mt-3">
        <div class="card bg-secondary text-white"><div class="card-body">
            <h6 class="card-title">Lab Orders (period)</h6><h2 class="card-text">{{ $stats['lab_orders'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-4 mt-3">
        <div class="card bg-warning text-dark"><div class="card-body">
            <h6 class="card-title">New Patients (period)</h6><h2 class="card-text">{{ $stats['new_patients'] }}</h2>
        </div></div>
    </div>
</div>
@endsection
