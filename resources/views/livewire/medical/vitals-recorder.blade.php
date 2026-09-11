<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 class="mb-0">
            <i class="bi bi-heart-pulse me-1"></i>Vital Signs
            @if($editingId)<span class="badge bg-warning text-dark ms-1">Editing</span>@endif
        </h6>
        @if($editingId)
            <button type="button" class="btn btn-sm btn-secondary" wire:click="cancelEdit">Cancel edit</button>
        @endif
    </div>
    <div class="card-body">
        @if($statusMessage)
            <div class="alert alert-success py-2">{{ $statusMessage }}</div>
        @endif
        @if($errorMessage)
            <div class="alert alert-danger py-2">{{ $errorMessage }}</div>
        @endif

        @if($canView && $latest)
            <div class="alert alert-info py-2 small mb-3">
                <strong>Latest</strong>
                ({{ $latest->recorded_at ? $latest->recorded_at->format('d M Y, h:i A') : '—' }}):
                @if($latest->temperature)Temp {{ $latest->temperature }}°C · @endif
                @if($latest->blood_pressure)BP {{ $latest->blood_pressure }} · @endif
                @if($latest->pulse)Pulse {{ $latest->pulse }} · @endif
                @if($latest->heart_rate)HR {{ $latest->heart_rate }} · @endif
                @if($latest->spo2)SpO2 {{ $latest->spo2 }}% · @endif
                @if($latest->pain_score !== null)Pain {{ $latest->pain_score }}/10 · @endif
                @if($latest->respiratory_rate)RR {{ $latest->respiratory_rate }} · @endif
                @if($latest->blood_sugar)Sugar {{ $latest->blood_sugar }} · @endif
                @if($latest->weight)Wt {{ $latest->weight }}kg · @endif
                @if($latest->height)Ht {{ $latest->height }}cm @endif
                @if($canEdit)
                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-warning align-baseline"
                            wire:click="update({{ $latest->id }})" title="Edit latest entry" style="text-decoration:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>
                            <path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/>
                        </svg>
                    </button>
                @endif
            </div>
        @endif

        @if($canSave || ($editingId && $canEdit))
            <form wire:submit="update">
                <div class="row g-2">
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-temperature">Temp (°C)</label>
                        <input type="number" id="vitals-temperature" step="0.1" min="35" max="42"
                               class="form-control form-control-sm @error('temperature') is-invalid @enderror"
                               wire:model="temperature" placeholder="37.0">
                        @error('temperature')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-sys">BP Sys</label>
                        <input type="number" id="vitals-sys" min="60" max="250"
                               class="form-control form-control-sm @error('blood_pressure_systolic') is-invalid @enderror"
                               wire:model="blood_pressure_systolic" placeholder="120">
                        @error('blood_pressure_systolic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-dia">BP Dia</label>
                        <input type="number" id="vitals-dia" min="30" max="150"
                               class="form-control form-control-sm @error('blood_pressure_diastolic') is-invalid @enderror"
                               wire:model="blood_pressure_diastolic" placeholder="80">
                        @error('blood_pressure_diastolic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-pulse">Pulse</label>
                        <input type="number" id="vitals-pulse" min="30" max="250"
                               class="form-control form-control-sm @error('pulse') is-invalid @enderror"
                               wire:model="pulse" placeholder="72">
                        @error('pulse')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-hr">Heart Rate</label>
                        <input type="number" id="vitals-hr" min="30" max="250"
                               class="form-control form-control-sm @error('heart_rate') is-invalid @enderror"
                               wire:model="heart_rate" placeholder="72">
                        @error('heart_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-rr">Resp. Rate</label>
                        <input type="number" id="vitals-rr" min="5" max="60"
                               class="form-control form-control-sm @error('respiratory_rate') is-invalid @enderror"
                               wire:model="respiratory_rate" placeholder="16">
                        @error('respiratory_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-spo2">SpO2 %</label>
                        <input type="number" id="vitals-spo2" min="70" max="100"
                               class="form-control form-control-sm @error('spo2') is-invalid @enderror"
                               wire:model="spo2" placeholder="98">
                        @error('spo2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-pain">Pain 0–10</label>
                        <input type="number" id="vitals-pain" min="0" max="10"
                               class="form-control form-control-sm @error('pain_score') is-invalid @enderror"
                               wire:model="pain_score" placeholder="0">
                        @error('pain_score')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-sugar">Blood Sugar</label>
                        <input type="number" id="vitals-sugar" step="0.1" min="20" max="500"
                               class="form-control form-control-sm @error('blood_sugar') is-invalid @enderror"
                               wire:model="blood_sugar" placeholder="110">
                        @error('blood_sugar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-weight">Weight (kg)</label>
                        <input type="number" id="vitals-weight" step="0.1" min="1" max="300"
                               class="form-control form-control-sm @error('weight') is-invalid @enderror"
                               wire:model="weight" placeholder="65">
                        @error('weight')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="vitals-height">Height (cm)</label>
                        <input type="number" id="vitals-height" step="0.1" min="30" max="250"
                               class="form-control form-control-sm @error('height') is-invalid @enderror"
                               wire:model="height" placeholder="170">
                        @error('height')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small" for="vitals-notes">Notes</label>
                        <textarea id="vitals-notes" rows="2"
                                  class="form-control form-control-sm @error('notes') is-invalid @enderror"
                                  wire:model="notes" placeholder="Optional note"></textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-2">
                    <button type="submit" class="btn btn-sm {{ $editingId ? 'btn-warning' : 'btn-primary' }}" wire:loading.attr="disabled">
                        <i class="bi bi-{{ $editingId ? 'save' : 'plus-lg' }} me-1"></i>{{ $editingId ? 'Update Vitals' : 'Record Vitals' }}
                    </button>
                </div>
            </form>
        @elseif(!$canView)
            <p class="text-muted small mb-0">You do not have permission to view vitals.</p>
        @endif
    </div>
</div>
