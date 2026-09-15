@extends('layouts.institute')

@section('title', 'Print Prescription — AccumenAI')

@section('content')
<style>
  * { box-sizing: border-box; }
  .rx-print-wrap {
    display: flex;
    justify-content: center;
    padding: 24px 12px;
  }
  .rx-page {
    background: #fff;
    width: 100%;
    max-width: 21cm;
    padding: 14mm 12mm;
    box-shadow: 0 0 6px rgba(0,0,0,0.15);
  }
  @page { margin: 12mm; }
  @media print {
    .topbar, .sidebar, .sidebar-backdrop, .skeleton-loader, footer, .no-print { display: none !important; }
    body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
    .layout { display: block !important; margin: 0 !important; padding: 0 !important; }
    main.content { padding: 0 !important; margin: 0 !important; }
    main.content > :not(.rx-print-wrap):not(script):not(style):not(link):not(meta) { display: none !important; }
    .rx-print-wrap { padding: 0; display: block; }
    .rx-page { max-width: none; width: auto; box-shadow: none; padding: 0; }
  }

  .clinic-header {
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 2px solid #111;
    padding-bottom: 8px;
  }
  .clinic-logo {
    width: 56px; height: 56px; flex: 0 0 auto;
    display: flex; align-items: center; justify-content: center;
  }
  .clinic-logo img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
  .clinic-info { flex: 1 1 auto; text-align: center; }
  .clinic-info .clinic-name { font-size: 19px; font-weight: bold; letter-spacing: 0.3px; margin: 0 0 2px; }
  .clinic-info .clinic-sub { font-size: 11px; color: #333; margin: 0; }
  .doctor-header { flex: 0 0 auto; text-align: right; font-size: 11px; min-width: 140px; }
  .doctor-header .doc-name { font-weight: bold; font-size: 13px; }

  .rx-meta {
    display: flex; justify-content: space-between;
    font-size: 11.5px; padding: 6px 0; border-bottom: 1px solid #999;
  }
  .rx-meta span strong { font-weight: bold; }

  .patient-box { border: 1px solid #111; margin-top: 8px; padding: 6px 10px; }
  .patient-grid { font-size: 11.5px; line-height: 1.6; }
  .patient-grid .field { display: inline; white-space: nowrap; margin-right: 16px; }
  .patient-grid .field strong { font-weight: 600; }
  .patient-address-row { margin-top: 4px; font-size: 11.5px; }

  .clinical-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 0; margin-top: 10px; border: 1px solid #111; }
  .clinical-section { padding: 8px 10px; }
  .clinical-section:first-child { border-right: 1px solid #111; }
  .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #333; margin: 0 0 4px; padding-bottom: 2px; }
  .soap-block { margin-bottom: 10px; break-inside: avoid; page-break-inside: avoid; }
  .soap-block:last-child { margin-bottom: 0; }
  .soap-block .label { font-weight: bold; font-size: 10.5px; text-transform: uppercase; color: #333; margin-bottom: 2px; }
  .soap-block .value { font-size: 11.5px; white-space: pre-wrap; }

  .rx-heading { font-size: 20px; font-weight: bold; font-family: Georgia, "Times New Roman", serif; margin: 0 0 6px; }
  .rx-item { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px dashed #ccc; break-inside: avoid; page-break-inside: avoid; }
  .rx-item:last-child { border-bottom: none; }
  .rx-item-head { display: flex; align-items: baseline; gap: 6px; }
  .rx-num { font-weight: bold; font-size: 13px; min-width: 16px; }
  .rx-name { font-weight: bold; font-size: 14px; }
  .rx-strength { font-size: 12px; color: #333; }
  .rx-generic { font-size: 10.5px; color: #555; font-style: italic; margin: 1px 0 4px 22px; }
  .rx-dosage-line { margin-left: 22px; font-size: 12px; font-weight: 600; display: flex; flex-wrap: wrap; gap: 4px 8px; }
  .rx-dosage-line .sep { color: #999; font-weight: normal; }
  .rx-route-line { margin-left: 22px; font-size: 11px; color: #333; }
  .rx-instruction { margin-left: 22px; font-size: 11px; margin-top: 2px; }
  .rx-instruction strong { font-weight: 600; }
  .rx-prn { margin-left: 22px; font-size: 10.5px; color: #555; margin-top: 2px; }
  .rx-empty { font-size: 11px; color: #777; font-style: italic; }

  .below-sections { margin-top: 10px; }
  .info-section { border: 1px solid #111; border-top: none; padding: 8px 10px; break-inside: avoid; page-break-inside: avoid; }
  .info-section .section-title { border-bottom: 1px solid #333; }
  .info-list { margin: 0; padding-left: 18px; font-size: 11.5px; }
  .info-list li { margin-bottom: 2px; }
  .followup-row { display: flex; gap: 20px; font-size: 11.5px; flex-wrap: wrap; }
  .followup-row .fu-date strong { font-weight: bold; }

  .rx-footer { margin-top: 26px; display: flex; justify-content: space-between; align-items: flex-end; break-inside: avoid; page-break-inside: avoid; }
  .footer-note { font-size: 10px; color: #666; max-width: 60%; }
  .signature-block { text-align: center; min-width: 200px; }
  .signature-line { border-top: 1px solid #111; margin-top: 34px; padding-top: 4px; }
  .signature-block .doc-name { font-weight: bold; font-size: 12.5px; }
  .signature-block .doc-detail { font-size: 10.5px; color: #333; }

  .rx-print-bar { max-width: 21cm; margin: 0 auto 8px; text-align: right; }
</style>

<div class="rx-print-bar no-print">
    <button class="btn btn-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print / Save as PDF
    </button>
    <a class="btn btn-outline-primary btn-sm" href="{{ route('medical.prescriptions.show', $prescription) }}">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="rx-print-wrap">
    <div class="rx-page">

        {{-- HEADER --}}
        <header class="clinic-header" id="print-letterhead">
            <div class="clinic-logo">
                @if(!empty($prescription->institute->logo_url))
                    <img src="{{ $prescription->institute->logo_url }}" alt="Logo" onerror="this.remove()">
                @endif
            </div>
            <div class="clinic-info">
                <p class="clinic-name">{{ $prescription->institute->name ?? 'Hospital' }}</p>
                @if(!empty($prescription->institute->address))
                    <p class="clinic-sub">{{ $prescription->institute->address }}</p>
                @endif
                @php($lhContact = implode(' · ', array_filter([$prescription->institute->phone ?? null, $prescription->institute->email ?? null, $prescription->institute->website ?? null])))
                @if($lhContact !== '')
                    <p class="clinic-sub">{{ $lhContact }}</p>
                @endif
            </div>
            <div class="doctor-header">
                <div class="doc-name">{{ $doctor->full_name ?? $doctor->name ?? 'N/A' }}</div>
                @if(!empty($doctorProfile->qualification))<div>{{ $doctorProfile->qualification }}</div>@endif
                @if(!empty($doctorProfile->specialty))<div>{{ $doctorProfile->specialty->name }}</div>@endif
                @if(!empty($doctorProfile->department))<div>{{ $doctorProfile->department->name }}</div>@endif
                @if(!empty($doctorProfile->registration_number))<div>BMDC Reg: {{ $doctorProfile->registration_number }}</div>@endif
            </div>
        </header>

        @if($prescription->isAmendment())
            <div style="background:#fff3cd; padding:8px 12px; border:1px solid #ffc107; margin-bottom:12px; border-radius:4px;">
                <strong>AMENDED PRESCRIPTION (v{{ $prescription->version }})</strong><br>
                Original Rx: {{ $prescription->parent->prescription_number ?? '—' }}
                ({{ $prescription->parent->prescription_date?->format('d M Y') ?? '—' }})<br>
                Reason: {{ $prescription->amendment_reason }}
            </div>
        @endif

        {{-- RX META --}}
        <div class="rx-meta">
            <span><strong>Rx No:</strong> {{ clinical_no($prescription->prescription_number) }} <small>(v{{ $prescription->version }})</small></span>
            <span><strong>Date:</strong> {{ $prescription->prescription_date?->format('d M Y') ?? '—' }}</span>
        </div>

        {{-- PATIENT INFO --}}
        <section class="patient-box" aria-label="Patient information">
            <div class="patient-grid">
                <span class="field"><strong>MR:</strong> {{ clinical_no($patient->mr_number ?? 'N/A') }}</span>
                <span class="field"><strong>Name:</strong> {{ $patient->full_name ?? 'N/A' }}</span>
                <span class="field"><strong>Age:</strong> {{ $patient->age ?? '—' }} yrs</span>
                <span class="field"><strong>Sex:</strong> {{ ucfirst($patient->gender ?? '—') }}</span>
                <span class="field"><strong>Phone:</strong> {{ $patient->phone ?? '—' }}</span>
                @if(!empty($patient->blood_group))
                    <span class="field"><strong>Blood:</strong> {{ $patient->blood_group }}</span>
                @endif
            </div>
            @if(!empty($patient->present_address))
                <div class="patient-address-row"><strong>Address:</strong> {{ $patient->present_address }}</div>
            @endif
        </section>

        <div class="soap-block" id="print-vitals" style="border:1px solid #111; padding:6px 10px; margin-top:8px;">
            <div class="label">Latest Vital Signs</div>
            @if($latestVitals)
            <div style="font-size:11px; display:flex; flex-wrap:wrap; gap:2px 16px; margin-top:4px;">
                @if(!empty($latestVitals->temperature))<span><strong>Temp:</strong> {{ $latestVitals->temperature }}°F</span>@endif
                @if(!empty($latestVitals->blood_pressure_systolic))<span><strong>BP:</strong> {{ $latestVitals->blood_pressure_systolic }}/{{ $latestVitals->blood_pressure_diastolic }}</span>@endif
                @if(!empty($latestVitals->pulse))<span><strong>Pulse:</strong> {{ $latestVitals->pulse }}/min</span>@endif
                @if(!empty($latestVitals->heart_rate))<span><strong>HR:</strong> {{ $latestVitals->heart_rate }}/min</span>@endif
                @if(!empty($latestVitals->respiratory_rate))<span><strong>RR:</strong> {{ $latestVitals->respiratory_rate }}/min</span>@endif
                @if(!empty($latestVitals->spo2))<span><strong>SpO2:</strong> {{ $latestVitals->spo2 }}%</span>@endif
                @if(!empty($latestVitals->blood_sugar))<span><strong>Sugar:</strong> {{ $latestVitals->blood_sugar }} mmol/L</span>@endif
                @if(!empty($latestVitals->weight))<span><strong>Wt:</strong> {{ $latestVitals->weight }} kg</span>@endif
                @if(!empty($latestVitals->height))<span><strong>Ht:</strong> {{ $latestVitals->height }} cm</span>@endif
                @if(!empty($latestVitals->pain_score))<span><strong>Pain:</strong> {{ $latestVitals->pain_score }}/10</span>@endif
            </div>
            @else
            <div style="font-size:11px; color:#999;">No vitals recorded</div>
            @endif
        </div>

        <div class="clinical-grid">
        {{-- SOAP CLINICAL NOTES + INVESTIGATIONS + ADVICE --}}
        <div class="clinical-section">
            <div class="soap-block" id="print-complaints">
                <div class="label">Chief Complaints</div>
                <div class="value">{{ $prescription->chief_complaints ?? '—' }}</div>
            </div>
            <div class="soap-block" id="print-findings">
                <div class="label">Examination Findings</div>
                <div class="value">{{ $prescription->examination_findings ?? '—' }}</div>
            </div>
            <div class="soap-block" id="print-diagnosis">
                <div class="label">Diagnosis</div>
                <div class="value">{{ $prescription->diagnosis ?? '—' }}</div>
            </div>
            <div class="soap-block" id="print-investigations">
                <div class="label">Investigations</div>
                <div class="value">{{ $prescription->investigations ?? '—' }}</div>
            </div>
            <div class="soap-block" id="print-advice">
                <div class="label">Advice</div>
                <div class="value">{{ $prescription->advice ?? '—' }}</div>
            </div>
        </div>

        {{-- Rx / MEDICINE LIST --}}
        <div class="clinical-section">
            <p class="rx-heading">℞</p>

            @forelse($items as $i => $item)
                @php $isDisc = method_exists($item, 'isDiscontinued') && $item->isDiscontinued(); @endphp
                <div class="rx-item" style="{{ $isDisc ? 'text-decoration:line-through; opacity:0.6;' : '' }}">
                    <div class="rx-item-head">
                        <span class="rx-num">{{ $i + 1 }}.</span>
                        <span class="rx-name">{{ $item->medicine_name ?? $item->display_name_snapshot ?? 'Medicine' }}</span>
                        @if($isDisc)
                            <span style="color:#dc3545; font-size:10px; font-weight:600; margin-left:6px;">DISCONTINUED</span>
                        @endif
                        @if(!empty($item->strength_snapshot) || !empty($item->dosage_form_snapshot))
                            <span class="rx-strength">{{ $item->strength_snapshot }} {{ $item->dosage_form_snapshot }}</span>
                        @endif
                    </div>
                    @if(mawa_dgda_enabled() && !empty($item->dgda_code))
                        <div class="rx-generic">DGDA: {{ $item->dgda_code }}</div>
                    @endif
                    <div class="rx-dosage-line">
                        @if(!empty($item->dosage))<span>{{ $item->dosage }}</span><span class="sep">—</span>@endif
                        @if(!empty($item->frequency))<span>{{ $item->frequency }}</span><span class="sep">—</span>@endif
                        @if($item->duration_days)<span>{{ $item->duration_days }} days</span>@endif
                    </div>
                    @if(!empty($item->route_snapshot) || $item->quantity)
                        <div class="rx-route-line">
                            @if(!empty($item->route_snapshot)){{ $item->route_snapshot }} &middot; @endif
                            Qty: {{ $item->quantity ?? '—' }}
                        </div>
                    @endif
                    @if(!empty($item->special_instructions))
                        <div class="rx-instruction"><strong>Note:</strong> {{ $item->special_instructions }}</div>
                    @endif
                </div>
            @empty
                <p class="rx-empty">No medicines prescribed.</p>
            @endforelse
        </div>
        </div>

        @if($prescription->follow_up_date)
        <div class="below-sections">
                <section class="info-section" aria-label="Follow-up">
                    <p class="section-title">Follow-up</p>
                    <div class="followup-row">
                        <span class="fu-date"><strong>Date:</strong> {{ $prescription->follow_up_date->format('d M Y') }}</span>
                    </div>
                </section>
        </div>
        @endif

        {{-- FOOTER --}}
        <div class="rx-footer">
            <div class="footer-note">
                <p>This prescription is generated for the named patient only. Please follow dosage instructions exactly as prescribed. Contact the clinic for any questions.</p>
                @if(!empty($qr))
                    <div id="print-qr-block" style="margin-top:8px; text-align:center; width:80px;">
                        <img src="{{ $qr }}" alt="Verification QR" width="80" height="80" style="display:block; margin:0 auto;">
                        <small style="margin:0; padding:0; line-height:1; margin-top:-2px; display:block;">Scan to verify</small>
                    </div>
                @endif
            </div>
            <div class="signature-block">
                <div class="signature-line">
                    <div class="doc-name">{{ $doctor->full_name ?? $doctor->name ?? 'N/A' }}</div>
                    @if(!empty($doctorProfile->qualification))<div class="doc-detail">{{ $doctorProfile->qualification }}</div>@endif
                    @if(!empty($doctorProfile->specialty))<div class="doc-detail">{{ $doctorProfile->specialty->name }}</div>@endif
                    @if(!empty($doctorProfile->registration_number))<div class="doc-detail">BMDC Reg. No: {{ $doctorProfile->registration_number }}</div>@endif
                </div>
                @if($prescription->signed_at)
                    <div id="print-signed-line" style="margin-top:4px; font-size:10px; color:#666;">
                        Signed {{ $prescription->signed_at->format('d M Y, h:i A') }}
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
(function () {
    var prefs = {};
    try { prefs = JSON.parse(localStorage.getItem('rxPrintPrefs') || '{}'); } catch (e) { prefs = {}; }
    function on(key) { return prefs[key] !== false; }
    var map = {
        'print-letterhead': 'letterhead',
        'print-qr-block': 'qr',
        'print-signed-line': 'signed',
        'print-advice': 'advice',
        'print-complaints': 'complaints',
        'print-findings': 'findings',
        'print-diagnosis': 'diagnosis',
        'print-investigations': 'investigations',
        'print-vitals': 'vitals'
    };
    Object.keys(map).forEach(function (id) {
        var el = document.getElementById(id);
        if (el && !on(map[id])) el.style.display = 'none';
    });

    if (!on('letterhead')) {
        var marginVal = prefs['margin'] !== undefined ? prefs['margin'] : 12;
        var inches = (marginVal / 10).toFixed(1);
        var rxPage = document.querySelector('.rx-page');
        if (rxPage) rxPage.style.paddingTop = inches + 'in';
    }

    if (new URLSearchParams(window.location.search).get('auto_print') === '1') {
        var queueUrl = '{{ route("medical.queue.react") }}';
        window.addEventListener('afterprint', function () {
            window.location.href = queueUrl;
        });
        setTimeout(function () { window.print(); }, 400);
    }
})();
</script>
@endpush
@endsection
