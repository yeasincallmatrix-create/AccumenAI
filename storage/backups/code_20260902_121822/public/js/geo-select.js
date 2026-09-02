(function (window) {
    'use strict';

    var Monetix = window.Monetix || (window.Monetix = {});

    /**
     * Global address selector — country-neutral cascading Country → Level 1 →
     * Level 2 → Level 3.
     *
     * Level labels are dynamic: they come from the per-country administrative-
     * level configuration served by /geo/levels/{country}. Selecting a country
     * reloads the labels and clears the descendant level selections.
     *
     * Options are loaded on demand from /geo/units. When the page pre-renders
     * options server-side (edit mode with a saved address) those options are
     * left untouched; picking a country or a parent level fetches and fills the
     * child level's units client-side so the cascade works even on a brand-new
     * form where nothing is selected yet.
     *
     * Works with ADMIN_1/2/3 style fields; without JS the labels are whatever
     * the server pre-rendered and the hidden form still submits its values.
     */

    function bindComponent(root) {
        if (root.getAttribute('data-bound') === '1') { return; }
        root.setAttribute('data-bound', '1');

        var countrySelect = root.querySelector('[data-address-country]');
        var unitsEndpoint = (countrySelect.getAttribute('data-units-endpoint') || '').replace(/\/$/, '');
        var reqSeq = 0;
        var prefix = root.getAttribute('data-prefix') || '';

        function zipInput() {
            return root.querySelector('[name="' + prefix + 'zip_code"]');
        }

        var levelFields = Array.prototype.slice.call(
            root.querySelectorAll('[data-address-level]')
        ).sort(function (a, b) {
            return Number(a.getAttribute('data-level')) - Number(b.getAttribute('data-level'));
        });

        function labelOf(level) {
            var field = levelFields.find(function (f) {
                return f.getAttribute('data-level') === String(level);
            });
            return field ? field.querySelector('[data-address-label]') : null;
        }

        function unitSelect(level) {
            var field = levelFields.find(function (f) {
                return f.getAttribute('data-level') === String(level);
            });
            return field ? field.querySelector('[data-address-unit]') : null;
        }

        function applyZip() {
            var zip = zipInput();
            if (!zip) { return; }
            var code = '';
            for (var l = 3; l >= 1; l--) {
                var select = unitSelect(l);
                var opt = select && select.options[select.selectedIndex];
                var c = opt && opt.value ? (opt.getAttribute('data-postal-code') || '') : '';
                if (c) { code = c; break; }
            }
            zip.value = code;
        }

        function clearFrom(level) {
            for (var l = level; l <= 3; l++) {
                var select = unitSelect(l);
                if (select) {
                    select.value = '';
                    while (select.options.length > 1) { select.remove(1); }
                }
            }
            // Selecting a lower level clears the auto postal code; it will be
            // re-derived from the deepest selected level.
            var zip = zipInput();
            if (zip) { zip.value = ''; }
        }

        function setFieldHidden(level, hidden) {
            var field = levelFields.find(function (f) {
                return f.getAttribute('data-level') === String(level);
            });
            if (!field) { return; }
            if (hidden) {
                field.setAttribute('hidden', 'hidden');
            } else {
                field.removeAttribute('hidden');
            }
        }

        // Level 1 shows as soon as a country is picked; District (2) is always
        // visible once a country exists; Upazila (3) stays hidden until its
        // parent level actually has a value AND the level-3 field itself has
        // data to choose from — otherwise it is hidden entirely. For Bangladesh
        // (BD) the field is shown immediately once a country exists (the BD
        // hierarchy is short and division→district→upazila is fully seeded), so
        // the Upazila dropdown sits in place even before its parent is chosen.
        function isBangladesh() {
            var opt = countrySelect.options[countrySelect.selectedIndex];
            return !!(opt && opt.getAttribute('data-iso2') === 'BD');
        }

        function hasData(level) {
            var select = unitSelect(level);
            return !!(select && select.options.length > 1);
        }

        function refreshVisibility() {
            var hasCountry = !!countrySelect.value;
            var bd = isBangladesh();
            setFieldHidden(1, !hasCountry);
            setFieldHidden(2, !hasCountry);
            for (var l = 3; l <= 3; l++) {
                var parent = unitSelect(l - 1);
                var parentHasValue = !!(parent && parent.value);
                // BD: show Upazila as soon as a country exists, even with an
                // empty select; other countries still wait for the parent pick.
                setFieldHidden(l, !(hasCountry && (bd || parentHasValue) && (bd || hasData(l))));
            }
        }

        function applyLabels(labels) {
            labels = labels || {};
            [1, 2, 3].forEach(function (level) {
                var labelEl = labelOf(level);
                var select = unitSelect(level);
                var text = labels[level] || ('Level ' + level);
                if (labelEl) { labelEl.textContent = text; }
                if (select && select.options.length > 0) {
                    select.options[0].textContent = '-- ' + text + ' --';
                }
            });
        }

        function labelsFromOption() {
            var opt = countrySelect.options[countrySelect.selectedIndex];
            var out = {};
            [1, 2, 3].forEach(function (level) {
                var v = opt ? opt.getAttribute('data-label-' + level) : '';
                if (v) { out[level] = v; }
            });
            return out;
        }

        function renderUnits(select, units) {
            var label = select.options[0] ? select.options[0].textContent : '';
            select.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = label || 'Select';
            select.appendChild(placeholder);
            units.forEach(function (u) {
                var opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.name;
                opt.setAttribute('data-postal-code', u.postal_code || '');
                select.appendChild(opt);
            });
        }

        function loadUnits(level, parentId) {
            var select = unitSelect(level);
            if (!select || !countrySelect.value) { return; }
            var seq = ++reqSeq;
            var url = unitsEndpoint
                + '?country_id=' + encodeURIComponent(countrySelect.value)
                + '&level=' + encodeURIComponent(level);
            if (parentId !== null && parentId !== undefined && parentId !== '') {
                url += '&parent_id=' + encodeURIComponent(parentId);
            }

            Monetix.request(url).then(function (res) {
                if (seq !== reqSeq) { return; }
                if (!res || !res.success) {
                    renderUnits(select, []);
                    refreshVisibility();
                    return;
                }
                renderUnits(select, (res.data && res.data.units) || []);
                refreshVisibility();
            });
        }

        function onCountryChange() {
            var id = countrySelect.value;
            var seq = ++reqSeq;
            if (!id) {
                applyLabels(null);
                clearFrom(1);
                refreshVisibility();
                return;
            }
            // Update the level labels synchronously from the selected country's
            // <option> (each option carries data-label-1/2/3). The server-side
            // endpoint is kept as a fallback for countries without attributes.
            applyLabels(labelsFromOption());
            clearFrom(1);
            refreshVisibility();
            var endpoint = countrySelect.getAttribute('data-label-endpoint') || '';
            var url = endpoint.replace('__ID__', encodeURIComponent(id));

            Monetix.request(url).then(function (res) {
                if (seq !== reqSeq) { return; }
                if (res && res.success) {
                    applyLabels(res.data.labels || {});
                }
            });
            loadUnits(1, null);
        }

        countrySelect.addEventListener('change', onCountryChange);

        levelFields.forEach(function (field) {
            var select = field.querySelector('[data-address-unit]');
            var level = Number(field.getAttribute('data-level'));
            if (!select) { return; }
            select.addEventListener('change', function () {
                var parentId = select.value;
                clearFrom(level + 1);
                applyZip();
                if (parentId) {
                    loadUnits(level + 1, parentId);
                }
                refreshVisibility();
            });
        });

        // If a country came pre-selected but its options were not pre-rendered
        // (fresh form defaults, AJAX page swap), populate the first level.
        if (countrySelect.value && unitSelect(1) && unitSelect(1).options.length <= 1) {
            onCountryChange();
        } else {
            refreshVisibility();
        }

        // Programmatic cascade fill: set a full address at once. Used by the
        // students quick-edit modal where the form is shared and each student's
        // saved address is applied when the modal opens.
        root.setGeoValues = function (countryId, vals) {
            vals = vals || {};
            var fillSeq = ++reqSeq;
            clearFrom(1);
            countrySelect.value = countryId ? String(countryId) : '';

            function restoreZip() {
                if (vals.zip_code !== undefined && vals.zip_code !== null && vals.zip_code !== '') {
                    var zip = zipInput();
                    if (zip) { zip.value = vals.zip_code; }
                }
            }

            if (!countrySelect.value) {
                applyLabels(null);
                restoreZip();
                refreshVisibility();
                return;
            }

            function loadLabelsThen(next) {
                // Prefer the selected option's embedded labels (instant, no
                // round-trip); the endpoint stays as a fallback.
                var embedded = labelsFromOption();
                if (embedded[1] || embedded[2] || embedded[3]) {
                    applyLabels(embedded);
                    if (next) { next(); }
                    return;
                }
                var endpoint = countrySelect.getAttribute('data-label-endpoint') || '';
                var url = endpoint.replace('__ID__', encodeURIComponent(countrySelect.value));
                Monetix.request(url).then(function (res) {
                    if (fillSeq !== reqSeq) { return; }
                    if (res && res.success) { applyLabels(res.data.labels || {}); }
                    if (next) { next(); }
                });
            }

            function fill(level) {
                if (level > 3) {
                    applyZip();
                    restoreZip();
                    refreshVisibility();
                    return;
                }
                var select = unitSelect(level);
                if (!select) { applyZip(); restoreZip(); refreshVisibility(); return; }
                var parentId = level === 1 ? null : unitSelect(level - 1).value;
                if (level > 1 && !parentId) {
                    applyZip();
                    restoreZip();
                    refreshVisibility();
                    return;
                }

                var url = unitsEndpoint
                    + '?country_id=' + encodeURIComponent(countrySelect.value)
                    + '&level=' + encodeURIComponent(level);
                if (parentId) { url += '&parent_id=' + encodeURIComponent(parentId); }

                Monetix.request(url).then(function (res) {
                    if (fillSeq !== reqSeq) { return; }
                    renderUnits(select, (res && res.success && res.data.units) || []);
                    var wanted = vals[level];
                    var matched = '';
                    if (wanted !== undefined && wanted !== null && wanted !== '') {
                        for (var i = 0; i < select.options.length; i++) {
                            if (select.options[i].value === String(wanted)) { matched = String(wanted); break; }
                        }
                    }
                    select.value = matched;
                    fill(level + 1);
                });
            }

            loadLabelsThen(function () { fill(1); });
        };

        // Re-evaluate which level fields are visible without touching values.
        // The quick-edit modal's "same as present" copies the present selects
        // into the permanent ones directly (innerHTML + value) and then calls
        // refresh() so the permanent cascade shows/hides its levels to match.
        root.refresh = function () {
            refreshVisibility();
        };
    }

    function bindAll() {
        document.querySelectorAll('[data-address-component]').forEach(bindComponent);
    }

    Monetix.bindGeoSelectors = bindAll;

    document.addEventListener('DOMContentLoaded', bindAll);
    document.addEventListener('loadPage', bindAll);
})(window);