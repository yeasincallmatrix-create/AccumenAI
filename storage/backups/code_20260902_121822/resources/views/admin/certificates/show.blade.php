@extends('layouts.admin')

@section('title', 'Certificate — AccumenAI')

@section('content')
@php
    $student = $certificate->student;
    $course = $certificate->course;
    $institute = $certificate->institute;

    $studentName = strtoupper(trim($student?->full_name ?? ''));
    $courseName = strtoupper(trim($course?->name ?? ''));

    $guardian = null;
    if (! empty($student?->father_name)) {
        $guardianLabel = match (strtolower((string) $student->gender)) {
            'male' => 'Son of',
            'female' => 'Daughter of',
            default => 'Child of',
        };
        $guardian = $guardianLabel.' '.trim($student->father_name);
    }

    $instituteName = strtoupper(trim($institute?->name ?? ''));
    $tagline = trim($institute?->short_name ?? '');
    $initials = strtoupper(substr($instituteName, 0, 1) ?: 'A');
@endphp

<style>
    @page { size: A4 landscape; margin: 0; }
    @media screen {
        main.content { background: #e9ecef !important; padding: 24px !important; display: flex; flex-direction: column; align-items: center; }
        .certificate-sheet, .cert2-sheet, .cert3-sheet {
            width: 297mm !important; height: 210mm !important; min-height: 210mm !important;
            max-width: none !important; flex-shrink: 0; margin: 0 auto !important;
        }
        @media (max-width: 1280px) {
            .certificate-sheet, .cert2-sheet, .cert3-sheet { transform: scale(0.82); transform-origin: top center; margin-bottom: -37.8mm !important; }
        }
        @media (max-width: 992px) {
            .certificate-sheet, .cert2-sheet, .cert3-sheet { transform: scale(0.62); margin-bottom: -79.8mm !important; }
        }
        @media (max-width: 768px) {
            .certificate-sheet, .cert2-sheet, .cert3-sheet { transform: scale(0.46); margin-bottom: -113.4mm !important; }
        }
    }
    .cert-switcher {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin: 18px auto 18px;
        background: #fff;
        border: 1px solid rgba(13, 110, 253, .15);
        border-radius: 12px;
        padding: 8px 16px;
        box-shadow: 0 8px 24px rgba(13, 110, 253, .08);
        width: fit-content;
    }
    .cert-switcher-btn {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid rgba(13, 110, 253, .2);
        background: #fff;
        color: var(--bs-primary);
        cursor: pointer;
        transition: all .15s ease;
    }
    .cert-switcher-btn:hover { background: rgba(13, 110, 253, .08); }
    .cert-switcher-label {
        font-size: 12px;
        letter-spacing: 1px;
        color: #495057;
        text-transform: uppercase;
        white-space: nowrap;
        margin: 0 4px;
    }
    html.monetix-dark .cert-switcher { background: #1e1f22; border-color: rgba(255,255,255,.12); }
    html.monetix-dark .cert-switcher-btn { background: #1e1f22; border-color: rgba(255,255,255,.2); }
    html.monetix-dark .cert-switcher-label { color: #9aa0a6; }

    @media print { html, body { margin:0 !important; padding:0 !important; background:#fff !important; } main.content{ background:#fff !important; padding:0 !important; } .topbar, .sidebar, .sidebar-backdrop, .page-header, .cert-switcher, .card { display: none !important; } .content{margin-left:0 !important; padding:0 !important;} .certificate-sheet, .cert2-sheet, .cert3-sheet{ transform:none !important; margin:0 !important; box-shadow:none !important; } }
</style>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Certificate</h4>
        <p class="page-header-desc">{{ $certificate->certificate_number ?? 'Certificate record' }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.certificates.index') }}">
            <i class="bi bi-arrow-left me-1"></i> Back to Certificates
        </a>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>
</div>

@if ($template === 1)
    @include('admin.certificates._template1')
@elseif ($template === 2)
    @include('admin.certificates._template2')
@else
    @include('admin.certificates._template3')
@endif

<div class="card mb-3">
    <div class="card-header">Certificate Design</div>
    <div class="card-body">
        <p class="mb-2">Current template: <strong>Design {{ $certificate->template_id ?? $template }}</strong></p>
        <div class="btn-group" role="group" aria-label="Update template">
            @for($t = 1; $t <= 3; $t++)
                <button class="btn btn-outline-secondary btn-sm update-template {{ ($certificate->template_id ?? $template) == $t ? 'active' : '' }}" data-template="{{ $t }}" data-cert-id="{{ $certificate->id }}">
                    Design {{ $t }}
                </button>
            @endfor
        </div>
        <small class="text-muted d-block mt-2">Click to change the design for this certificate.</small>
    </div>
</div>

<div class="cert-switcher" role="group" aria-label="Certificate template switcher">
    <button class="cert-switcher-btn" type="button" id="certPrev" title="Previous template (←)" aria-label="Previous template">
        <i class="bi bi-arrow-left"></i>
    </button>
    <span class="cert-switcher-label">Template {{ $template }} of {{ $templateCount }}</span>
    <button class="cert-switcher-btn" type="button" id="certNext" title="Next template (→)" aria-label="Next template">
        <i class="bi bi-arrow-right"></i>
    </button>
    <button class="cert-switcher-btn" type="button" id="certSwap" title="Swap template" aria-label="Swap template">
        <i class="bi bi-arrow-repeat"></i>
    </button>
    <button class="cert-switcher-btn" type="button" id="certDefault" title="Reset to default template" aria-label="Reset to default template">
        <i class="bi bi-house-door"></i>
    </button>
</div>

@push('scripts')
<script>
(function () {
    'use strict';
    var total = {{ $templateCount }};
    var current = {{ $template }};
    function go(t) {
        if (t < 1) t = total;
        if (t > total) t = 1;
        if (t === current) return;
        var url = new URL(window.location.href);
        url.searchParams.set('template', t);
        window.location.href = url.toString();
    }
    var prev = document.getElementById('certPrev');
    var next = document.getElementById('certNext');
    var swap = document.getElementById('certSwap');
    var def = document.getElementById('certDefault');
    if (prev) prev.addEventListener('click', function () { go(current - 1); });
    if (next) next.addEventListener('click', function () { go(current + 1); });
    if (swap) swap.addEventListener('click', function () { go(current === 1 ? 2 : 1); });
    if (def) def.addEventListener('click', function () { go(1); });
    if (window.Monetix && Monetix.delegate) {
        Monetix.delegate('keydown', null, function (e) {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
            var tag = (e.target && e.target.tagName) || '';
            if (e.metaKey || e.ctrlKey || e.altKey) return;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target.isContentEditable) return;
            e.preventDefault();
            go(e.key === 'ArrowLeft' ? current - 1 : current + 1);
        }, 'mtx-cert-nav-keys');
    }

    // Update template via Super Admin API
    document.querySelectorAll('.update-template').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var template = this.getAttribute('data-template');
            var certId = this.getAttribute('data-cert-id');
            var token = document.querySelector('meta[name="csrf-token"]');
            token = token ? token.getAttribute('content') : '{{ csrf_token() }}';
            fetch('{{ url('admin/certificates') }}/' + certId + '/update-template', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ template_id: parseInt(template, 10) })
            }).then(function(response){ return response.json(); }).then(function(data){
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to update template.');
                }
            }).catch(function(){ alert('Failed to update template.'); });
        });
    });
})();
</script>
@endpush
@endsection