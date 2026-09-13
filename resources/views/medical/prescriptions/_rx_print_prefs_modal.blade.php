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
                <p class="text-muted small">Show or hide the clinical note cards on the printout, and a second toggle per line for the writing window (left side while preparing Rx).</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th>Section</th><th class="text-center" style="width:90px;">Print</th><th class="text-center" style="width:90px;">Window</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><label class="form-check-label" for="rxpp-complaints">Subjective — Chief Complaints</label></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxpp-complaints" checked></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxvw-complaints" checked></div></td>
                            </tr>
                            <tr>
                                <td><label class="form-check-label" for="rxpp-findings">Objective — Examination Findings</label></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxpp-findings" checked></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxvw-findings" checked></div></td>
                            </tr>
                            <tr>
                                <td><label class="form-check-label" for="rxpp-diagnosis">Assessment — Diagnosis</label></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxpp-diagnosis" checked></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxvw-diagnosis" checked></div></td>
                            </tr>
                            <tr>
                                <td><label class="form-check-label" for="rxpp-investigations">Plan — Investigations</label></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxpp-investigations" checked></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxvw-investigations" checked></div></td>
                            </tr>
                            <tr>
                                <td><label class="form-check-label" for="rxpp-advice">Plan — Advice</label></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxpp-advice" checked></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" id="rxvw-advice" checked></div></td>
                            </tr>
                        </tbody>
                    </table>
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
    var VIEW_KEY = 'rxViewPrefs';
    var FIELDS = ['letterhead', 'qr', 'signed', 'complaints', 'findings', 'diagnosis', 'investigations', 'advice'];
    var VIEW_FIELDS = ['complaints', 'findings', 'diagnosis', 'investigations', 'advice'];
    function load(key) {
        try { return JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { return {}; }
    }
    function viewOn(field) {
        return load(VIEW_KEY)[field] !== false;
    }
    // Hide/show the left-side writing panels. Hidden panels keep their
    // textarea values so nothing is lost on save — they just don't appear
    // while preparing the Rx.
    function applyViewPrefs() {
        var prefs = load(VIEW_KEY);
        VIEW_FIELDS.forEach(function (f) {
            var show = prefs[f] !== false;
            document.querySelectorAll('[data-rx-panel="' + f + '"]').forEach(function (el) {
                el.style.display = show ? '' : 'none';
            });
            document.querySelectorAll('[data-rx-panel-toggle="' + f + '"]').forEach(function (btn) {
                var icon = btn.querySelector('i');
                if (icon) icon.className = show ? 'bi bi-eye' : 'bi bi-eye-slash';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.title = show ? 'Hide panel' : 'Show panel';
            });
        });
        // Top-row complaints in create: when hidden, let Patient Details
        // take the full row width instead of leaving a gap.
        var complaintsShow = prefs['complaints'] !== false;
        var patientCol = document.getElementById('rx-patient-col');
        if (patientCol) {
            patientCol.classList.toggle('col-md-10', complaintsShow);
            patientCol.classList.toggle('col-md-12', !complaintsShow);
        }
        // Bottom-row SOAP column (edit page holds all five SOAP cards
        // there; create keeps Vitals there too): when every SOAP panel
        // inside it is hidden — and no always-visible card like Vitals
        // remains — collapse the column so the Rx table takes full width.
        var soapCol = document.getElementById('rx-soap-col');
        var mainCol = document.getElementById('rx-main-col');
        if (soapCol && mainCol) {
            var hasPinned = !!soapCol.querySelector('#rx-vitals-card');
            var anySoapLeft = VIEW_FIELDS.some(function (f) {
                return prefs[f] !== false && !!soapCol.querySelector('[data-rx-panel="' + f + '"]');
            });
            var showSoapCol = hasPinned || anySoapLeft;
            soapCol.style.display = showSoapCol ? '' : 'none';
            mainCol.classList.toggle('col-md-10', showSoapCol);
            mainCol.classList.toggle('col-md-12', !showSoapCol);
        }

    }
    window.rxApplyViewPrefs = applyViewPrefs;
    window.rxSetViewPref = function (field, show) {
        var vp = load(VIEW_KEY);
        vp[field] = !!show;
        try { localStorage.setItem(VIEW_KEY, JSON.stringify(vp)); } catch (err) {}
        var sw = document.getElementById('rxvw-' + field);
        if (sw) sw.checked = vp[field] !== false;
        applyViewPrefs();
    };
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-rx-print-prefs]') : null;
        if (btn) {
            var prefs = load(KEY);
            FIELDS.forEach(function (f) {
                var el = document.getElementById('rxpp-' + f);
                if (el) el.checked = prefs[f] !== false;
            });
            var vprefs = load(VIEW_KEY);
            VIEW_FIELDS.forEach(function (f) {
                var vel = document.getElementById('rxvw-' + f);
                if (vel) vel.checked = vprefs[f] !== false;
            });
            var m = document.getElementById('rxPrintPrefsModal');
            if (m && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(m).show();
            return;
        }
        var eye = e.target && e.target.closest ? e.target.closest('[data-rx-panel-toggle]') : null;
        if (eye) {
            var field = eye.getAttribute('data-rx-panel-toggle');
            var vp = load(VIEW_KEY);
            vp[field] = !viewOn(field);
            try { localStorage.setItem(VIEW_KEY, JSON.stringify(vp)); } catch (err) {}
            // Keep the modal switch in sync if it is open.
            var sw = document.getElementById('rxvw-' + field);
            if (sw) sw.checked = vp[field] !== false;
            applyViewPrefs();
        }
    });
    var save = document.getElementById('rxpp-save');
    if (save) save.addEventListener('click', function () {
        var prefs = {};
        FIELDS.forEach(function (f) {
            var el = document.getElementById('rxpp-' + f);
            prefs[f] = el ? !!el.checked : true;
        });
        try { localStorage.setItem(KEY, JSON.stringify(prefs)); } catch (e) {}
        var vprefs = {};
        VIEW_FIELDS.forEach(function (f) {
            var el = document.getElementById('rxvw-' + f);
            vprefs[f] = el ? !!el.checked : true;
        });
        try { localStorage.setItem(VIEW_KEY, JSON.stringify(vprefs)); } catch (e) {}
        applyViewPrefs();
        var m = document.getElementById('rxPrintPrefsModal');
        if (m && window.bootstrap) { var inst = window.bootstrap.Modal.getInstance(m); if (inst) inst.hide(); }
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyViewPrefs);
    } else {
        applyViewPrefs();
    }
})();
</script>
@endpush
