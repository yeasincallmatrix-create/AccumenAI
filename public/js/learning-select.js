(function (window) {
    'use strict';
    var Monetix = window.Monetix || (window.Monetix = {});

    /**
     * Learning Structure N-level cascading selector.
     * Generic — derives levels from /academic/structure/options.
     * Pattern cloned from geo-select.js: stale-request protection via reqSeq/AbortController.
     *
     * Usage: <div data-learning-component data-options-endpoint="/academic/structure/options"
     *                 data-nodes-endpoint="/academic/structure/nodes" data-branch-id="1"
     *                 data-selected='{"1":10,"2":25}'></div>
     * Each level renders a <select data-learning-level="N"> with label from level metadata.
     */

    function bindComponent(root) {
        if (root.getAttribute('data-bound') === '1') return;
        root.setAttribute('data-bound', '1');

        var optionsEndpoint = root.getAttribute('data-options-endpoint') || '/academic/structure/options';
        var nodesEndpoint = root.getAttribute('data-nodes-endpoint') || '/academic/structure/nodes';
        var branchId = root.getAttribute('data-branch-id') || null;
        var selectedAttr = root.getAttribute('data-selected');
        var selected = {};
        try { if (selectedAttr) selected = JSON.parse(selectedAttr); } catch (e) {}
        var seq = 0;
        var abortControllers = {};

        var levelsMeta = []; // [{level_order,label,label_key,value_source}]
        var templateInfo = null;

        function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
        function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

        function branchQuery() {
            return branchId ? '&branch_id=' + encodeURIComponent(branchId) : '';
        }

        function clearFrom(levelOrder) {
            // Clear selections for levelOrder and deeper
            for (var i = levelOrder; i <= levelsMeta.length; i++) {
                var sel = root.querySelector('[data-learning-level="' + i + '"]');
                if (sel) {
                    sel.value = '';
                    // Remove options beyond placeholder, disable
                    while (sel.options.length > 1) sel.remove(1);
                    if (i >= levelOrder) sel.disabled = true;
                    var status = sel.parentElement ? sel.parentElement.querySelector('[data-learning-status]') : null;
                    if (status) status.textContent = '';
                }
            }
        }

        function setLoading(levelOrder, loading) {
            var sel = root.querySelector('[data-learning-level="' + levelOrder + '"]');
            if (!sel) return;
            var status = sel.parentElement ? sel.parentElement.querySelector('[data-learning-status]') : null;
            if (status) status.textContent = loading ? 'Loading...' : '';
        }

        function renderOptions(select, options) {
            var placeholder = select.options[0] ? select.options[0].textContent : 'Select';
            select.innerHTML = '';
            var ph = document.createElement('option');
            ph.value = '';
            ph.textContent = placeholder;
            select.appendChild(ph);
            options.forEach(function (o) {
                var opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = o.name;
                opt.setAttribute('data-code', o.code || '');
                select.appendChild(opt);
            });
        }

        function loadNodes(levelOrder, parentNodeId, mySeq) {
            var sel = root.querySelector('[data-learning-level="' + levelOrder + '"]');
            if (!sel) return Promise.resolve();
            var currentSeq = mySeq !== undefined ? mySeq : ++seq;
            // Abort previous pending for this level
            if (abortControllers[levelOrder]) { try { abortControllers[levelOrder].abort(); } catch (e) {} }
            var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            if (controller) abortControllers[levelOrder] = controller;

            setLoading(levelOrder, true);
            sel.disabled = true;

            var url = nodesEndpoint + '?level_order=' + encodeURIComponent(levelOrder) + branchQuery();
            if (parentNodeId !== null && parentNodeId !== undefined && parentNodeId !== '') {
                url += '&parent_node_id=' + encodeURIComponent(parentNodeId);
            }
            if (branchId) url += '&branch_id=' + encodeURIComponent(branchId);

            var fetchOpts = controller ? { signal: controller.signal } : {};
            // Use Monetix.request if available, else fetch
            var promise;
            if (Monetix.request) {
                promise = Monetix.request(url, fetchOpts);
            } else {
                promise = fetch(url, fetchOpts).then(function (r) { return r.json(); });
            }

            return promise.then(function (res) {
                if (currentSeq !== seq) return; // stale
                if (!res || !res.success) {
                    renderOptions(sel, []);
                    var status = sel.parentElement ? sel.parentElement.querySelector('[data-learning-status]') : null;
                    if (status) status.textContent = res && res.message ? res.message : 'Error loading options';
                    setLoading(levelOrder, false);
                    return;
                }
                var opts = (res.data && res.data.options) || [];
                renderOptions(sel, opts);
                sel.disabled = false;
                setLoading(levelOrder, false);
                var status2 = sel.parentElement ? sel.parentElement.querySelector('[data-learning-status]') : null;
                if (status2) status2.textContent = opts.length === 0 ? 'No options available' : '';
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                if (currentSeq !== seq) return;
                setLoading(levelOrder, false);
                var status = sel.parentElement ? sel.parentElement.querySelector('[data-learning-status]') : null;
                if (status) status.textContent = 'Error loading options';
            });
        }

        function onLevelChange(changedOrder) {
            var sel = root.querySelector('[data-learning-level="' + changedOrder + '"]');
            var val = sel ? sel.value : '';
            // Increment seq to invalidate stale child responses
            seq++;
            // Clear deeper levels
            clearFrom(changedOrder + 1);
            if (!val) return;
            if (changedOrder < levelsMeta.length) {
                loadNodes(changedOrder + 1, val, seq);
            }
            // Emit event for external consumers
            try {
                root.dispatchEvent(new CustomEvent('learning:change', { detail: { level: changedOrder, value: val } }));
            } catch (e) {}
        }

        function buildUI() {
            root.innerHTML = '';
            if (!levelsMeta.length) {
                var empty = document.createElement('div');
                empty.className = 'text-muted small';
                empty.textContent = 'No learning structure configured.';
                root.appendChild(empty);
                return;
            }
            var header = document.createElement('div');
            header.className = 'mb-2 small text-muted';
            if (templateInfo) header.textContent = templateInfo.name + ' (' + templateInfo.code + ')';
            root.appendChild(header);

            levelsMeta.forEach(function (lvl) {
                var wrap = document.createElement('div');
                wrap.className = 'mb-2';
                wrap.setAttribute('data-learning-field', lvl.level_order);
                var label = document.createElement('label');
                label.className = 'form-label small fw-medium';
                label.textContent = lvl.label;
                var select = document.createElement('select');
                select.className = 'form-select form-select-sm';
                select.setAttribute('data-learning-level', lvl.level_order);
                select.setAttribute('data-label-key', lvl.label_key || '');
                var ph = document.createElement('option');
                ph.value = '';
                ph.textContent = '-- ' + lvl.label + ' --';
                select.appendChild(ph);
                select.disabled = lvl.level_order !== 1;
                var status = document.createElement('div');
                status.className = 'form-text small';
                status.setAttribute('data-learning-status', '1');
                wrap.appendChild(label);
                wrap.appendChild(select);
                wrap.appendChild(status);
                root.appendChild(wrap);
                select.addEventListener('change', function () { onLevelChange(lvl.level_order); });
            });
        }

        function preselectChain() {
            // Load level1 already optionally populated from options.nodes; if selected provided, walk chain
            var hasSelected = selected && Object.keys(selected).length > 0;
            if (!hasSelected) return;

            // If level1 nodes were embedded in options, they are not yet rendered into select
            // We need to load sequentially: for each level with selected value, ensure parent loaded
            (async function () {
                for (var i = 1; i <= levelsMeta.length; i++) {
                    var wanted = selected[String(i)] || selected[i];
                    if (!wanted) {
                        // If no wanted but previous level selected, we need to load next level's options
                        var prevSel = root.querySelector('[data-learning-level="' + (i - 1) + '"]');
                        var prevVal = prevSel ? prevSel.value : null;
                        if (prevVal && i > 1) {
                            var curSeq = ++seq;
                            await loadNodes(i, prevVal, curSeq);
                        }
                        continue;
                    }
                    var sel = root.querySelector('[data-learning-level="' + i + '"]');
                    if (!sel) continue;
                    // Ensure this level's options are loaded (for level1 they may already be from options, but reload if needed)
                    if (sel.options.length <= 1) {
                        var parentId = null;
                        if (i > 1) {
                            var parentSel = root.querySelector('[data-learning-level="' + (i - 1) + '"]');
                            parentId = parentSel ? parentSel.value : null;
                            if (!parentId) continue;
                        }
                        var s = ++seq;
                        await loadNodes(i, parentId, s);
                        sel = root.querySelector('[data-learning-level="' + i + '"]');
                    }
                    // Set value if exists
                    var found = false;
                    for (var o = 0; o < sel.options.length; o++) {
                        if (sel.options[o].value === String(wanted)) { found = true; break; }
                    }
                    if (found) {
                        sel.value = String(wanted);
                        sel.disabled = false;
                        // Trigger load of next if not last
                        if (i < levelsMeta.length) {
                            var nextSeq = ++seq;
                            await loadNodes(i + 1, String(wanted), nextSeq);
                        }
                    }
                }
            })();
        }

        function init() {
            var url = optionsEndpoint + '?_=' + Date.now() + branchQuery();
            var p = Monetix.request ? Monetix.request(url) : fetch(url).then(function (r) { return r.json(); });
            p.then(function (res) {
                if (!res || !res.success) {
                    root.innerHTML = '<div class="text-danger small">Failed to load learning structure.</div>';
                    return;
                }
                var data = res.data || {};
                templateInfo = data.template || null;
                levelsMeta = (data.levels || []).slice().sort(function (a, b) { return a.level_order - b.level_order; });
                buildUI();
                // Populate level 1 nodes if embedded
                if (levelsMeta.length && levelsMeta[0].nodes && levelsMeta[0].nodes.length) {
                    var sel1 = root.querySelector('[data-learning-level="1"]');
                    if (sel1) {
                        // Flatten tree: top-level nodes only at level1
                        var opts = levelsMeta[0].nodes.map(function (n) {
                            return { id: n.id, name: n.name, code: n.code };
                        });
                        renderOptions(sel1, opts);
                        sel1.disabled = false;
                    }
                } else if (levelsMeta.length) {
                    // Load level 1 via nodes endpoint for branch-filtered case
                    loadNodes(1, null, ++seq);
                }
                preselectChain();
            }).catch(function () {
                root.innerHTML = '<div class="text-danger small">Failed to load learning structure.</div>';
            });
        }

        // Branch change integration: external code can dispatch event or call root.reload
        root.reload = function (newBranchId) {
            if (newBranchId !== undefined) branchId = newBranchId ? String(newBranchId) : null;
            seq++; // invalidate stale
            Object.keys(abortControllers).forEach(function (k) { try { abortControllers[k].abort(); } catch (e) {} });
            init();
        };
        root.addEventListener('branch:change', function (e) {
            var bid = e.detail && e.detail.branch_id ? String(e.detail.branch_id) : null;
            root.reload(bid);
        });

        init();
    }

    function bindAll() {
        document.querySelectorAll('[data-learning-component]').forEach(bindComponent);
    }

    Monetix.bindLearningSelectors = bindAll;
    window.LearningSelect = { bindComponent: bindComponent, bindAll: bindAll };

    document.addEventListener('DOMContentLoaded', bindAll);
    document.addEventListener('loadPage', bindAll);
})(window);
