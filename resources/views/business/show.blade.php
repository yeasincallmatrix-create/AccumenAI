@extends('layouts.standalone', ['title' => $institute->name . ' — Business Profile'])

@section('page_title', $institute->name)

@section('content')
<style>
    .business-cover { height: 220px; background: linear-gradient(135deg, var(--bs-primary) 0%, #6f42c1 100%); border-radius: 16px; overflow: hidden; position: relative; }
    .business-cover img { width: 100%; height: 100%; object-fit: cover; }
    .business-cover-placeholder { display: flex; align-items: center; justify-content: center; height: 100%; color: rgba(255,255,255,.85); font-size: 1.1rem; }
    .business-avatar { width: 96px; height: 96px; border-radius: 16px; background: #fff; border: 3px solid #fff; box-shadow: 0 4px 16px rgba(0,0,0,.15); overflow: hidden; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 2rem; color: var(--bs-primary); margin-top: -48px; position: relative; z-index: 2; }
    .business-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .business-stats .stat-card { border-radius: 12px; border: 1px solid var(--bs-border-color); padding: 16px; text-align: center; }
    .business-stats .stat-number { font-size: 1.5rem; font-weight: 800; }
</style>

{{-- Cover --}}
<div class="business-cover mb-0">
    @if ($institute->cover_photo)
        <img src="{{ asset('storage/' . $institute->cover_photo) }}" alt="{{ $institute->name }} cover">
    @else
        <div class="business-cover-placeholder">
            <i class="bi bi-image me-2"></i> {{ $institute->name }}
        </div>
    @endif
</div>

<div class="d-flex gap-3 align-items-end mb-3 px-2">
    <div class="business-avatar">
        @if ($institute->logo)
            <img src="{{ asset('storage/' . $institute->logo) }}" alt="{{ $institute->name }} logo">
        @else
            {{ strtoupper(mb_substr($institute->name, 0, 1)) }}
        @endif
    </div>
    <div class="flex-grow-1 pb-2">
        <h1 class="h4 mb-0 d-flex align-items-center gap-2">
            {{ $institute->name }}
            @if ($institute->verified)
                <i class="bi bi-patch-check-fill text-primary" title="Verified"></i>
            @endif
            @if ($institute->status !== 'active')
                <span class="badge bg-warning text-dark text-uppercase" style="font-size:.65rem">{{ $institute->status }}</span>
            @endif
        </h1>
        @if ($institute->short_name)
            <div class="text-muted small">{{ $institute->short_name }}</div>
        @endif
        <div class="text-muted small">
            @if ($institute->industry) <span class="badge bg-light text-dark border text-capitalize">{{ $institute->industry }}</span> @endif
            @if ($institute->sub_industry) <span class="badge bg-light text-dark border text-capitalize">{{ str_replace('_',' ', $institute->sub_industry) }}</span> @endif
            @if ($institute->founded_year) <span class="ms-1"><i class="bi bi-calendar-event me-1"></i>{{ $institute->founded_year }}</span> @endif
            @if ($institute->institute_code) <span class="ms-2"><i class="bi bi-hash"></i>{{ $institute->institute_code }}</span> @endif
        </div>
    </div>
    <div class="pb-2 d-flex gap-2">
        @if ($institute->phone)
            <a href="tel:{{ $institute->phone }}" class="btn btn-primary btn-sm rounded-pill px-3"><i class="bi bi-telephone me-1"></i> {{ $institute->phone }}</a>
        @endif
    </div>
</div>

@if ($institute->status !== 'active')
    <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i> This business profile is currently <strong>{{ $institute->status }}</strong>.</div>
@endif

{{-- Name Holder Card View --}}
<div class="d-flex justify-content-center my-4">
    <x-name-holder-card
        :name="$institute->name"
        :subtitle="$institute->short_name ?: ($institute->industry ? ucwords(str_replace('_',' ', $institute->industry)) : null)"
        :logo="$institute->logo ? asset('storage/'.$institute->logo) : null"
        :verified="(bool) $institute->verified"
    />
</div>

@if (($institute->industry ?? '') === 'education')
{{-- Stats — education only --}}
<div class="row g-3 business-stats mb-4">
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-number">{{ number_format($studentsCount) }}</div>
            <div class="text-muted small">Students</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-number">{{ number_format($coursesCount) }}</div>
            <div class="text-muted small">Courses</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-number">{{ number_format($batchesCount) }}</div>
            <div class="text-muted small">Batches</div>
        </div>
    </div>
</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">

        @if ($institute->description)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>About</h2>
                    <div class="text-muted" style="white-space: pre-wrap;">{{ $institute->description }}</div>
                </div>
            </div>
        @endif

        @if (($institute->industry ?? '') === 'education' && $courses->isNotEmpty())
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3"><i class="bi bi-journal-bookmark me-2"></i>Courses</h2>
                    <div class="row g-3">
                        @foreach ($courses as $ic)
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="fw-semibold">{{ $ic->course->name ?? $ic->course_id }}</div>
                                    @if (!empty($ic->course->code))
                                        <div class="text-muted small">{{ $ic->course->code }}</div>
                                    @endif
                                    @if (!empty($ic->course->description))
                                        <div class="text-muted small mt-1" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ Str::limit($ic->course->description, 90) }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if ($branches->isNotEmpty())
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3"><i class="bi bi-diagram-3 me-2"></i>Branches</h2>
                    <div class="list-group list-group-flush">
                        @foreach ($branches as $branch)
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">{{ $branch->name }}</div>
                                    @if ($branch->address) <div class="text-muted small">{{ $branch->address }}</div> @endif
                                </div>
                                <span class="badge bg-light text-dark border text-uppercase">{{ $branch->status }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

    </div>

    <div class="col-lg-4">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-geo-alt me-2"></i>Contact & Address</h2>
                <ul class="list-unstyled mb-0 small">
                    @if ($institute->address)
                        <li class="mb-2"><i class="bi bi-geo me-2 text-muted"></i>{{ $institute->address }}
                            @if ($institute->postal_code) , {{ $institute->postal_code }} @endif
                        </li>
                    @endif
                    @if ($institute->adminLevel1 || $institute->adminLevel2 || $institute->adminLevel3)
                        <li class="mb-2"><i class="bi bi-map me-2 text-muted"></i>{{ collect([$institute->adminLevel3?->name, $institute->adminLevel2?->name, $institute->adminLevel1?->name])->filter()->implode(', ') }}</li>
                    @endif
                    @if ($institute->country)
                        <li class="mb-2"><i class="bi bi-globe me-2 text-muted"></i>{{ $institute->country }}</li>
                    @endif
                    @if ($institute->phone)
                        <li class="mb-2"><i class="bi bi-telephone me-2 text-muted"></i><a href="tel:{{ $institute->phone }}" class="text-decoration-none">{{ $institute->phone }}</a></li>
                    @endif
                    @if ($institute->whatsapp)
                        <li class="mb-2"><i class="bi bi-whatsapp me-2 text-muted"></i><a href="https://wa.me/{{ preg_replace('/[^0-9]/','',$institute->whatsapp) }}" target="_blank" class="text-decoration-none">{{ $institute->whatsapp }}</a></li>
                    @endif
                    @if ($institute->email)
                        <li class="mb-2"><i class="bi bi-envelope me-2 text-muted"></i><a href="mailto:{{ $institute->email }}" class="text-decoration-none">{{ $institute->email }}</a></li>
                    @endif
                    @if ($institute->website)
                        <li class="mb-2"><i class="bi bi-link-45deg me-2 text-muted"></i><a href="{{ Str::startsWith($institute->website,'http') ? $institute->website : 'https://'.$institute->website }}" target="_blank" class="text-decoration-none">{{ $institute->website }}</a></li>
                    @endif
                    @if ($institute->facebook)
                        <li class="mb-2"><i class="bi bi-facebook me-2 text-muted"></i><a href="{{ $institute->facebook }}" target="_blank" class="text-decoration-none">Facebook</a></li>
                    @endif
                    @if ($institute->youtube)
                        <li class="mb-2"><i class="bi bi-youtube me-2 text-muted"></i><a href="{{ $institute->youtube }}" target="_blank" class="text-decoration-none">YouTube</a></li>
                    @endif
                    @if ($institute->google_map_url)
                        <li class="mt-3"><a href="{{ $institute->google_map_url }}" target="_blank" class="btn btn-outline-primary btn-sm w-100 rounded-pill"><i class="bi bi-geo-alt me-1"></i> View on Map</a></li>
                    @endif
                </ul>
            </div>
        </div>

        @if ($institute->trade_license || $institute->registration_number || $institute->license_number)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3"><i class="bi bi-patch-check me-2"></i>Legal Info</h2>
                    <ul class="list-unstyled small mb-0">
                        @if ($institute->trade_license) <li class="mb-1"><span class="text-muted">Trade License:</span> {{ $institute->trade_license }}</li> @endif
                        @if ($institute->registration_number) <li class="mb-1"><span class="text-muted">Registration:</span> {{ $institute->registration_number }}</li> @endif
                        @if ($institute->license_number) <li class="mb-1"><span class="text-muted">License:</span> {{ $institute->license_number }}</li> @endif
                        @if ($institute->e_tin) <li class="mb-1"><span class="text-muted">E-TIN:</span> {{ $institute->e_tin }}</li> @endif
                    </ul>
                </div>
            </div>
        @endif

        <div class="d-grid gap-2">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-speedometer2 me-1"></i> Go to Dashboard</a>
            <div class="text-center small text-muted">Public business profile · /business/{{ $institute->slug }}</div>
        </div>

    </div>
</div>
@endsection
