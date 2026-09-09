{{-- Transfer Appointment popup for the appointments index page.
     Moves a scheduled appointment to another date picked from the calendar
     (tenant-aware x-tdate-input). The server re-issues the serial for the
     target date. --}}
<div class="modal fade" id="transferAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="transfer-appointment-form">
                @csrf
                <input type="hidden" name="_transfer_id" id="tr_appointment_id" value="{{ old('_transfer_id') }}">
                <div class="modal-header">
                    <h5 class="modal-title">Transfer Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-4">Patient</dt><dd class="col-sm-8" id="tr_patient">—</dd>
                        <dt class="col-sm-4">Doctor</dt><dd class="col-sm-8" id="tr_doctor">—</dd>
                        <dt class="col-sm-4">Current Date</dt><dd class="col-sm-8" id="tr_current">—</dd>
                        <dt class="col-sm-4">Serial</dt><dd class="col-sm-8" id="tr_serial">—</dd>
                    </dl>
                    <div class="mb-3">
                        <label class="form-label" for="tr_appointment_date">New Date <span class="text-danger">*</span></label>
                        <x-tdate-input name="appointment_date" :value="old('appointment_date')" id="tr_appointment_date" class="form-control" required />
                        @error('appointment_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <p class="text-muted small mb-0">A new serial number will be issued for the selected date.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
var transferUrls = {
@foreach(($appointments ?? []) as $appointment)
    {{ $appointment->id }}: @json(route('medical.appointments.transfer', $appointment)),
@endforeach
};
var transferInfo = {
@foreach(($appointments ?? []) as $appointment)
    {{ $appointment->id }}: {
        date: @json($appointment->appointment_date->format('Y-m-d')),
        patient: @json($appointment->patient->full_name ?? 'N/A'),
        doctor: @json($appointment->doctor->name ?? 'N/A'),
        serial: @json((string) $appointment->serial_number)
    },
@endforeach
};
function openTransferModal(id) {
    var form = document.getElementById('transfer-appointment-form');
    form.action = transferUrls[id] || '';
    document.getElementById('tr_appointment_id').value = id;
    var info = transferInfo[id] || {};
    document.getElementById('tr_patient').textContent = info.patient || '—';
    document.getElementById('tr_doctor').textContent = info.doctor || '—';
    document.getElementById('tr_current').textContent = info.date || '—';
    document.getElementById('tr_serial').textContent = info.serial ? '#' + info.serial : '—';
    var hidden = document.getElementById('tr_appointment_date');
    if (hidden && !hidden.value && info.date) {
        hidden.value = info.date;
        if (window.tdateSync) window.tdateSync('tr_appointment_date');
    }
    var modalEl = document.getElementById('transferAppointmentModal');
    if (modalEl && window.bootstrap) { window.bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
}
@if($errors->has('appointment_date') && old('_transfer_id'))
document.addEventListener('DOMContentLoaded', function () {
    openTransferModal({{ (int) old('_transfer_id') }});
});
@endif
</script>
@endpush
