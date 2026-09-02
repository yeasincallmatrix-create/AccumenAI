@extends('layouts.institute')
@section('title', 'Certificate — '.($certificate->certificate_number ?? ''))
@section('content')
<style>
    @page { size: A4 landscape; margin: 0; }
    /* Screen A4 paper preview */
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
    /* A4 paper on screen - page itself */
    @media screen {
        main.content { background: #e9ecef; min-height: calc(100vh - 56px); padding: 24px 16px !important; display: flex; flex-direction: column; align-items: center; }
        .certificate-sheet, .cert2-sheet, .cert3-sheet {
            width: 297mm !important; height: 210mm !important; max-width: none !important; min-height: 210mm !important;
            aspect-ratio: auto !important; flex-shrink: 0;
            box-shadow: 0 12px 40px rgba(0,0,0,.18), 0 1px 3px rgba(0,0,0,.1);
            margin: 0 auto !important;
        }
        .page-header, nav.breadcrumb { width: 297mm; max-width: 100%; margin-left: auto; margin-right: auto; }
        .cert-switcher { margin-top: 18px; }
        @media (max-width: 1100px) {
            .certificate-sheet, .cert2-sheet, .cert3-sheet { transform-origin: top center; transform: scale(calc(100vw / 320mm)); margin-bottom: calc((210mm * (100vw / 320mm) - 210mm)) !important; }
        }
    }
    .cert-switcher {
        display: flex; align-items: center; justify-content: center; gap: 12px;
        margin: 18px auto 18px; background: #fff; border: 1px solid rgba(13,110,253,.15);
        border-radius: 12px; padding: 8px 16px; box-shadow: 0 8px 24px rgba(13,110,253,.08); width: fit-content;
    }
    .cert-switcher-btn {
        width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; border: 1px solid rgba(13,110,253,.2); background: #fff; color: var(--bs-primary); cursor: pointer; transition: all .15s ease;
    }
    .cert-switcher-btn:hover { background: rgba(13,110,253,.08); }
    .cert-switcher-label { font-size: 12px; letter-spacing: 1px; color: #495057; text-transform: uppercase; white-space: nowrap; margin: 0 4px; }
    html.monetix-dark .cert-switcher { background: #1e1f22; border-color: rgba(255,255,255,.12); }
    html.monetix-dark .cert-switcher-btn { background: #1e1f22; border-color: rgba(255,255,255,.2); }
    html.monetix-dark .cert-switcher-label { color: #9aa0a6; }
    @media print {
        html, body { margin:0 !important; padding:0 !important; background:#fff !important; width:297mm !important; height:210mm !important; overflow:hidden !important; }
        @page { size: A4 landscape; margin:0 !important; }
        main.content, .content, .page-content, .container, .container-fluid, #app { background:#fff !important; padding:0 !important; margin:0 !important; width:297mm !important; height:210mm !important; max-width:none !important; overflow:hidden !important; }
        .topbar, .sidebar, .sidebar-backdrop, .page-header, .cert-switcher, nav.breadcrumb, .breadcrumb, nav[aria-label="breadcrumb"], header, footer, .page-header-actions { display:none !important; }
        .content { margin-left:0 !important; padding:0 !important; }
        .certificate-sheet, .cert2-sheet, .cert3-sheet { transform:none !important; margin:0 !important; box-shadow:none !important; border-radius:0 !important; position:absolute !important; top:0 !important; left:0 !important; right:0 !important; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('training.certificates.index') }}" class="text-decoration-none">Certificates</a></li>
        <li class="breadcrumb-item active">{{ $certificate->certificate_number ?? 'Certificate' }}</li>
    </ol>
</nav>
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Certificate</h4>
        <p class="page-header-desc">{{ $certificate->certificate_number ?? 'Certificate record' }} — Design {{ $template }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('training.certificates.index', ['batch_id' => $certificate->batch_id]) }}"><i class="bi bi-arrow-left me-1"></i> Back</a>
        <a class="btn btn-success btn-sm" href="{{ route('training.certificates.download', $certificate) }}"><i class="bi bi-download me-1"></i> Download PDF</a>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
    </div>
</div>

@if($template === 1)
    @include('admin.certificates._template1')
@elseif($template === 2)
    @include('admin.certificates._template2')
@else
    @include('admin.certificates._template3')
@endif

<div class="cert-switcher" role="group" aria-label="Certificate template switcher">
    <button class="cert-switcher-btn" type="button" id="certPrev" title="Previous (←)" aria-label="Previous"><i class="bi bi-arrow-left"></i></button>
    <span class="cert-switcher-label">Design {{ $template }} of {{ $templateCount }}</span>
    <button class="cert-switcher-btn" type="button" id="certNext" title="Next (→)" aria-label="Next"><i class="bi bi-arrow-right"></i></button>
</div>

@push('scripts')
<script>
(function(){
    'use strict';
    var total={{ $templateCount }};
    var current={{ $template }};
    function go(t){
        if(t<1) t=total;
        if(t>total) t=1;
        if(t===current) return;
        var url=new URL(window.location.href);
        url.searchParams.set('template', t);
        window.location.href=url.toString();
    }
    var prev=document.getElementById('certPrev');
    var next=document.getElementById('certNext');
    if(prev) prev.addEventListener('click', function(){ go(current-1); });
    if(next) next.addEventListener('click', function(){ go(current+1); });
    if(window.Monetix && Monetix.delegate){
        Monetix.delegate('keydown', null, function(e){
            if(e.key!=='ArrowLeft' && e.key!=='ArrowRight') return;
            var tag=(e.target && e.target.tagName)||'';
            if(e.metaKey||e.ctrlKey||e.altKey) return;
            if(tag==='INPUT'||tag==='TEXTAREA'||tag==='SELECT'||e.target.isContentEditable) return;
            e.preventDefault();
            go(e.key==='ArrowLeft'? current-1 : current+1);
        }, 'mtx-cert-nav-keys');
    }
})();
</script>
@endpush
@endsection
