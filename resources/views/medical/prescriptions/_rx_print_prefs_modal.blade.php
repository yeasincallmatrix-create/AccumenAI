{{-- Rx print preferences popup (gear on the Medicines card, create + edit).
     Stored per browser in localStorage; applied by the print preview page.
     The PDF export always renders the full record. --}}
<div class="modal fade" id="rxPrintPrefsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-gear me-1"></i>Print Preferences</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Choose what appears on the prescription printout. Saved on this device only.</p>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="rxpp-letterhead" checked>
                    <label class="form-check-label" for="rxpp-letterhead">Clinic letterhead (name, address, phone)</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="rxpp-qr" checked>
                    <label class="form-check-label" for="rxpp-qr">Verification QR code</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="rxpp-signed" checked>
                    <label class="form-check-label" for="rxpp-signed">Signed stamp (date + signature hash)</label>
                </div>
                <h6 class="mt-3 mb-2">SOAP Setting</h6>
                <p class="text-muted small">Show or hide the clinical note cards on the printout.</p>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="rxpp-complaints" checked>
                    <label class="form-check-label" for="rxpp-complaints">Subjective — Chief Complaints</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="rxpp-findings" checked>
                    <label class="form-check-label" for="rxpp-findings">Objective — Examination Findings</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="rxpp-diagnosis" checked>
                    <label class="form-check-label" for="rxpp-diagnosis">Assessment — Diagnosis</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="rxpp-investigations" checked>
                    <label class="form-check-label" for="rxpp-investigations">Plan — Investigations</label>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="rxpp-advice" checked>
                    <label class="form-check-label" for="rxpp-advice">Plan — Advice</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="rxpp-save">Save Preferences</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var KEY = 'rxPrintPrefs';
    var FIELDS = ['letterhead', 'qr', 'signed', 'complaints', 'findings', 'diagnosis', 'investigations', 'advice'];
    function load() {
        try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; }
    }
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-rx-print-prefs]') : null;
        if (!btn) return;
        var prefs = load();
        FIELDS.forEach(function (f) {
            var el = document.getElementById('rxpp-' + f);
            if (el) el.checked = prefs[f] !== false;
        });
        var m = document.getElementById('rxPrintPrefsModal');
        if (m && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(m).show();
    });
    var save = document.getElementById('rxpp-save');
    if (save) save.addEventListener('click', function () {
        var prefs = {};
        FIELDS.forEach(function (f) {
            var el = document.getElementById('rxpp-' + f);
            prefs[f] = el ? !!el.checked : true;
        });
        try { localStorage.setItem(KEY, JSON.stringify(prefs)); } catch (e) {}
        var m = document.getElementById('rxPrintPrefsModal');
        if (m && window.bootstrap) { var inst = window.bootstrap.Modal.getInstance(m); if (inst) inst.hide(); }
    });
})();
</script>
@endpush
