<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Prescription {{ clinical_no($prescription->prescription_number) }}</title>
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; font-family: DejaVu Sans, Arial, sans-serif; color: #111; font-size: 12px; line-height: 1.45; }

  .clinic-header { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #111; padding-bottom: 8px; }
  .clinic-logo { width: 56px; height: 56px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; }
  .clinic-logo img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
  .clinic-info { flex: 1 1 auto; text-align: center; }
  .clinic-info .clinic-name { font-size: 19px; font-weight: bold; letter-spacing: 0.3px; margin: 0 0 2px; }
  .clinic-info .clinic-sub { font-size: 11px; color: #333; margin: 0; }
  .doctor-header { flex: 0 0 auto; text-align: right; font-size: 11px; min-width: 140px; }
  .doctor-header .doc-name { font-weight: bold; font-size: 13px; }

  .rx-meta { display: flex; justify-content: space-between; font-size: 11.5px; padding: 6px 0; border-bottom: 1px solid #999; }
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
  .soap-block { margin-bottom: 10px; }
  .soap-block:last-child { margin-bottom: 0; }
  .soap-block .label { font-weight: bold; font-size: 10.5px; text-transform: uppercase; color: #333; margin-bottom: 2px; }
  .soap-block .value { font-size: 11.5px; white-space: pre-wrap; }

  .rx-heading { font-size: 20px; font-weight: bold; font-family: Georgia, "Times New Roman", serif; margin: 0 0 6px; }
  .rx-item { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px dashed #ccc; }
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
  .rx-empty { font-size: 11px; color: #777; font-style: italic; }

  .below-sections { margin-top: 10px; }
  .info-section { border: 1px solid #111; border-top: none; padding: 8px 10px; }
  .info-section .section-title { border-bottom: 1px solid #333; }
  .info-list { margin: 0; padding-left: 18px; font-size: 11.5px; }
  .info-list li { margin-bottom: 2px; }
  .followup-row { display: flex; gap: 20px; font-size: 11.5px; flex-wrap: wrap; }
  .followup-row .fu-date strong { font-weight: bold; }

  .rx-footer { margin-top: 26px; display: flex; justify-content: space-between; align-items: flex-end; }
  .footer-note { font-size: 10px; color: #666; max-width: 60%; }
  .signature-block { text-align: center; min-width: 200px; }
  .signature-line { border-top: 1px solid #111; margin-top: 34px; padding-top: 4px; }
  .signature-block .doc-name { font-weight: bold; font-size: 12.5px; }
  .signature-block .doc-detail { font-size: 10.5px; color: #333; }
</style>
</head>
<body>

    <div class="rx-page" style="padding: 14mm 12mm;">

        {{-- HEADER --}}
        <header class="clinic-header">
            <div class="clinic-logo">
                @php($logoPath = $prescription->institute->logo_path_resolved)
                @if(!empty($logoPath) && file_exists(public_path('storage/'.$logoPath)))
                    <img src="{{ public_path('storage/'.$logoPath) }}" alt="Logo">
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

        {{-- RX META --}}
        <div class="rx-meta">
            <span><strong>Rx No:</strong> {{ clinical_no($prescription->prescription_number) }}</span>
            <span><strong>Date:</strong> {{ $prescription->prescription_date?->format('d M Y') ?? '—' }}</span>
        </div>

        {{-- PATIENT INFO --}}
        <section class="patient-box">
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
        {{-- SOAP + INVESTIGATIONS + ADVICE --}}
        <div class="clinical-section">
            <div class="soap-block">
                <div class="label">Chief Complaints</div>
                <div class="value">{{ $prescription->chief_complaints ?? '—' }}</div>
            </div>
            <div class="soap-block">
                <div class="label">Examination Findings</div>
                <div class="value">{{ $prescription->examination_findings ?? '—' }}</div>
            </div>
            <div class="soap-block">
                <div class="label">Diagnosis</div>
                <div class="value">{{ $prescription->diagnosis ?? '—' }}</div>
            </div>
            <div class="soap-block">
                <div class="label">Investigations</div>
                <div class="value">{{ $prescription->investigations ?? '—' }}</div>
            </div>
            <div class="soap-block">
                <div class="label">Advice</div>
                <div class="value">{{ $prescription->advice ?? '—' }}</div>
            </div>
        </div>

        {{-- Rx --}}
        <div class="clinical-section">
            <p class="rx-heading">℞</p>
            @forelse($items as $i => $item)
                <div class="rx-item">
                    <div class="rx-item-head">
                        <span class="rx-num">{{ $i + 1 }}.</span>
                        <span class="rx-name">{{ $item->medicine_name ?? $item->display_name_snapshot ?? 'Medicine' }}</span>
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

        {{-- BELOW COLUMNS --}}
        <div class="below-sections">
            @if($prescription->follow_up_date)
                <section class="info-section">
                    <p class="section-title">Follow-up</p>
                    <div class="followup-row">
                        <span class="fu-date"><strong>Date:</strong> {{ $prescription->follow_up_date->format('d M Y') }}</span>
                    </div>
                </section>
            @endif
        </div>

        {{-- FOOTER --}}
        <footer class="rx-footer">
            <div class="footer-note">
                <p>This prescription is generated for the named patient only. Please follow dosage instructions exactly as prescribed.</p>
                @if(!empty($verifyCode))
                    <div style="margin-top:8px;">Verification: <code>{{ $verifyCode }}</code></div>
                @endif
                @if(!empty($prescription->signature_hash))
                    <div>Signature: <code>{{ $prescription->signature_hash }}</code></div>
                @endif
            </div>
            <div class="signature-block">
                <div class="signature-line">
                    <div class="doc-name">{{ $doctor->full_name ?? $doctor->name ?? 'N/A' }}</div>
                    @if(!empty($doctorProfile->qualification))<div class="doc-detail">{{ $doctorProfile->qualification }}</div>@endif
                    @if(!empty($doctorProfile->specialty))<div class="doc-detail">{{ $doctorProfile->specialty->name }}</div>@endif
                    @if(!empty($doctorProfile->registration_number))<div class="doc-detail">BMDC Reg. No: {{ $doctorProfile->registration_number }}</div>@endif
                </div>
            </div>
        </footer>

    </div>

</body>
</html>
