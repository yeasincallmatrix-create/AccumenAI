@extends('layouts.institute')

@section('title', 'Clinical Report — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Clinical Report</h4>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="from_date">From</label>
                <input type="date" id="from_date" name="from_date" class="form-control" value="{{ $fromDate }}" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="to_date">To</label>
                <input type="date" id="to_date" name="to_date" class="form-control" value="{{ $toDate }}" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">New Patients</h6><h2 class="card-text">{{ $newPatients }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Appointments ({{ $appointmentStats['total'] }})</h6></div>
            <div class="card-body">
                <p class="mb-1">Completed: <strong>{{ $appointmentStats['completed'] }}</strong></p>
                <p class="mb-1">Cancelled: <strong>{{ $appointmentStats['cancelled'] }}</strong></p>
                <p class="mb-0">No-show: <strong>{{ $appointmentStats['no_show'] }}</strong></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Admissions ({{ $admissionStats['total'] }})</h6></div>
            <div class="card-body">
                <p class="mb-1">Active: <strong>{{ $admissionStats['active'] }}</strong></p>
                <p class="mb-1">Discharged: <strong>{{ $admissionStats['discharged'] }}</strong></p>
                <p class="mb-0">Expired: <strong>{{ $admissionStats['expired'] }}</strong></p>
            </div>
        </div>
    </div>
</div>
@endsection
