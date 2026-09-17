@extends('layouts.institute')

@section('title', 'Dental Chart — ' . $patient->full_name)

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-emoji-smile"></i> Dental Chart — {{ $patient->full_name }}</h4>
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Interactive Tooth Chart (FDI Notation)</h6></div>
                <div class="card-body" x-data="dentalChart()" x-init="init()">
                    <div class="text-center mb-3">
                        <small class="text-muted">Click on a tooth to update its condition</small>
                    </div>

                    {{-- SVG Tooth Chart --}}
                    <div class="dental-chart-container text-center">
                        <svg viewBox="0 0 800 420" class="dental-chart" style="max-width:800px; margin:0 auto; display:block;">
                            {{-- Quadrant Labels --}}
                            <text x="180" y="20" text-anchor="middle" class="quadrant-label" font-size="12" fill="#6b7280">Upper Right</text>
                            <text x="620" y="20" text-anchor="middle" class="quadrant-label" font-size="12" fill="#6b7280">Upper Left</text>
                            <text x="180" y="240" text-anchor="middle" class="quadrant-label" font-size="12" fill="#6b7280">Lower Right</text>
                            <text x="620" y="240" text-anchor="middle" class="quadrant-label" font-size="12" fill="#6b7280">Lower Left</text>

                            {{-- Upper Right: 18-11 (left to right = 18,17,...,11) --}}
                            @php $upperRight = ['18','17','16','15','14','13','12','11']; @endphp
                            @foreach($upperRight as $i => $tooth)
                                @php $data = $chart->getToothCondition($tooth); @endphp
                                <g class="tooth" data-tooth="{{ $tooth }}" @click="openModal('{{ $tooth }}', '{{ $data['condition'] ?? 'healthy' }}', '{{ addslashes($data['notes'] ?? '') }}')" style="cursor:pointer;">
                                    <rect x="{{ 30 + $i * 45 }}" y="30" width="40" height="80" rx="8" ry="8"
                                          fill="{{ $chart->conditionColor($tooth) }}" stroke="#d1d5db" stroke-width="1.5"/>
                                    <text x="{{ 50 + $i * 45 }}" y="65" text-anchor="middle" font-size="14" font-weight="bold" fill="#374151">{{ $tooth }}</text>
                                    <text x="{{ 50 + $i * 45 }}" y="85" text-anchor="middle" font-size="9" fill="#6b7280">{{ ucfirst($data['condition'] ?? 'healthy') }}</text>
                                </g>
                            @endforeach

                            {{-- Upper Left: 21-28 (left to right = 21,22,...,28) --}}
                            @php $upperLeft = ['21','22','23','24','25','26','27','28']; @endphp
                            @foreach($upperLeft as $i => $tooth)
                                @php $data = $chart->getToothCondition($tooth); @endphp
                                <g class="tooth" data-tooth="{{ $tooth }}" @click="openModal('{{ $tooth }}', '{{ $data['condition'] ?? 'healthy' }}', '{{ addslashes($data['notes'] ?? '') }}')" style="cursor:pointer;">
                                    <rect x="{{ 420 + $i * 45 }}" y="30" width="40" height="80" rx="8" ry="8"
                                          fill="{{ $chart->conditionColor($tooth) }}" stroke="#d1d5db" stroke-width="1.5"/>
                                    <text x="{{ 440 + $i * 45 }}" y="65" text-anchor="middle" font-size="14" font-weight="bold" fill="#374151">{{ $tooth }}</text>
                                    <text x="{{ 440 + $i * 45 }}" y="85" text-anchor="middle" font-size="9" fill="#6b7280">{{ ucfirst($data['condition'] ?? 'healthy') }}</text>
                                </g>
                            @endforeach

                            {{-- Lower Right: 48-41 (left to right = 48,47,...,41) --}}
                            @php $lowerRight = ['48','47','46','45','44','43','42','41']; @endphp
                            @foreach($lowerRight as $i => $tooth)
                                @php $data = $chart->getToothCondition($tooth); @endphp
                                <g class="tooth" data-tooth="{{ $tooth }}" @click="openModal('{{ $tooth }}', '{{ $data['condition'] ?? 'healthy' }}', '{{ addslashes($data['notes'] ?? '') }}')" style="cursor:pointer;">
                                    <rect x="{{ 30 + $i * 45 }}" y="250" width="40" height="80" rx="8" ry="8"
                                          fill="{{ $chart->conditionColor($tooth) }}" stroke="#d1d5db" stroke-width="1.5"/>
                                    <text x="{{ 50 + $i * 45 }}" y="285" text-anchor="middle" font-size="14" font-weight="bold" fill="#374151">{{ $tooth }}</text>
                                    <text x="{{ 50 + $i * 45 }}" y="305" text-anchor="middle" font-size="9" fill="#6b7280">{{ ucfirst($data['condition'] ?? 'healthy') }}</text>
                                </g>
                            @endforeach

                            {{-- Lower Left: 31-38 (left to right = 31,32,...,38) --}}
                            @php $lowerLeft = ['31','32','33','34','35','36','37','38']; @endphp
                            @foreach($lowerLeft as $i => $tooth)
                                @php $data = $chart->getToothCondition($tooth); @endphp
                                <g class="tooth" data-tooth="{{ $tooth }}" @click="openModal('{{ $tooth }}', '{{ $data['condition'] ?? 'healthy' }}', '{{ addslashes($data['notes'] ?? '') }}')" style="cursor:pointer;">
                                    <rect x="{{ 420 + $i * 45 }}" y="250" width="40" height="80" rx="8" ry="8"
                                          fill="{{ $chart->conditionColor($tooth) }}" stroke="#d1d5db" stroke-width="1.5"/>
                                    <text x="{{ 440 + $i * 45 }}" y="285" text-anchor="middle" font-size="14" font-weight="bold" fill="#374151">{{ $tooth }}</text>
                                    <text x="{{ 440 + $i * 45 }}" y="305" text-anchor="middle" font-size="9" fill="#6b7280">{{ ucfirst($data['condition'] ?? 'healthy') }}</text>
                                </g>
                            @endforeach

                            {{-- Midline --}}
                            <line x1="400" y1="25" x2="400" y2="210" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="4"/>
                            <line x1="400" y1="245" x2="400" y2="335" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="4"/>
                        </svg>
                    </div>

                    {{-- Legend --}}
                    <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
                        @foreach($toothConditions as $key => $label)
                            @php
                                $colors = ['healthy'=>'#e5e7eb','caries'=>'#ef4444','filled'=>'#3b82f6','crown'=>'#f59e0b','rct'=>'#8b5cf6','missing'=>'#9ca3af','impacted'=>'#f97316','fractured'=>'#dc2626','mobile'=>'#6b7280','sensitive'=>'#06b6d4'];
                            @endphp
                            <span class="badge" style="background-color:{{ $colors[$key] ?? '#e5e7eb' }}; color:{{ in_array($key, ['healthy','missing']) ? '#374151' : '#fff' }}; font-size:11px;">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>

                    {{-- Summary Counters --}}
                    <div class="row g-2 mt-3 text-center">
                        <div class="col"><span class="badge bg-danger">{{ $chart->caries_count }} Caries</span></div>
                        <div class="col"><span class="badge bg-primary">{{ $chart->filled_count }} Filled</span></div>
                        <div class="col"><span class="badge bg-secondary">{{ $chart->missing_count }} Missing</span></div>
                        <div class="col"><span class="badge bg-warning text-dark">{{ $chart->crown_count }} Crown</span></div>
                        <div class="col"><span class="badge bg-purple" style="background-color:#8b5cf6;">{{ $chart->rct_count }} RCT</span></div>
                    </div>

                    {{-- Modal for updating tooth --}}
                    <div class="modal fade" id="toothModal" tabindex="-1" x-show="showModal" x-transition>
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Update Tooth <span x-text="modalTooth"></span></h5>
                                    <button type="button" class="btn-close" @click="showModal = false"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Condition</label>
                                        <select class="form-select" x-model="modalCondition">
                                            @foreach($toothConditions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" x-model="modalNotes" rows="3" placeholder="Optional notes..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" @click="showModal = false">Cancel</button>
                                    <button type="button" class="btn btn-primary" @click="saveTooth()" :disabled="saving">
                                        <span x-show="!saving">Save</span>
                                        <span x-show="saving">Saving...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Chart Details</h6></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('medical.dental.chart.save', $patient) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Oral Hygiene</label>
                            <select name="oral_hygiene" class="form-select">
                                <option value="">— Select —</option>
                                @foreach($oralHygiene as $key => $label)
                                    <option value="{{ $key }}" {{ ($chart->oral_hygiene ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">General Notes</label>
                            <textarea name="general_notes" class="form-control" rows="4">{{ $chart->general_notes ?? '' }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Save Chart Notes</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Recent Procedures</h6></div>
                <div class="card-body">
                    @forelse($chart->procedures->take(5) as $proc)
                        <div class="border-bottom py-2">
                            <strong>{{ $proc->procedure_number }}</strong>
                            <br><small>{{ $proc->procedure_name }} — {{ $proc->performed_at->format('d M Y') }}</small>
                            <br><span class="badge bg-{{ $proc->statusColor() }}" style="font-size:10px;">{{ $proc->statusLabel() }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No procedures yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function dentalChart() {
    return {
        showModal: false,
        modalTooth: '',
        modalCondition: 'healthy',
        modalNotes: '',
        saving: false,

        init() {},

        openModal(tooth, condition, notes) {
            this.modalTooth = tooth;
            this.modalCondition = condition;
            this.modalNotes = notes;
            this.showModal = true;
        },

        async saveTooth() {
            this.saving = true;
            try {
                const response = await fetch('{{ route("medical.dental.chart.tooth.update", $patient) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tooth_number: this.modalTooth,
                        condition: this.modalCondition,
                        notes: this.modalNotes,
                    }),
                });
                if (response.ok) {
                    location.reload();
                } else {
                    const data = await response.json();
                    alert(data.message || 'Error updating tooth.');
                }
            } catch (e) {
                alert('Network error. Please try again.');
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>

<style>
.dental-chart-container svg .tooth:hover rect {
    stroke: #2563eb;
    stroke-width: 2.5;
}
.dental-chart-container svg .tooth:hover {
    filter: brightness(0.95);
}
</style>
@endsection
