@extends('layouts.institute')

@section('title', 'Radiology Order ' . $order->order_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0">
            <i class="bi bi-radioactive"></i> {{ $order->order_number }}
            @if($order->is_urgent)
                <span class="badge bg-danger ms-2">URGENT</span>
            @endif
            <span class="badge bg-{{ $order->statusColor() }} ms-1">{{ $order->statusLabel() }}</span>
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            @if($order->isPending() && $user && $user->hasPermission('medical_radiology.edit'))
                @if($order->status === 'ordered')
                    <form method="POST" action="{{ route('medical.radiology.orders.schedule', $order) }}" class="d-inline">
                        @csrf
                        <div class="input-group input-group-sm" style="width:260px;">
                            <input type="datetime-local" name="scheduled_at" class="form-control" required>
                            <button type="submit" class="btn btn-info text-white"><i class="bi bi-calendar-check"></i> Schedule</button>
                        </div>
                    </form>
                @endif
                @if($order->status === 'scheduled')
                    <form method="POST" action="{{ route('medical.radiology.orders.start', $order) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-play-circle"></i> Start Study</button>
                    </form>
                @endif
                @if($order->status === 'in_progress')
                    <form method="POST" action="{{ route('medical.radiology.orders.perform', $order) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle"></i> Mark Performed</button>
                    </form>
                @endif
            @endif
            @if($order->status === 'completed' && $user && $user->hasPermission('medical_radiology.report'))
                <a href="{{ route('medical.radiology.orders.show', $order) }}#report" class="btn btn-success btn-sm">
                    <i class="bi bi-file-medical"></i> Write Report
                </a>
            @endif
            @if($order->status === 'reported' && $user && $user->hasPermission('medical_radiology.verify') && !$order->verified_at)
                <form method="POST" action="{{ route('medical.radiology.orders.verify', $order) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-patch-check"></i> Verify Report</button>
                </form>
            @endif
            @if($user && $user->hasPermission('medical_radiology.edit'))
                <a href="{{ route('medical.radiology.orders.edit', $order) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif
            <a href="{{ route('medical.radiology.orders.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3">
        <!-- Patient & Study -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-person"></i> Patient & Study</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="140">Order #</th><td>{{ $order->order_number }}</td></tr>
                        <tr><th>Patient</th><td>{{ $order->patient?->name ?? '-' }}</td></tr>
                        <tr><th>Referring Doctor</th><td>{{ $order->doctor?->name ?? '-' }}</td></tr>
                        <tr><th>Modality</th><td><span class="badge bg-light text-dark">{{ $order->modalityLabel() }}</span></td></tr>
                        <tr><th>Study</th><td>{{ $order->fullStudyName() }}</td></tr>
                        <tr><th>Clinical Indication</th><td>{{ $order->clinical_indication ?? '-' }}</td></tr>
                        <tr><th>Contrast</th><td>{{ $order->is_contrast ? 'Yes (' . ($order->contrast_type ?? 'N/A') . ')' : 'No' }}</td></tr>
                        <tr><th>Fasting</th><td>{{ $order->is_fasting_required ? 'Required' : 'Not Required' }}</td></tr>
                        <tr><th>Fee</th><td>৳{{ number_format($order->fee, 2) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Scheduling & Workflow -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-clock-history"></i> Scheduling & Workflow</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="140">Status</th><td><span class="badge bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span></td></tr>
                        <tr><th>Scheduled At</th><td>{{ $order->scheduled_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                        <tr><th>Performed At</th><td>{{ $order->performed_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                        <tr><th>Performed By</th><td>{{ $order->performedBy?->name ?? '-' }}</td></tr>
                        <tr><th>Reported At</th><td>{{ $order->reported_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                        <tr><th>Radiologist</th><td>{{ $order->radiologist?->name ?? $order->radiologist_name ?? '-' }}</td></tr>
                        <tr><th>Verified At</th><td>{{ $order->verified_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                        <tr><th>Verified By</th><td>{{ $order->verifiedBy?->name ?? '-' }}</td></tr>
                        <tr><th>Payment</th><td><span class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($order->payment_status) }}</span></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Report -->
        <div class="col-md-12" id="report">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-file-medical"></i> Radiology Report</div>
                <div class="card-body">
                    @if($order->findings || $order->impression)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6>Technique</h6>
                                <p>{{ $order->technique ?? '-' }}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Findings</h6>
                                <p>{{ $order->findings ?? '-' }}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Impression</h6>
                                <p>{{ $order->impression ?? '-' }}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Recommendations</h6>
                                <p>{{ $order->recommendations ?? '-' }}</p>
                            </div>
                        </div>
                    @elseif($order->status === 'completed' && $user && $user->hasPermission('medical_radiology.report'))
                        <form method="POST" action="{{ route('medical.radiology.orders.report', $order) }}">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Technique</label>
                                    <textarea name="technique" class="form-control" rows="2">{{ old('technique') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Findings *</label>
                                    <textarea name="findings" class="form-control" rows="4" required>{{ old('findings') }}</textarea>
                                    @error('findings')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Impression *</label>
                                    <textarea name="impression" class="form-control" rows="3" required>{{ old('impression') }}</textarea>
                                    @error('impression')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Recommendations</label>
                                    <textarea name="recommendations" class="form-control" rows="3">{{ old('recommendations') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Radiologist Name</label>
                                    <input type="text" name="radiologist_name" class="form-control" value="{{ old('radiologist_name') }}">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Submit Report</button>
                                </div>
                            </div>
                        </form>
                    @else
                        <p class="text-muted">No report yet.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Images -->
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-images"></i> Images ({{ $order->images->count() }})</span>
                    @if($user && $user->hasPermission('medical_radiology.edit'))
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#uploadForm">
                            <i class="bi bi-upload"></i> Upload
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    @if($user && $user->hasPermission('medical_radiology.edit'))
                        <div class="collapse mb-3" id="uploadForm">
                            <form method="POST" action="{{ route('medical.radiology.orders.images.upload', $order) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label">Image/PDF (max 10MB)</label>
                                        <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Caption</label>
                                        <input type="text" name="caption" class="form-control" placeholder="Optional caption">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Upload</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endif

                    @if($order->images->isEmpty())
                        <p class="text-muted">No images uploaded.</p>
                    @else
                        <div class="row g-2">
                            @foreach($order->images as $image)
                                <div class="col-md-3 col-6">
                                    <div class="card h-100">
                                        @if(str_starts_with($image->mime_type, 'image/'))
                                            <img src="{{ Storage::url($image->file_path) }}" class="card-img-top" style="height:150px; object-fit:cover;" alt="{{ $image->original_filename }}">
                                        @else
                                            <div class="card-body text-center py-4">
                                                <i class="bi bi-file-pdf fs-2 text-danger"></i>
                                                <br><small>{{ $image->original_filename }}</small>
                                            </div>
                                        @endif
                                        <div class="card-body py-1 px-2">
                                            <small class="text-muted d-block">{{ $image->original_filename }}</small>
                                            @if($image->caption)
                                                <small>{{ $image->caption }}</small>
                                            @endif
                                            <small class="text-muted d-block">{{ round($image->file_size / 1024) }} KB</small>
                                        </div>
                                        @if($user && $user->hasPermission('medical_radiology.delete'))
                                            <div class="card-footer py-1 px-2">
                                                <form method="POST" action="{{ route('medical.radiology.orders.images.destroy', [$order, $image]) }}" class="d-inline" onsubmit="return confirm('Delete this image?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
