@extends('layouts.institute')
@section('title','Application — HR')
@section('content')
<div class="standalone-heading">
    <h4>Application #{{ $application->id }} — {{ $application->candidateLead->first_name }} {{ $application->candidateLead->last_name }}</h4>
    <span class="badge text-bg-primary">{{ $application->current_stage }}</span>
    <a href="{{ route('hr.recruitment.applications') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h6>Candidate</h6>
            <div>{{ $application->candidateLead->first_name }} {{ $application->candidateLead->last_name }}</div>
            <div class="text-muted small">{{ $application->candidateLead->email }} — {{ $application->candidateLead->phone }}</div>
            <div class="mt-2">Vacancy: <strong>{{ $application->vacancy?->title ?? 'Direct' }}</strong></div>
            <div>Recruiter: {{ $application->recruiter?->email ?? '—' }}</div>
        </div>
        <div class="admin-card p-3 mt-3">
            <h6>Pipeline</h6>
            @if($canManage)
            <form method="POST" action="{{ route('hr.recruitment.applications.stage',$application) }}" class="row g-2 mb-3">
                @csrf
                <div class="col-7">
                    <select name="to_stage" class="form-select form-select-sm" required>
                        @foreach(['new','screening','shortlisted','interview','assessment','selected','rejected','hired','withdrawn'] as $stage)
                            <option value="{{ $stage }}" @selected($application->current_stage==$stage)>{{ $stage }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-5"><button type="submit" class="btn btn-primary btn-sm w-100">Move Stage</button></div>
                <div class="col-12"><input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes"></div>
            </form>
            @endif
            <h6>History</h6>
            @foreach($application->histories as $h)
                <div class="small border-bottom py-1">{{ $h->from_stage ?? '—' }} → <strong>{{ $h->to_stage }}</strong> by {{ $h->changer?->email ?? 'System' }} <span class="text-muted">{{ $h->created_at->format('Y-m-d H:i') }}</span> @if($h->notes)<div class="text-muted">{{ $h->notes }}</div>@endif</div>
            @endforeach
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h6>Interviews</h6>
            @foreach($application->interviews as $iv)
                <div class="small border-bottom py-2">
                    {{ $iv->scheduled_at->format('Y-m-d H:i') }} — {{ $iv->interview_type }} — {{ $iv->status }}
                    @if($iv->score) Score: {{ $iv->score }} @endif
                    @if($iv->feedback) <div class="text-muted">{{ $iv->feedback }}</div> @endif
                </div>
            @endforeach
            @if($canManage)
            <form method="POST" action="{{ route('hr.recruitment.interviews.store') }}" class="mt-2 row g-2">
                @csrf
                <input type="hidden" name="application_id" value="{{ $application->id }}">
                <div class="col-6"><input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required></div>
                <div class="col-6"><select name="interview_type" class="form-select form-select-sm"><option value="onsite">Onsite</option><option value="online">Online</option><option value="phone">Phone</option><option value="panel">Panel</option></select></div>
                <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-primary w-100">Schedule Interview</button></div>
            </form>
            @endif
        </div>
        <div class="admin-card p-3 mt-3">
            <h6>Offer</h6>
            @if($application->getOffer())
                <div>Salary: {{ $application->getOffer()->offered_salary }} — Status: <span class="badge text-bg-secondary">{{ $application->getOffer()->status }}</span></div>
                @if($canManage && $application->getOffer()->status==='draft')
                    <form method="POST" action="{{ route('hr.recruitment.offers.status',$application->getOffer()) }}" class="d-inline mt-2">@csrf<input type="hidden" name="status" value="sent"><button type="submit" class="btn btn-sm btn-outline-primary">Send Offer</button></form>
                @endif
            @else
                @if($canManage)
                <form method="POST" action="{{ route('hr.recruitment.offers.store') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="application_id" value="{{ $application->id }}">
                    <div class="col-6"><input type="number" step="0.01" name="offered_salary" class="form-control form-control-sm" placeholder="Offered Salary"></div>
                    <div class="col-6"><input type="date" name="joining_date" class="form-control form-control-sm"></div>
                    <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-primary w-100">Create Offer</button></div>
                </form>
                @endif
            @endif
        </div>
        @if($application->current_stage==='selected' && $canManage)
        <div class="admin-card p-3 mt-3">
            <h6>Hiring</h6>
            <form method="POST" action="{{ route('hr.recruitment.applications.hire',$application) }}">
                @csrf
                <button type="submit" class="btn btn-success btn-sm w-100">Hire Candidate (Create Employee)</button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
