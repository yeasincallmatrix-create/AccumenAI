{{-- Tenant-aware dates driven by Settings → General → Date Format
     (dmy → DD/MM/YYYY, mdy → MM/DD/YYYY, ymd → YYYY/MM/DD).
     Pairs [data-tdate-display] text with hidden ISO (server always gets Y-m-d).
     Calendar buttons ([data-tdate-picker] / [data-live-date-picker]) open a
     built-in month popup; picked dates fill back in tenant format.
     Manual typing is filter-only (digits + slash, caret preserved) — the text
     is parsed (tenant order, ISO year-first, - . separators, 8-digit runs)
     and the hidden ISO synced live; valid entries are normalized to tenant
     format on blur. Invalid entries are flagged and block filter submits.
     window.tdateSync(id) refreshes the visible text after programmatic hidden-value sets.
     window.tdateReady(id) / window.guardTdateSubmit(el) guard filter-form submits. --}}
<style>
.tdate-cal{position:fixed;z-index:3000;background:#fff;border:1px solid #dee2e6;border-radius:.5rem;box-shadow:0 .5rem 1rem rgba(0,0,0,.15);padding:.5rem;width:292px;max-width:calc(100vw - 16px);}
.tdate-cal-head{display:flex;align-items:center;gap:.25rem;margin-bottom:.3rem;}
.tdate-cal-head strong{flex:1;text-align:center;font-size:.85rem;font-weight:600;}
.tdate-cal-head button{border:1px solid #dee2e6;background:#f8f9fa;border-radius:.35rem;padding:0 .45rem;line-height:1.7;font-size:.85rem;}
.tdate-cal-head button:hover{background:#e9ecef;}
.tdate-cal-head select{border:1px solid #dee2e6;border-radius:.35rem;font-size:.8rem;padding:.1rem .2rem;background:#fff;max-width:100%;}
.tdate-cal-head select[data-cal-year]{flex-shrink:0;}
.tdate-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center;font-size:.8rem;}
.tdate-cal-grid .dow{color:#6c757d;font-weight:600;padding:.1rem 0;}
.tdate-cal-grid button.day{border:0;background:transparent;border-radius:.35rem;padding:.22rem 0;font-size:.8rem;}
.tdate-cal-grid button.day:hover{background:#e9ecef;}
.tdate-cal-grid button.day.sel{background:#0d6efd;color:#fff;}
.tdate-cal-grid button.day.muted{color:#adb5bd;}
.tdate-cal-grid button.day.disabled{color:#dee2e6;text-decoration:line-through;cursor:not-allowed;}
.tdate-cal-grid button.day.disabled:hover{background:transparent;}
</style>
<script>
window.MAWA_DATE_ORDER = @json(mawa_date_format_key());
(function () {
    function orderOf(disp) {
        var o = disp && disp.getAttribute ? disp.getAttribute('data-date-order') : '';
        if (o === 'mdy' || o === 'ymd' || o === 'dmy') return o;
        var g = window.MAWA_DATE_ORDER;
        return (g === 'mdy' || g === 'ymd' || g === 'dmy') ? g : 'dmy';
    }
    function toDisplay(iso, order) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
        if (!m) return '';
        if (order === 'mdy') return m[2] + '/' + m[3] + '/' + m[1];
        if (order === 'ymd') return m[1] + '/' + m[2] + '/' + m[3];
        return m[3] + '/' + m[2] + '/' + m[1];
    }
    function toIso(text, order) {
        var t = (text || '').trim();
        var m;
        if (order === 'ymd') {
            m = /^(\d{4})\/(\d{1,2})\/(\d{1,2})$/.exec(t);
            if (!m) return '';
            var y = +m[1], mo = +m[2], d = +m[3];
            var dt = new Date(y, mo - 1, d);
            if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return '';
            return m[1] + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        }
        m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(t);
        if (!m) return '';
        var a = +m[1], b = +m[2], yy = +m[3];
        var dd = order === 'mdy' ? b : a;
        var mm = order === 'mdy' ? a : b;
        var chk = new Date(yy, mm - 1, dd);
        if (chk.getFullYear() !== yy || chk.getMonth() !== mm - 1 || chk.getDate() !== dd) return '';
        return yy + '-' + String(mm).padStart(2, '0') + '-' + String(dd).padStart(2, '0');
    }
    // Keep only date characters (digits + slash), capped at maxLen.
    function stripToDateChars(value, maxLen) {
        var out = '';
        for (var i = 0; i < value.length && out.length < maxLen; i++) {
            var ch = value.charAt(i);
            if ((ch >= '0' && ch <= '9') || ch === '/') out += ch;
        }
        return out;
    }
    // Caret position after stripping: kept chars before the old caret,
    // clamped to the cleaned length.
    function caretAfterStrip(raw, pos, maxPos) {
        var kept = 0;
        for (var i = 0; i < raw.length && i < pos; i++) {
            var ch = raw.charAt(i);
            if ((ch >= '0' && ch <= '9') || ch === '/') kept++;
        }
        return Math.min(kept, maxPos);
    }
    // Parse free-typed text to ISO (strict — rejects overflow like 31/02).
    // Accepts tenant order with / - . separators, 8-digit runs (DDMMYYYY /
    // MMDDYYYY / YYYYMMDD per order), and year-first ISO in any order.
    function parseToIso(text, order) {
        var t = (text || '').trim().replace(/[.]/g, '/').replace(/-/g, '/');
        var m = /^(\d{4})\/(\d{1,2})\/(\d{1,2})$/.exec(t);
        if (m) {
            var y0 = +m[1], mo0 = +m[2], d0 = +m[3];
            var dt0 = new Date(y0, mo0 - 1, d0);
            if (dt0.getFullYear() === y0 && dt0.getMonth() === mo0 - 1 && dt0.getDate() === d0) {
                return m[1] + '-' + String(mo0).padStart(2, '0') + '-' + String(d0).padStart(2, '0');
            }
            return '';
        }
        var d8 = /^(\d{2})(\d{2})(\d{4})$/.exec(t);
        if (order !== 'ymd' && d8) {
            var viaDayFirst = toIso(d8[1] + '/' + d8[2] + '/' + d8[3], order);
            if (viaDayFirst) return viaDayFirst;
            // Day-first split invalid (e.g. stripped ISO "20260911" in dmy):
            // fall through to the year-first attempt below.
        }
        var y8 = /^(\d{4})(\d{2})(\d{2})$/.exec(t);
        if (y8) {
            return parseToIso(y8[1] + '/' + y8[2] + '/' + y8[3], order);
        }
        return toIso(t, order);
    }
    function fullLen(order) { return 10; }
    // Set a hidden ISO input and notify listeners (e.g. DOB → age calculators).
    // Programmatic .value sets fire no events on their own.
    function setHiddenIso(hidden, iso) {
        if (!hidden) return;
        iso = iso || '';
        if ((hidden.value || '') === iso) return;
        hidden.value = iso;
        try { hidden.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
    }
    window.tdateSync = function (id) {
        var hidden = document.getElementById(id);
        var disp = document.getElementById(id + '_display');
        if (hidden && disp) disp.value = toDisplay(hidden.value, orderOf(disp));
    };
    // True when a tenant-date field is submittable (empty or valid ISO present).
    window.tdateReady = function (id) {
        var hidden = document.getElementById(id);
        var disp = document.getElementById(id + '_display');
        if (!hidden || !disp) return true;
        var t = (disp.value || '').trim();
        return t === '' || hidden.value !== '';
    };
    // Filter-form submit guard: skips submit while any tenant date field is
    // incomplete/invalid — flagging the first bad field and focusing it so
    // the skip is visible instead of silent.
    window.guardTdateSubmit = function (el) {
        var form = el && el.form ? el.form : (el && el.closest ? el.closest('form') : null);
        if (form && form.querySelectorAll) {
            var displays = form.querySelectorAll('[data-tdate-display]');
            var bad = null;
            for (var i = 0; i < displays.length; i++) {
                var h = document.getElementById(displays[i].getAttribute('data-tdate-display'));
                var t = (displays[i].value || '').trim();
                if (t !== '' && (!h || h.value === '')) {
                    displays[i].classList.add('is-invalid');
                    if (!bad) bad = displays[i];
                }
            }
            if (bad) {
                try { bad.focus(); } catch (e) {}
                return;
            }
            form.submit();
        } else if (el && el.form) {
            el.form.submit();
        }
    };
    document.addEventListener('input', function (e) {
        var disp = e.target && e.target.closest ? e.target.closest('[data-tdate-display]') : null;
        if (!disp) return;
        var order = orderOf(disp);
        // Filter only: never regroup mid-string edits (that scrambled
        // day/month changes into the year) and never move the caret
        // except to account for stripped illegal characters.
        var raw = disp.value || '';
        var cleaned = stripToDateChars(raw, fullLen(order));
        if (cleaned !== raw) {
            var caret = caretAfterStrip(raw, disp.selectionStart || 0, cleaned.length);
            disp.value = cleaned;
            try { disp.setSelectionRange(caret, caret); } catch (err) {}
        }
        var hidden = document.getElementById(disp.getAttribute('data-tdate-display'));
        var iso = cleaned.trim() === '' ? '' : parseToIso(cleaned, order);
        setHiddenIso(hidden, iso || '');
        disp.classList.toggle('is-invalid', cleaned.trim() !== '' && !iso);
    });
    // Blur: normalize a valid entry to tenant format (1/9/2026 → 01/09/2026,
    // 2026-09-11 → 11/09/2026, 11092026 → 11/09/2026); flag invalid ones.
    document.addEventListener('focusout', function (e) {
        var disp = e.target && e.target.closest ? e.target.closest('[data-tdate-display]') : null;
        if (!disp) return;
        var order = orderOf(disp);
        var hidden = document.getElementById(disp.getAttribute('data-tdate-display'));
        var t = (disp.value || '').trim();
        if (t === '') {
            setHiddenIso(hidden, '');
            disp.classList.remove('is-invalid');
            return;
        }
        var iso = parseToIso(t, order);
        if (iso) {
            setHiddenIso(hidden, iso);
            disp.value = toDisplay(iso, order);
            disp.classList.remove('is-invalid');
        } else {
            setHiddenIso(hidden, '');
            disp.classList.add('is-invalid');
        }
    });
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.querySelector) return;
        form.querySelectorAll('[data-tdate-display]').forEach(function (disp) {
            var hidden = document.getElementById(disp.getAttribute('data-tdate-display'));
            if (hidden && disp.value && disp.value.length === fullLen(orderOf(disp))) {
                var iso = parseToIso(disp.value, orderOf(disp));
                if (iso) hidden.value = iso;
            }
        });
    }, true);

    // Livewire tenant dates: [data-live-date-display] text <-> hidden wire:model.live ISO.
    // Typing a complete valid date pushes ISO to Livewire; server re-renders
    // re-sync the text (skipped while focused so partial typing is never wiped).
    function syncLiveWrap(wrap) {
        var hidden = wrap.querySelector('[data-live-date-hidden]');
        var disp = wrap.querySelector('[data-live-date-display]');
        if (!hidden || !disp) return;
        if (document.activeElement === disp) return;
        disp.value = toDisplay(hidden.value || '', orderOf(disp));
        disp.classList.remove('is-invalid');
    }
    function syncAllLiveDates() {
        document.querySelectorAll('[data-live-date-wrap]').forEach(syncLiveWrap);
    }
    document.addEventListener('input', function (e) {
        var disp = e.target && e.target.closest ? e.target.closest('[data-live-date-display]') : null;
        if (!disp || !disp.closest('[data-live-date-wrap]')) return;
        var wrap = disp.closest('[data-live-date-wrap]');
        var hidden = wrap.querySelector('[data-live-date-hidden]');
        if (!hidden) return;
        var order = orderOf(disp);
        // Filter only (same fix as the standard handler above): no regrouping,
        // caret preserved; tolerant parse pushes ISO to Livewire when valid.
        var raw = disp.value || '';
        var cleaned = stripToDateChars(raw, fullLen(order));
        if (cleaned !== raw) {
            var caret = caretAfterStrip(raw, disp.selectionStart || 0, cleaned.length);
            disp.value = cleaned;
            try { disp.setSelectionRange(caret, caret); } catch (err) {}
        }
        if (cleaned === '') {
            disp.classList.remove('is-invalid');
            if (hidden.value !== '') {
                hidden.value = '';
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }
            return;
        }
        var iso = parseToIso(cleaned, order);
        disp.classList.toggle('is-invalid', !iso);
        if (iso !== '' && hidden.value !== iso) {
            hidden.value = iso;
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
    document.addEventListener('livewire:updated', syncAllLiveDates);
    document.addEventListener('livewire:morph', syncAllLiveDates);
    document.addEventListener('livewire:init', function () {
        try {
            if (window.Livewire && window.Livewire.hook) {
                window.Livewire.hook('morph.updating', function (info) {
                    var el = info && info.el;
                    if (el && el.hasAttribute && el.hasAttribute('data-live-date-display') && document.activeElement === el) return false;
                });
                window.Livewire.hook('morph.updated', function () { syncAllLiveDates(); });
            }
        } catch (err) {}
    });
    document.addEventListener('DOMContentLoaded', syncAllLiveDates);

    // Calendar popup shared by [data-tdate-picker] and [data-live-date-picker].
    // Picked ISO dates fill the text input in tenant format (and notify
    // listeners / Livewire through the hidden ISO input).
    // Dynamic placement: below the button when it fits, above when the
    // field sits at the bottom of a card/modal, centered on small screens
    // or when neither side fits. Rendered on <body> so card/modal overflow
    // can never clip it. Repositioned on scroll/resize while open.
    var activePicker = null;
    function positionDatePicker() {
        var p = activePicker;
        if (!p || !p.popup || !p.btn) return;
        var r = p.btn.getBoundingClientRect();
        var pw = p.popup.offsetWidth || 292;
        var ph = p.popup.offsetHeight || 330;
        var vw = window.innerWidth || 360;
        var vh = window.innerHeight || 600;
        var margin = 8;
        var left, top;
        if (vw < 480) {
            left = Math.max(margin, (vw - pw) / 2);
        } else {
            left = r.right - pw;
            if (left < margin) left = margin;
            if (left + pw > vw - margin) left = vw - pw - margin;
        }
        if (vh - r.bottom >= ph + margin) {
            top = r.bottom + 4;
        } else if (r.top >= ph + margin) {
            top = r.top - ph - 4;
        } else {
            top = Math.max(margin, (vh - ph) / 2);
            left = Math.max(margin, (vw - pw) / 2);
        }
        p.popup.style.left = left + 'px';
        p.popup.style.top = top + 'px';
    }
    window.addEventListener('resize', function () { if (activePicker) positionDatePicker(); });
    document.addEventListener('scroll', function () { if (activePicker) positionDatePicker(); }, true);
    var CAL_MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    function pad2(n) { return String(n).padStart(2, '0'); }
    function isoOfDate(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
    function parseIso(iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
        if (!m) return null;
        var d = new Date(+m[1], +m[2] - 1, +m[3]);
        if (d.getFullYear() !== +m[1] || d.getMonth() !== +m[2] - 1 || d.getDate() !== +m[3]) return null;
        return d;
    }
    function closeDatePicker() {
        if (activePicker && activePicker.popup && activePicker.popup.parentNode) {
            activePicker.popup.parentNode.removeChild(activePicker.popup);
        }
        activePicker = null;
    }
    function pickerSelectedIso(p) {
        if (p.kind === 'live') {
            var lh = p.wrap ? p.wrap.querySelector('[data-live-date-hidden]') : null;
            return (lh && lh.value) || '';
        }
        return (p.hidden && p.hidden.value) || '';
    }
    function renderDatePicker(p) {
        var first = new Date(p.y, p.m, 1);
        var startDay = first.getDay();
        var daysIn = new Date(p.y, p.m + 1, 0).getDate();
        var prevDays = new Date(p.y, p.m, 0).getDate();
        var sel = pickerSelectedIso(p);
        var nowY = new Date().getFullYear();
        var minY = nowY - 120;
        var maxY = nowY + 10;
        if (p.maxIso) {
            maxY = Math.min(maxY, parseInt(p.maxIso.slice(0, 4), 10) || maxY);
            if (p.y > maxY) { p.y = maxY; p.m = 11; }
        }
        // Days past the ceiling render disabled (unpickable).
        var html = '<div class="tdate-cal-head">'
            + '<button type="button" data-cal="prev" aria-label="Previous month">&lsaquo;</button>'
            + '<select data-cal-month aria-label="Month">';
        for (var mi = 0; mi < 12; mi++) {
            html += '<option value="' + mi + '"' + (mi === p.m ? ' selected' : '') + '>' + CAL_MONTHS[mi] + '</option>';
        }
        html += '</select><select data-cal-year aria-label="Year">';
        for (var yy = maxY; yy >= minY; yy--) {
            html += '<option value="' + yy + '"' + (yy === p.y ? ' selected' : '') + '>' + yy + '</option>';
        }
        html += '</select>'
            + '<button type="button" data-cal="next" aria-label="Next month">&rsaquo;</button>'
            + '<button type="button" data-cal="today">Today</button>'
            + '</div><div class="tdate-cal-grid">';
        var DOW = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        for (var i = 0; i < 7; i++) html += '<span class="dow">' + DOW[i] + '</span>';
        var k, d, iso, cls, dis;
        for (k = startDay - 1; k >= 0; k--) {
            d = new Date(p.y, p.m - 1, prevDays - k);
            iso = isoOfDate(d);
            dis = p.maxIso && iso > p.maxIso;
            cls = 'day muted' + (iso === sel ? ' sel' : '') + (dis ? ' disabled' : '');
            html += '<button type="button" class="' + cls + '" data-day="' + iso + '"' + (dis ? ' disabled' : '') + '>' + d.getDate() + '</button>';
        }
        for (var day = 1; day <= daysIn; day++) {
            d = new Date(p.y, p.m, day);
            iso = isoOfDate(d);
            dis = p.maxIso && iso > p.maxIso;
            cls = 'day' + (iso === sel ? ' sel' : '') + (dis ? ' disabled' : '');
            html += '<button type="button" class="' + cls + '" data-day="' + iso + '"' + (dis ? ' disabled' : '') + '>' + day + '</button>';
        }
        var tail = (7 - ((startDay + daysIn) % 7)) % 7;
        for (var t = 1; t <= tail; t++) {
            d = new Date(p.y, p.m + 1, t);
            iso = isoOfDate(d);
            dis = p.maxIso && iso > p.maxIso;
            cls = 'day muted' + (iso === sel ? ' sel' : '') + (dis ? ' disabled' : '');
            html += '<button type="button" class="' + cls + '" data-day="' + iso + '"' + (dis ? ' disabled' : '') + '>' + t + '</button>';
        }
        p.popup.innerHTML = html + '</div>';
    }
    function pickIsoDate(p, iso) {
        if (p.kind === 'live') {
            var wrap = p.wrap;
            var lh = wrap ? wrap.querySelector('[data-live-date-hidden]') : null;
            var ld = wrap ? wrap.querySelector('[data-live-date-display]') : null;
            if (lh && (lh.value || '') !== iso) {
                lh.value = iso;
                try { lh.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
            }
            if (ld) {
                ld.value = toDisplay(iso, orderOf(ld));
                ld.classList.remove('is-invalid');
            }
        } else {
            setHiddenIso(p.hidden, iso);
            if (p.disp) {
                p.disp.value = toDisplay(iso, orderOf(p.disp));
                p.disp.classList.remove('is-invalid');
                // Programmatic sets fire no events on their own: notify
                // filter forms (onchange="guardTdateSubmit(this)") so a
                // calendar-picked date actually refreshes the list. Booking
                // forms carry no change handler, so they are unaffected.
                try { p.disp.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
            }
        }
        closeDatePicker();
    }
    function openDatePicker(btn, kind) {
        if (activePicker && activePicker.btn === btn) { closeDatePicker(); return; }
        closeDatePicker();
        var p = { btn: btn, kind: kind, popup: document.createElement('div'), y: 0, m: 0 };
        p.popup.className = 'tdate-cal';
        p.popup.setAttribute('role', 'dialog');
        if (kind === 'live') {
            p.wrap = btn.closest('[data-live-date-wrap]');
            var curLh = p.wrap ? p.wrap.querySelector('[data-live-date-hidden]') : null;
            var cur = parseIso(curLh ? curLh.value : '') || new Date();
            p.y = cur.getFullYear();
            p.m = cur.getMonth();
        } else {
            var id = btn.getAttribute('data-tdate-picker');
            p.hidden = document.getElementById(id);
            p.disp = document.getElementById(id + '_display');
            // Optional per-field ceiling (e.g. DOB never in the future):
            // data-tdate-max="Y-m-d" on the display or hidden input.
            var maxAttr = (p.disp && p.disp.getAttribute('data-tdate-max')) || (p.hidden && p.hidden.getAttribute('data-tdate-max')) || '';
            p.maxIso = /^\d{4}-\d{2}-\d{2}$/.test(maxAttr) ? maxAttr : '';
            var curT = parseIso(p.hidden ? p.hidden.value : '') || new Date();
            p.y = curT.getFullYear();
            p.m = curT.getMonth();
        }
        document.body.appendChild(p.popup);
        activePicker = p;
        renderDatePicker(p);
        positionDatePicker();
    }
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.key === 'Esc') && activePicker) closeDatePicker();
    });
    // Month / year jump selects inside the popup (DOB-friendly: no endless stepping).
    document.addEventListener('change', function (e) {
        if (!activePicker) return;
        var sel = e.target && e.target.closest ? e.target.closest('[data-cal-month],[data-cal-year]') : null;
        if (!sel || !activePicker.popup.contains(sel)) return;
        if (sel.hasAttribute('data-cal-month')) {
            var mm = parseInt(sel.value, 10);
            if (!isNaN(mm) && mm >= 0 && mm <= 11) activePicker.m = mm;
        } else {
            var yy = parseInt(sel.value, 10);
            if (!isNaN(yy)) activePicker.y = yy;
        }
        renderDatePicker(activePicker);
    });
    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target : null;
        var pickerBtn = el && el.closest ? el.closest('[data-tdate-picker],[data-live-date-picker]') : null;
        if (pickerBtn) {
            if (pickerBtn.hasAttribute('data-tdate-picker')) openDatePicker(pickerBtn, 'tdate');
            else openDatePicker(pickerBtn, 'live');
            return;
        }
        if (!activePicker) return;
        // Clicks inside the popup (month/year selects, padding) keep it open.
        var inner = el && el.closest ? el.closest('.tdate-cal [data-day],.tdate-cal [data-cal]') : null;
        if (inner) {
            if (inner.hasAttribute('data-day')) { pickIsoDate(activePicker, inner.getAttribute('data-day')); return; }
            var nav = inner.getAttribute('data-cal');
            if (nav === 'prev' || nav === 'next') {
                var nm = activePicker.m + (nav === 'next' ? 1 : -1);
                var ny = activePicker.y;
                if (nm < 0) { nm = 11; ny--; }
                if (nm > 11) { nm = 0; ny++; }
                activePicker.m = nm;
                activePicker.y = ny;
                renderDatePicker(activePicker);
                positionDatePicker();
                return;
            }
            if (nav === 'today') {
                var now = new Date();
                pickIsoDate(activePicker, isoOfDate(now));
                return;
            }
            return;
        }
        if (el && el.closest && el.closest('.tdate-cal')) return;
        closeDatePicker();
    });
})();
</script>
