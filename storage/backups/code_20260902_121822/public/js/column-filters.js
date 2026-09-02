(function (window) {
    'use strict';

    var Monetix = window.Monetix || (window.Monetix = {});

    /**
     * Column header funnel filters (custom command "column header filter").
     * Every <th data-header-filter> gets a little funnel button in its
     * heading; clicking opens a small popover with the controls matching the
     * filter type:
     *
     *   data-header-filter="options" + data-filter-param
     *       + data-filter-values (JSON array)  -> filter by one of its options
     *   data-header-filter="sort"              -> Oldest / Latest
     *       + data-filter-mode="age"            -> Eldest / Youngest
     *   data-header-filter="date"    + data-filter-param
     *       -> "Older than" / "Later than" date inputs composing
     *          <param>_before / <param>_after
     *   anything else / absent                 -> no funnel
     *
     * Filters live in the URL query string, so they keep working even when the
     * column is hidden via the Columns toggle (visibility never clears the
     * filter).
     */

    function currentParams() {
        var params = new URLSearchParams(window.location.search);
        return params;
    }

    function hasActiveToggles(params) {
        var active = false;
        params.forEach(function (value, key) {
            if (String(value) !== '') { active = true; }
        });
        return active;
    }

    function go(params) {
        var cleaned = new URLSearchParams();
        params.forEach(function (value, key) {
            if (value !== '' && value !== null && value !== undefined) { cleaned.append(key, value); }
        });
        var qs = cleaned.toString();
        var url = qs ? (window.location.pathname + '?' + qs) : window.location.pathname;
        if (Monetix.loadPage) {
            Monetix.loadPage(url, { preserveFocus: true });
        } else {
            window.location.href = url;
        }
    }

    function toggleParam(params, key, value) {
        var current = params.get(key);
        if (current === String(value)) {
            params.delete(key);
        } else {
            params.set(key, value);
        }
    }

    function buildButton() {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'colhdr-btn';
        btn.title = 'Filter column';
        var icon = document.createElement('i');
        icon.className = 'bi bi-funnel';
        icon.setAttribute('aria-hidden', 'true');
        btn.appendChild(icon);
        return btn;
    }

    function buildPop() {
        var pop = document.createElement('div');
        pop.className = 'colhdr-pop';
        pop.hidden = true;
        return pop;
    }

    function buildTitle(text) {
        var title = document.createElement('span');
        title.className = 'colhdr-title';
        title.textContent = text;
        return title;
    }

    function buildOption(param, value, label, active) {
        var opt = document.createElement('button');
        opt.type = 'button';
        opt.className = 'colhdr-opt' + (active ? ' is-active' : '');
        opt.setAttribute('data-value', value);
        var icon = document.createElement('i');
        icon.className = active ? 'bi bi-check-circle-fill' : 'bi bi-circle';
        icon.setAttribute('aria-hidden', 'true');
        var text = document.createElement('span');
        text.textContent = label;
        opt.appendChild(icon);
        opt.appendChild(text);
        return opt;
    }

    function buildBasicActions(params, ownKeys, pop) {
        var actions = document.createElement('div');
        actions.className = 'colhdr-actions';

        var clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'btn btn-outline-secondary btn-sm';
        clear.textContent = 'Clear';
        clear.addEventListener('click', function () {
            ownKeys.forEach(function (key) { params.delete(key); });
            go(params);
        });
        actions.appendChild(clear);

        var apply = document.createElement('button');
        apply.type = 'button';
        apply.className = 'btn btn-primary btn-sm';
        apply.textContent = 'Apply';
        apply.addEventListener('click', function (e) {
            var target = e.target.closest('.colhdr-pop');
            target.hidden = true;
            go(params);
        });
        actions.appendChild(apply);

        pop.appendChild(actions);
    }

    function bindOptions(th, params) {
        var param = th.getAttribute('data-filter-param');
        if (!param) { return; }

        var rawValues = th.getAttribute('data-filter-values');
        if (!rawValues) { return; }

        var values, labels;
        try {
            values = JSON.parse(rawValues) || [];
            labels = JSON.parse(th.getAttribute('data-filter-labels') || '{}') || {};
        } catch (err) {
            return;
        }

        var pop = th.querySelector('.colhdr-pop');
        var current = params.get(param);
        var activeKeys = current ? [current] : [];
        var btn = th.querySelector('.colhdr-btn');

        values.forEach(function (value) {
            var label = labels[value] !== undefined ? labels[value] : value;
            var opt = buildOption(param, value, label, current === String(value));
            opt.addEventListener('click', function () {
                toggleParam(params, param, value);
                go(params);
            });
            pop.appendChild(opt);
        });

        buildBasicActions(params, [param], pop);
    }

    function bindSort(th, params) {
        var mode = th.getAttribute('data-filter-mode') === 'age' ? 'age' : 'date';
        var a = mode === 'age' ? 'eldest' : 'oldest';
        var b = mode === 'age' ? 'youngest' : 'latest';

        var pop = th.querySelector('.colhdr-pop');
        pop.appendChild(buildTitle(mode === 'age' ? 'Sort by age' : 'Sort by date'));

        var current = params.get('sort');

        [a, b].forEach(function (value) {
            var label = value.charAt(0).toUpperCase() + value.slice(1);
            var opt = buildOption('sort', value, label, current === value);
            opt.addEventListener('click', function () {
                toggleParam(params, 'sort', value);
                go(params);
            });
            pop.appendChild(opt);
        });

        buildBasicActions(params, ['sort'], pop);
    }

    function buildDateRow(params, key, isAfter) {
        var wrap = document.createElement('div');
        var label = document.createElement('label');
        label.textContent = isAfter ? 'Later than' : 'Older than';
        var input = document.createElement('input');
        input.type = 'date';
        input.value = params.get(key) || '';
        input.addEventListener('change', function () {
            if (input.value) { params.set(key, input.value); } else { params.delete(key); }
        });
        wrap.appendChild(label);
        wrap.appendChild(input);
        return wrap;
    }

    function bindDate(th, params) {
        var param = th.getAttribute('data-filter-param');
        if (!param) { return; }

        var beforeKey = param + '_before';
        var afterKey = param + '_after';

        var pop = th.querySelector('.colhdr-pop');
        pop.appendChild(buildTitle('Filter by date'));
        pop.appendChild(buildDateRow(params, beforeKey, false));
        pop.appendChild(buildDateRow(params, afterKey, true));

        buildBasicActions(params, [beforeKey, afterKey], pop);
    }

    function bindColumns() {
        document.querySelectorAll('thead th[data-header-filter]').forEach(function (th) {
            if (th.getAttribute('data-colhdr-bound') === '1') { return; }
            th.setAttribute('data-colhdr-bound', '1');

            var type = th.getAttribute('data-header-filter');
            if (type !== 'options' && type !== 'sort' && type !== 'date') { return; }

            var params = currentParams();
            var ownKeys = [];
            if (type === 'options') { ownKeys.push(th.getAttribute('data-filter-param')); }
            if (type === 'sort') { ownKeys.push('sort'); }
            if (type === 'date') {
                var beforeKey = th.getAttribute('data-filter-param') + '_before';
                var afterKey = th.getAttribute('data-filter-param') + '_after';
                ownKeys.push(beforeKey, afterKey);
            }

            // Wrap existing header content into a label span.
            var label = document.createElement('span');
            label.className = 'colhdr-label';
            Array.prototype.slice.call(th.childNodes).forEach(function (node) {
                label.appendChild(node);
            });

            var header = document.createElement('span');
            header.className = 'colhdr';
            var btn = buildButton();
            var icon = btn.querySelector('i');
            var pop = buildPop();
            header.appendChild(label);
            header.appendChild(btn);
            header.appendChild(pop);
            th.appendChild(header);

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = !pop.hidden;
                document.querySelectorAll('.colhdr-pop').forEach(function (p) { p.hidden = true; });
                pop.hidden = !open;
            });

            document.addEventListener('click', function onClickOutside(e) {
                if (!pop.hidden && !pop.contains(e.target) && !btn.contains(e.target)) {
                    pop.hidden = true;
                }
            });

            if (type === 'options') { bindOptions(th, params); }
            if (type === 'sort') { bindSort(th, params); }
            if (type === 'date') { bindDate(th, params); }

            if (ownKeys.some(function (key) { return params.has(key) && params.get(key) !== ''; })) {
                btn.classList.add('is-active');
                icon.classList.remove('bi-funnel');
                icon.classList.add('bi-funnel-fill');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', bindColumns);
    document.addEventListener('loadPage', bindColumns);
})(window);