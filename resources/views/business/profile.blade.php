@extends('layouts.institute')

@section('title', $institute->name . ' — Business Profile')

@push('styles')
<style>
    .biz-cover { height: 200px; background: linear-gradient(135deg, var(--bs-primary, #0d6efd) 0%, #6f42c1 100%); border-radius: 16px; overflow: hidden; position: relative; }
    .biz-cover img { width: 100%; height: 100%; object-fit: cover; }
    .biz-cover-placeholder { display: flex; align-items: center; justify-content: center; height: 100%; color: rgba(255,255,255,.85); font-size: 1.05rem; }
    .biz-avatar { width: 88px; height: 88px; border-radius: 14px; background: #fff; border: 3px solid #fff; box-shadow: 0 4px 16px rgba(0,0,0,.15); overflow: hidden; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.8rem; color: var(--bs-primary, #0d6efd); margin-top: -44px; position: relative; z-index: 2; flex-shrink: 0; }
    .biz-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .biz-card { border: 1px solid var(--bs-border-color, #e9ecef); border-radius: 14px; background: #fff; }
    .biz-card .card-body { padding: 1.25rem; }
    .biz-badge-domain-academic { background: #0d6efd; color: #fff; }
    .biz-badge-domain-professional { background: #6f42c1; color: #fff; }
    .biz-badge-domain-other { background: #6c757d; color: #fff; }
    .biz-kv dt { font-weight: 600; color: #6c757d; font-size: .82rem; text-transform: uppercase; letter-spacing: .02em; }
    .biz-kv dd { font-size: .92rem; }
    .not-provided { color: #adb5bd; font-style: italic; }
    @media (max-width: 767.98px) { .biz-cover { height: 150px; } .biz-avatar { width: 72px; height: 72px; font-size: 1.5rem; margin-top: -36px; } }
</style>
@endpush

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Business Profile</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>Business Profile</h4>
    @if($canEdit)
        <a href="{{ route('settings.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="bi bi-pencil-square me-1"></i> Edit Profile</a>
    @endif
</div>

{{-- Cover --}}
<div class="biz-cover mb-0">
    @if ($institute->cover_photo)
        <img src="{{ asset('storage/' . $institute->cover_photo) }}" alt="{{ $institute->name }} cover">
    @else
        <div class="biz-cover-placeholder"><i class="bi bi-image me-2"></i>{{ $institute->name }}</div>
    @endif
</div>

{{-- Header row --}}
<div class="d-flex gap-3 align-items-end mb-4 px-1">
    <div class="biz-avatar">
        @if ($institute->logo_path_resolved)
            <img src="{{ $institute->logo_url }}" alt="{{ $institute->name }} logo">
        @else
            {{ strtoupper(mb_substr($institute->name, 0, 1)) }}
        @endif
    </div>
    <div class="flex-grow-1 pb-1">
        <h1 class="h5 mb-1 d-flex align-items-center gap-2 flex-wrap">
            {{ $institute->name }}
            @if ($institute->verified)
                <i class="bi bi-patch-check-fill text-primary" title="Verified"></i>
            @endif
            @if ($institute->status !== 'active')
                <span class="badge bg-warning text-dark text-uppercase" style="font-size:.62rem">{{ $institute->status }}</span>
            @else
                <span class="badge bg-success text-uppercase" style="font-size:.62rem">Active</span>
            @endif
            <span class="badge biz-badge-domain-{{ $domain }} text-uppercase" style="font-size:.62rem">{{ $domainLabel }}</span>
        </h1>
        <div class="text-muted small d-flex flex-wrap gap-2 align-items-center">
            @if ($institute->short_name) <span>{{ $institute->short_name }}</span> @endif
            @if ($institute->institute_code) <span><i class="bi bi-hash"></i>{{ $institute->institute_code }}</span> @endif
            @if ($institute->founded_year) <span><i class="bi bi-calendar-event me-1"></i>{{ $institute->founded_year }}</span> @endif
            <span class="badge bg-light text-dark border">{{ $industryLabel }}</span>
            <span class="badge bg-light text-dark border">{{ $subIndustryLabel }}</span>
        </div>
    </div>
</div>

@if ($institute->status !== 'active')
    <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i> This business is currently <strong>{{ $institute->status }}</strong>.</div>
@endif

{{-- Common Information Grid --}}
<div class="row g-3 mb-3">
    {{-- Business Information --}}
    <div class="col-lg-6">
        <div class="biz-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Business Information</h2>
                <dl class="row mb-0 biz-kv">
                    <dt class="col-sm-5">Business Name</dt><dd class="col-sm-7">{{ $institute->name }}</dd>
                    <dt class="col-sm-5">Short Name</dt><dd class="col-sm-7">{{ $institute->short_name ?: 'Not provided' }}</dd>
                    <dt class="col-sm-5">Business Code</dt><dd class="col-sm-7">{{ $institute->institute_code ?: 'Not provided' }}</dd>
                    <dt class="col-sm-5">Institute UID</dt>
                    <dd class="col-sm-7">
                        <div class="input-group input-group-sm" style="max-width: 260px;">
                            <input type="text" class="form-control" id="instituteUid" value="{{ $institute->uid }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyUid()">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <small class="text-muted">Stable 6-character identifier</small>
                    </dd>
                    <dt class="col-sm-5">Industry</dt><dd class="col-sm-7">{{ $industryLabel }}</dd>
                    <dt class="col-sm-5">Business Type</dt><dd class="col-sm-7">{{ $subIndustryLabel }}</dd>
                    <dt class="col-sm-5">Domain</dt><dd class="col-sm-7"><span class="badge biz-badge-domain-{{ $domain }} text-uppercase">{{ $domainLabel }}</span></dd>
                    <dt class="col-sm-5">Status</dt><dd class="col-sm-7"><span class="badge {{ $institute->status==='active'?'bg-success':'bg-warning text-dark' }} text-uppercase">{{ $institute->status }}</span></dd>
                    <dt class="col-sm-5">Verified</dt><dd class="col-sm-7">{{ $institute->verified ? 'Yes' : 'No' }}</dd>
                    <dt class="col-sm-5">Founded Year</dt><dd class="col-sm-7">{{ $institute->founded_year ?: 'Not provided' }}</dd>
                    <dt class="col-sm-5">Slug</dt><dd class="col-sm-7"><code class="small">{{ $institute->slug }}</code></dd>
                </dl>
                @if ($institute->description)
                    <hr>
                    <div class="small text-muted" style="white-space: pre-wrap;">{{ $institute->description }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Contact --}}
    <div class="col-lg-6">
        <div class="biz-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-telephone me-2 text-primary"></i>Contact Information</h2>
                <dl class="row mb-0 biz-kv">
                    <dt class="col-sm-5">Phone</dt>
                    <dd class="col-sm-7">
                        @if ($institute->phone)<a href="tel:{{ $institute->phone }}">{{ $institute->phone }}</a>@else<span class="not-provided">Not provided</span>@endif
                    </dd>
                    <dt class="col-sm-5">WhatsApp</dt>
                    <dd class="col-sm-7">
                        @if ($institute->whatsapp)<a href="https://wa.me/{{ preg_replace('/[^0-9]/','',$institute->whatsapp) }}" target="_blank">{{ $institute->whatsapp }}</a>@else<span class="not-provided">Not provided</span>@endif
                    </dd>
                    <dt class="col-sm-5">Email</dt>
                    <dd class="col-sm-7">
                        @if ($institute->email)<a href="mailto:{{ $institute->email }}">{{ $institute->email }}</a>@else<span class="not-provided">Not provided</span>@endif
                    </dd>
                    <dt class="col-sm-5">Website</dt>
                    <dd class="col-sm-7">
                        @if ($institute->website)<a href="{{ \Illuminate\Support\Str::startsWith($institute->website,'http') ? $institute->website : 'https://'.$institute->website }}" target="_blank">{{ $institute->website }}</a>@else<span class="not-provided">Not provided</span>@endif
                    </dd>
                    <dt class="col-sm-5">Facebook</dt>
                    <dd class="col-sm-7">
                        @if ($institute->facebook)<a href="{{ $institute->facebook }}" target="_blank">Facebook</a>@else<span class="not-provided">Not provided</span>@endif
                    </dd>
                    <dt class="col-sm-5">YouTube</dt>
                    <dd class="col-sm-7">
                        @if ($institute->youtube)<a href="{{ $institute->youtube }}" target="_blank">YouTube</a>@else<span class="not-provided">Not provided</span>@endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Location --}}
    <div class="col-lg-6">
        <div class="biz-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-geo-alt me-2 text-primary"></i>Address &amp; Location</h2>
                <dl class="row mb-0 biz-kv">
                    <dt class="col-sm-5">Country</dt><dd class="col-sm-7">{{ $institute->country ?: 'Not provided' }}</dd>
                    <dt class="col-sm-5">Division</dt><dd class="col-sm-7">{{ $institute->adminLevel1?->name ?: 'Not provided' }}</dd>
                    <dt class="col-sm-5">District</dt><dd class="col-sm-7">{{ $institute->adminLevel2?->name ?: 'Not provided' }}</dd>
                    <dt class="col-sm-5">Upazila / City</dt><dd class="col-sm-7">{{ $institute->adminLevel3?->name ?: 'Not provided' }}</dd>
                    <dt class="col-sm-5">Address</dt><dd class="col-sm-7">{{ $institute->address ?: 'Not provided' }}</dd>
                    <dt class="col-sm-5">Postal Code</dt><dd class="col-sm-7">{{ $institute->postal_code ?: 'Not provided' }}</dd>
                    <dt class="col-sm-5">Google Map</dt>
                    <dd class="col-sm-7">
                        @if ($institute->google_map_url)<a href="{{ $institute->google_map_url }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="bi bi-box-arrow-up-right me-1"></i>View on Map</a>@else<span class="not-provided">Not provided</span>@endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Domain & Business Structure + Branding + Settings --}}
    <div class="col-lg-6">
        <div class="biz-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-diagram-3 me-2 text-primary"></i>Domain &amp; Business Structure</h2>
                <dl class="row mb-2 biz-kv">
                    <dt class="col-sm-5">Industry</dt><dd class="col-sm-7">{{ $industryLabel }}</dd>
                    <dt class="col-sm-5">Business Type</dt><dd class="col-sm-7">{{ $subIndustryLabel }}</dd>
                    <dt class="col-sm-5">Domain</dt><dd class="col-sm-7"><span class="badge biz-badge-domain-{{ $domain }} text-uppercase">{{ $domainLabel }}</span> <span class="small text-muted ms-1">via InstituteDomain</span></dd>
                </dl>
                <hr>
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-palette me-2 text-primary"></i>Branding</h2>
                <dl class="row mb-2 biz-kv">
                    <dt class="col-sm-5">Logo</dt>
                    <dd class="col-sm-7">
                        @if ($institute->logo_path_resolved)
                            <img src="{{ $institute->logo_url }}" alt="logo" style="height:36px; border-radius:6px; border:1px solid #e9ecef; background:#fff; padding:2px;">
                        @elseif ($settings?->logo)
                            <img src="{{ asset('storage/'.$settings->logo) }}" alt="logo" style="height:36px; border-radius:6px; border:1px solid #e9ecef;">
                        @else <span class="not-provided">Not provided</span> @endif
                        @if($canEdit)
                            <form method="POST" action="{{ route('institute.logo.upload') }}" enctype="multipart/form-data" class="mt-2 d-flex gap-2 align-items-center">
                                @csrf
                                <input type="file" name="logo" accept="image/*" class="form-control form-control-sm" style="max-width:200px;" required>
                                <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                            </form>
                            @if($institute->logo_path_resolved)
                            <form method="POST" action="{{ route('institute.logo.remove') }}" class="mt-1">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove logo?')">Remove logo</button>
                            </form>
                            @endif
                            <small class="text-muted d-block mt-1">JPEG/PNG/GIF/SVG/WEBP, max 2MB. Stored per tenant.</small>
                        @endif
                    </dd>
                    <dt class="col-sm-5">Favicon</dt>
                    <dd class="col-sm-7">
                        @if ($settings?->favicon)
                            <img src="{{ asset('storage/'.$settings->favicon) }}" alt="favicon" style="height:20px;">
                        @else <span class="not-provided">Not provided</span> @endif
                    </dd>
                    <dt class="col-sm-5">Cover Photo</dt><dd class="col-sm-7">{{ $institute->cover_photo ? 'Set' : 'Not provided' }}</dd>
                </dl>
                <hr>
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-gear me-2 text-primary"></i>Business Settings</h2>
                <dl class="row mb-0 biz-kv">
                    <dt class="col-sm-5">Timezone</dt><dd class="col-sm-7">{{ $settings?->timezone ?? 'Not provided' }}</dd>
                    <dt class="col-sm-5">Language</dt><dd class="col-sm-7">{{ $settings?->language ?? 'Not provided' }}</dd>
                    <dt class="col-sm-5">Theme</dt><dd class="col-sm-7">{{ $settings?->theme ?? 'Not provided' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Legal & Registration --}}
    <div class="col-12">
        <div class="biz-card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-shield-check me-2 text-primary"></i>Legal &amp; Registration</h2>
                <div class="row g-3 biz-kv">
                    <div class="col-md-3"><dt>Trade License</dt><dd>{{ $institute->trade_license ?: 'Not provided' }}</dd></div>
                    <div class="col-md-3"><dt>License Number</dt><dd>{{ $institute->license_number ?: 'Not provided' }}</dd></div>
                    <div class="col-md-3"><dt>Registration No.</dt><dd>{{ $institute->registration_number ?: 'Not provided' }}</dd></div>
                    <div class="col-md-3"><dt>E-TIN</dt><dd>{{ $institute->e_tin ?: 'Not provided' }}</dd></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Branches --}}
    <div class="col-12">
        <div class="biz-card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-diagram-3 me-2 text-primary"></i>Branches <span class="badge bg-light text-dark border ms-2">{{ $branchesCount }}</span></h2>
                @if ($branches->isEmpty())
                    <p class="text-muted small mb-0">No branches available.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="text-muted small"><tr><th>Name</th><th>Address</th><th>Phone</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($branches as $branch)
                                    <tr>
                                        <td class="fw-semibold">
                                            {{ $branch->name }}
                                            @if($branch->is_principal)
                                                <span class="badge bg-primary ms-1" style="font-size:.62rem">Principal</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">{{ $branch->address ?: 'Not provided' }}</td>
                                        <td class="small">{{ $branch->phone ?: 'Not provided' }}</td>
                                        <td><span class="badge {{ $branch->status==='active'?'bg-success':'bg-secondary' }} text-uppercase" style="font-size:.68rem">{{ $branch->status }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Business-Type-Specific --}}
    @if ($domain === 'academic' && $academicData)
        <div class="col-12">
            <div class="biz-card">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-2"><i class="bi bi-mortarboard me-2 text-primary"></i>Academic Overview</h2>
                    <p class="small text-muted mb-3">Academic institution · {{ $subIndustryLabel }} · Education domain.</p>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-3 text-center"><div class="fw-bold fs-5">{{ $academicData['studentsCount'] ?? 0 }}</div><div class="small text-muted">Students</div></div></div>
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-3 text-center"><div class="fw-bold fs-5">{{ $academicData['batchesCount'] ?? 0 }}</div><div class="small text-muted">Batches</div></div></div>
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-3 text-center"><div class="fw-bold fs-5">{{ $academicData['coursesCount'] ?? 0 }}</div><div class="small text-muted">Courses</div></div></div>
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-3 text-center"><div class="fw-bold fs-5">{{ $academicData['subjectsCount'] ?? 0 }}</div><div class="small text-muted">Subjects</div></div></div>
                    </div>
                    @if (($academicData['recentCourses'] ?? collect())->isNotEmpty())
                        <h3 class="h6 fw-semibold mb-2 small text-muted text-uppercase">Assigned Courses</h3>
                        <div class="row g-2">
                            @foreach ($academicData['recentCourses'] as $ic)
                                <div class="col-md-4"><div class="border rounded-3 p-2 small"><span class="fw-semibold">{{ $ic->course->name ?? $ic->course_id }}</span> @if(!empty($ic->course->code))<span class="text-muted d-block">{{ $ic->course->code }}</span>@endif</div></div>
                            @endforeach
                        </div>
                    @else
                        <p class="small text-muted mb-0">No academic courses assigned yet.</p>
                    @endif
                </div>
            </div>
        </div>
    @elseif ($domain === 'professional' && $professionalData)
        <div class="col-12">
            <div class="biz-card">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-2"><i class="bi bi-easel me-2 text-primary"></i>Training Overview</h2>
                    <p class="small text-muted mb-3">{{ $subIndustryLabel }} · Training Center · Professional domain.</p>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-3 text-center"><div class="fw-bold fs-5">{{ $professionalData['coursesCount'] ?? 0 }}</div><div class="small text-muted">Courses</div></div></div>
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-3 text-center"><div class="fw-bold fs-5">{{ $professionalData['batchesCount'] ?? 0 }}</div><div class="small text-muted">Batches</div></div></div>
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-3 text-center"><div class="fw-bold fs-5">{{ $professionalData['subjectsCount'] ?? 0 }}</div><div class="small text-muted">Subjects</div></div></div>
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-3 text-center"><div class="fw-bold fs-5">{{ $professionalData['teachersCount'] ?? 0 }}</div><div class="small text-muted">Instructors</div></div></div>
                    </div>
                    @if (($professionalData['recentCourses'] ?? collect())->isNotEmpty())
                        <h3 class="h6 fw-semibold mb-2 small text-muted text-uppercase">Training Programs</h3>
                        <div class="row g-2">
                            @foreach ($professionalData['recentCourses'] as $ic)
                                <div class="col-md-4"><div class="border rounded-3 p-2 small"><span class="fw-semibold">{{ $ic->course->name ?? $ic->course_id }}</span> @if(!empty($ic->course->code))<span class="text-muted d-block">{{ $ic->course->code }}</span>@endif</div></div>
                            @endforeach
                        </div>
                    @endif
                    @if (($professionalData['recentBatches'] ?? collect())->isNotEmpty())
                        <h3 class="h6 fw-semibold mt-3 mb-2 small text-muted text-uppercase">Recent Batches</h3>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($professionalData['recentBatches'] as $b)
                                <span class="badge bg-light text-dark border">{{ $b->name }} <span class="text-muted">({{ $b->status }})</span></span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        {{-- Other industries: Retail / Manufacturing / Service / Transportation / Restaurant / Healthcare etc. --}}
        <div class="col-12">
            <div class="biz-card">
                <div class="card-body">
                    @php
                        $otherTitle = match($institute->industry) {
                            'retail' => 'Retail Overview',
                            'manufacturing' => 'Manufacturing Overview',
                            'service' => 'Service Business Overview',
                            'transportation', 'transport' => 'Transportation Overview',
                            'restaurant' => 'Restaurant Overview',
                            'healthcare' => 'Healthcare Overview',
                            'information_technology' => 'IT Business Overview',
                            'finance' => 'Finance Business Overview',
                            'real_estate' => 'Real Estate Overview',
                            default => 'Business Overview',
                        };
                        $otherIcon = match($institute->industry) {
                            'retail' => 'bi-shop',
                            'manufacturing' => 'bi-gear',
                            'service' => 'bi-tools',
                            'transportation', 'transport' => 'bi-truck',
                            'restaurant' => 'bi-cup-hot',
                            'healthcare' => 'bi-heart-pulse',
                            'information_technology' => 'bi-laptop',
                            'finance' => 'bi-bank',
                            default => 'bi-building',
                        };
                    @endphp
                    <h2 class="h6 fw-bold mb-2"><i class="bi {{ $otherIcon }} me-2 text-primary"></i>{{ $otherTitle }}</h2>
                    <p class="small text-muted mb-3">{{ $subIndustryLabel }} · {{ $industryLabel }} · <span class="badge biz-badge-domain-other text-uppercase">{{ $domainLabel }}</span> domain.</p>
                    <dl class="row mb-0 biz-kv">
                        <dt class="col-sm-4">Industry</dt><dd class="col-sm-8">{{ $industryLabel }}</dd>
                        <dt class="col-sm-4">Business Type</dt><dd class="col-sm-8">{{ $subIndustryLabel }}</dd>
                        <dt class="col-sm-4">Branches</dt><dd class="col-sm-8">{{ $branchesCount }}</dd>
                        <dt class="col-sm-4">Domain</dt><dd class="col-sm-8"><span class="badge biz-badge-domain-other text-uppercase">{{ $domainLabel }}</span></dd>
                    </dl>
                    <p class="small text-muted mt-3 mb-0">Academic-specific sections (subjects, placements, assessments) are hidden for this business type. All data shown belongs to the current institute only.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Subscription & Modules --}}
    <div class="col-lg-6">
        <div class="biz-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-credit-card me-2 text-primary"></i>Subscription</h2>
                @if ($subscription)
                    <dl class="row mb-2 biz-kv">
                        <dt class="col-sm-5">Package</dt><dd class="col-sm-7">{{ $package?->name ?? $package?->slug ?? 'Not provided' }}</dd>
                        <dt class="col-sm-5">Status</dt><dd class="col-sm-7"><span class="badge {{ $subscription->status==='active'?'bg-success':'bg-warning text-dark' }} text-uppercase">{{ $subscription->status }}</span></dd>
                        <dt class="col-sm-5">Start Date</dt><dd class="col-sm-7">{{ $subscription->start_date ?? 'Not provided' }}</dd>
                        <dt class="col-sm-5">End Date</dt><dd class="col-sm-7">{{ $subscription->end_date ?? 'Not provided' }}</dd>
                        <dt class="col-sm-5">Billing Cycle</dt><dd class="col-sm-7 text-capitalize">{{ $subscription->billing_cycle ?? 'Not provided' }}</dd>
                    </dl>
                @elseif ($package)
                    <dl class="row mb-2 biz-kv">
                        <dt class="col-sm-5">Package</dt><dd class="col-sm-7">{{ $package->name ?? $package->slug ?? 'Not provided' }}</dd>
                        <dt class="col-sm-5">Status</dt><dd class="col-sm-7"><span class="badge bg-light text-dark border text-uppercase">Active</span></dd>
                        <dt class="col-sm-5">Start Date</dt><dd class="col-sm-7"><span class="not-provided">Not provided</span></dd>
                        <dt class="col-sm-5">End Date</dt><dd class="col-sm-7"><span class="not-provided">Not provided</span></dd>
                    </dl>
                    <p class="small text-muted mb-0">Legacy package assignment — detailed subscription record not found.</p>
                @else
                    <p class="text-muted small mb-0">No subscription available.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="biz-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-puzzle me-2 text-primary"></i>Enabled Modules</h2>
                @if (empty($enabledModules))
                    <p class="text-muted small mb-0">No modules enabled.</p>
                @else
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach ($enabledModules as $mod)
                            <span class="badge bg-primary">{{ $mod }}</span>
                        @endforeach
                    </div>
                    <p class="small text-muted mb-0">{{ count($enabledModules) }} module(s) enabled.</p>
                @endif
                <hr>
                <h2 class="h6 fw-bold mb-2"><i class="bi bi-people me-2 text-primary"></i>Business Summary</h2>
                <dl class="row mb-0 biz-kv">
                    <dt class="col-sm-5">Branches</dt><dd class="col-sm-7">{{ $branchesCount }}</dd>
                    <dt class="col-sm-5">Users</dt><dd class="col-sm-7">{{ $usersCount }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="text-center small text-muted mt-4">Business Profile · {{ $institute->name }} · Domain: {{ $domainLabel }} · InstituteDomain authoritative</div>

@push('scripts')
<script>
function copyUid() {
    var uidInput = document.getElementById('instituteUid');
    if (!uidInput) return;
    var value = uidInput.value;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(function(){
            showCopySuccess();
        }).catch(function(){
            fallbackCopy(uidInput);
        });
    } else {
        fallbackCopy(uidInput);
    }
    function fallbackCopy(el){
        el.select();
        el.setSelectionRange(0, 99999);
        try { document.execCommand('copy'); } catch(e){}
        showCopySuccess();
    }
    function showCopySuccess(){
        var btn = document.querySelector('button[onclick="copyUid()"]');
        if (!btn) return;
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-secondary');
        setTimeout(function(){
            btn.innerHTML = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
        if (window.Monetix && Monetix.toast) Monetix.toast('Institute UID copied to clipboard','success');
    }
}
</script>
@endpush
@endsection
