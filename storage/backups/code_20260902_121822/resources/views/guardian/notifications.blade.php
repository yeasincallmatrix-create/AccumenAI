@extends('guardian.layout')

@section('title', mawa_e('guardian.notifications_title'))

@section('content')
<h1 class="h4 mb-3">{{ mawa_e('guardian.notifications_title') }}</h1>

@if ($notifications->isEmpty())
    <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>{{ mawa_e('guardian.no_notifications') }}</div>
@else
    <div class="card">
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @foreach ($notifications as $notification)
                    <li class="list-group-item py-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center text-primary" style="width:38px;height:38px">
                                <i class="bi {{ $notification->category === 'certificate' ? 'bi-patch-check' : 'bi-megaphone' }}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $notification->title }}</div>
                                <div class="small mb-1">{{ $notification->message }}</div>
                                <div class="small text-body-secondary">
                                    <i class="bi bi-clock me-1"></i>{{ \Illuminate\Support\Carbon::parse($notification->created_at)->format('d M Y, h:i A') }}
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($notifications->hasPages())
                <div class="p-2">{{ $notifications->links() }}</div>
            @endif
        </div>
    </div>
@endif
@endsection