<div>
    @if($statusMessage !== '')
        <div class="alert alert-success py-2" data-auto-dismiss>{{ $statusMessage }}</div>
    @endif

    @if($errorMessage !== '')
        <div class="alert alert-danger py-2">{{ $errorMessage }}</div>
    @endif

    @if(!$canReorder)
        <div class="alert alert-warning py-2 mb-3">
            <i class="bi bi-lock me-1"></i>{{ $readonlyReason ?: 'This queue is read-only for you.' }}
        </div>
    @endif

    @if(empty($items))
        <div class="empty-fill text-muted">
            <i class="bi bi-people fs-2 d-block mb-2"></i>
            No checked-in patients in this queue.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="col-handle" style="width:42px"></th>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Phone No</th>
                        <th>Serial</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="medical-queue-list" data-queue-list>
                    @foreach($items as $item)
                        <tr data-queue-id="{{ $item['id'] }}"
                            wire:key="queue-{{ $item['id'] }}">
                            <td class="col-handle text-center">
                                @if($canReorder)
                                    <i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i>
                                @endif
                            </td>
                            <td class="text-muted" data-position-badge>#{{ $item['position'] }}</td>
                            <td><a class="fw-semibold text-decoration-none" href="{{ route('medical.appointments.show', $item['id']) }}">{{ $item['patient_name'] }}</a></td>
                            <td>{{ $item['patient_phone'] ?? 'N/A' }}</td>
                            <td>#{{ $item['serial'] }}</td>
                            <td>{{ $item['time'] !== '' ? $item['time'] : 'N/A' }}</td>
                            <td>
                                <span class="badge bg-{{ $item['status'] === 'completed' ? 'success' : ($item['status'] === 'scheduled' ? 'primary' : ($item['status'] === 'in_progress' ? 'warning' : ($item['status'] === 'cancelled' ? 'danger' : 'secondary'))) }}">
                                    {{ ucfirst(str_replace('_', ' ', $item['status'])) }}
                                </span>
                            </td>
                            <td>
                                @if(!empty($item['fee_collected']))
                                    <span class="badge bg-success">Paid</span>
                                @elseif(!empty($item['fee_required']))
                                    <span class="badge bg-danger">Unpaid</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap col-action">
                                <a href="{{ route('medical.appointments.token', $item['id']) }}" target="_blank" class="btn btn-sm btn-outline-dark" title="Print serial token">
                                    <i class="bi bi-printer"></i>
                                </a>
                                @if($canManage)
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            title="Record vitals"
                                            data-vital-id="{{ $item['id'] }}"
                                            data-vital-patient="{{ $item['patient_name'] }}"
                                            data-vital-serial="{{ $item['serial'] }}"
                                            data-vitals-latest='@json($item['latest_vitals'])'
                                            onclick="openVitalsModalFrom(this)">
                                        <i class="bi bi-heart-pulse"></i>
                                    </button>
                                    @if($item['status'] === 'checked_in')
                                        @if($item['fee_required'] && empty($item['fee_collected']))
                                            <button type="button" class="btn btn-sm btn-success" title="Collect Visit Fee"
                                                    data-fee-url="{{ route('medical.appointments.collect-fee', $item['id']) }}"
                                                    data-fee-action="start"
                                                    data-fee-patient="{{ $item['patient_name'] }}"
                                                    data-fee-amount="{{ number_format((float) $item['fee_amount'], 2, '.', '') }}"
                                                    data-fee-type="{{ $item['fee_type'] }}"
                                                    onclick="openFeeModal(this)">
                                                <i class="bi bi-cash-coin"></i>
                                            </button>
                                        @else
                                            <form action="{{ route('medical.appointments.start', $item['id']) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-info" title="Start consultation">
                                                <i class="bi bi-play-circle{{ ($item['fee_required'] && !empty($item['fee_collected'])) ? ' me-1' : '' }}"></i>@if($item['fee_required'] && !empty($item['fee_collected']))
Start Consultation
@endif
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        @if($item['fee_required'])
                                            <button type="button" class="btn btn-sm btn-outline-success" title="Complete — fee due"
                                                    data-fee-url="{{ route('medical.appointments.collect-fee', $item['id']) }}"
                                                    data-fee-action="complete"
                                                    data-fee-patient="{{ $item['patient_name'] }}"
                                                    data-fee-amount="{{ number_format((float) $item['fee_amount'], 2, '.', '') }}"
                                                    data-fee-type="{{ $item['fee_type'] }}"
                                                    onclick="openFeeModal(this)">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        @else
                                            <form action="{{ route('medical.appointments.complete', $item['id']) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Complete">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    @if(($item['fee_timing'] ?? 'none') === 'pre' && !empty($item['fee_collected']))
                                        <a href="{{ route('medical.prescriptions.create', array_filter(['patient_id' => $item['patient_id'], 'fee_appointment_id' => $item['status'] === 'in_progress' ? $item['id'] : null])) }}"
                                           class="btn btn-sm btn-outline-primary" title="Write prescription">
                                            <i class="bi bi-file-earmark-medical"></i>
                                        </a>
                                    @elseif(($item['fee_timing'] ?? 'none') === 'post' && $item['status'] === 'in_progress')
                                        <a href="{{ route('medical.prescriptions.create', ['patient_id' => $item['patient_id'], 'fee_appointment_id' => $item['id']]) }}"
                                           class="btn btn-sm btn-outline-primary" title="Write prescription">
                                            <i class="bi bi-file-earmark-medical"></i>
                                        </a>
                                    @endif
                                    <form action="{{ route('medical.appointments.cancel', $item['id']) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel"
                                                onclick="return confirm('Cancel this appointment? It will leave the queue.')">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canReorder)
    @script
    <script>
    (function () {
        // Bound once per page on document (NOT on the tbody): Livewire morphs
        // may replace the tbody on Refresh/save, which would orphan
        // element-bound listeners and silently kill dragging. Delegation
        // always resolves the CURRENT list, so drag survives re-renders.
        if (window.__queueDragBound === '1') return;
        window.__queueDragBound = '1';

        // Filter-row refresh button (outside Livewire) calls in here.
        window.refreshQueueWidget = function () {
            try { $wire.call('loadQueue'); }
            catch (_) { window.location.reload(); }
        };
        let draggedId = null;
        let startOrder = null; // DOM order when the drag began
        let lastSaved = null;  // last order string sent to the server
        let saveTimer = null;
        let inFlight = false;
        let waitCycles = 0;

        function queueList() {
            return document.getElementById('medical-queue-list');
        }
        function queueRowFromEvent(e) {
            if (!queueList() || !e.target || !e.target.closest) return null;
            return e.target.closest('#medical-queue-list [data-queue-id]');
        }
        function currentOrder() {
            const list = queueList();
            if (!list) return [];
            return Array.from(list.querySelectorAll('[data-queue-id]'))
                .map(function (c) { return parseInt(c.getAttribute('data-queue-id'), 10); })
                .filter(function (id) { return !isNaN(id); });
        }
        // Optimistic: renumber position badges the instant the card lands,
        // so the list looks final with zero waiting on the server.
        function renumberBadges() {
            const list = queueList();
            if (!list) return;
            list.querySelectorAll('[data-queue-id]').forEach(function (card, i) {
                const badge = card.querySelector('[data-position-badge]');
                if (badge) badge.textContent = '#' + (i + 1);
            });
        }
        function setSaveState(html) {
            const el = document.getElementById('queue-save-state');
            if (el) el.innerHTML = html || '';
        }
        function orderKey(order) {
            return order.join(',');
        }
        // Debounced save: rapid successive drops collapse into one request.
        function scheduleSave() {
            clearTimeout(saveTimer);
            waitCycles = 0;
            saveTimer = setTimeout(saveNow, 500);
        }
        // Single-flight save with a bounded wait (never spins or loops
        // forever): if a request is in flight we retry briefly, then give
        // up with a retry hint. Any DOM move that lands mid-flight gets
        // one catch-up save afterwards.
        function saveNow() {
            const order = currentOrder();
            if (order.length === 0) return;
            const key = orderKey(order);
            if (key === lastSaved) { setSaveState(''); return; } // already stored
            if (inFlight) {
                if (++waitCycles > 40) { // ~20s without a response: stop waiting
                    inFlight = false;
                    waitCycles = 0;
                    setSaveState('<i class="bi bi-exclamation-triangle me-1"></i>Taking too long — drag again to retry');
                    return;
                }
                saveTimer = setTimeout(saveNow, 500);
                return;
            }
            waitCycles = 0;
            inFlight = true;
            lastSaved = key;
            setSaveState('<span class="spinner-border spinner-border-sm me-1"></span>Saving…');
            let settled = false;
            const done = function (ok) {
                if (settled) return;
                settled = true;
                inFlight = false;
                if (ok) {
                    setSaveState('<i class="bi bi-check-lg me-1"></i>Saved');
                    if (orderKey(currentOrder()) !== lastSaved) scheduleSave(); // moved mid-flight
                } else {
                    lastSaved = null; // allow retry of the same order
                    setSaveState('<i class="bi bi-exclamation-triangle me-1"></i>Save failed — drag again to retry');
                }
            };
            // Watchdog: the spinner must never spin forever.
            setTimeout(function () {
                if (!settled && inFlight) {
                    inFlight = false;
                    lastSaved = null;
                    settled = true;
                    setSaveState('<i class="bi bi-exclamation-triangle me-1"></i>Taking too long — drag again to retry');
                }
            }, 20000);
            try {
                Promise.resolve($wire.updateQueueOrder(order)).then(
                    function () { done(true); },
                    function (err) { try { console.error('[queue-save] failed', err); } catch (_) {} done(false); }
                );
            } catch (err) {
                try { console.error('[queue-save] failed', err); } catch (_) {}
                done(false);
            }
        }

        document.addEventListener('dragstart', function (e) {
            const card = queueRowFromEvent(e);
            if (!card) return;
            draggedId = card.getAttribute('data-queue-id');
            startOrder = orderKey(currentOrder());
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', draggedId); } catch (_) {}
            card.classList.add('dragging');
        });

        document.addEventListener('dragend', function () {
            const list = queueList();
            if (list) list.querySelectorAll('[data-queue-id]').forEach(function (c) {
                c.classList.remove('dragging', 'border-primary');
            });
            // A drop outside any row fires dragend without drop: the DOM may
            // still have moved (dragover shuffling), so save when the order
            // differs from dragstart. This is what makes every drag real.
            if (startOrder !== null && orderKey(currentOrder()) !== startOrder) {
                renumberBadges();
                scheduleSave();
            }
            draggedId = null;
            startOrder = null;
        });

        document.addEventListener('dragover', function (e) {
            const target = queueRowFromEvent(e);
            if (!target || !draggedId) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const list = queueList();
            if (!list) return;
            const dragged = Array.from(list.querySelectorAll('[data-queue-id]'))
                .find(function (c) { return c.getAttribute('data-queue-id') === String(draggedId); });
            if (!dragged || dragged === target) return;
            const rect = target.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            // FLIP shuffle (visual only — persist happens on drop): measure,
            // move the dragged row aside, animate the rest into place.
            const rows = Array.from(list.querySelectorAll('[data-queue-id]'));
            const prev = new Map();
            rows.forEach(function (tr) { prev.set(tr, tr.getBoundingClientRect().top); });
            let moved = false;
            if (after && target.nextElementSibling !== dragged) {
                target.after(dragged);
                moved = true;
            } else if (!after && target.previousElementSibling !== dragged) {
                target.before(dragged);
                moved = true;
            }
            if (!moved) return;
            target.classList.add('border-primary');
            renumberBadges();
            Array.from(list.querySelectorAll('[data-queue-id]')).forEach(function (tr) {
                const delta = prev.get(tr) - tr.getBoundingClientRect().top;
                if (delta) {
                    tr.style.transition = 'none';
                    tr.style.transform = 'translateY(' + delta + 'px)';
                }
            });
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    const live = queueList();
                    if (!live) return;
                    live.querySelectorAll('[data-queue-id]').forEach(function (tr) {
                        tr.style.transition = 'transform .3s cubic-bezier(.2,.85,.35,1)';
                        tr.style.transform = '';
                    });
                });
            });
        });

        document.addEventListener('dragleave', function (e) {
            const card = queueRowFromEvent(e);
            if (card) card.classList.remove('border-primary');
        });

        document.addEventListener('drop', function (e) {
            const target = queueRowFromEvent(e);
            if (!target || !draggedId) return;
            e.preventDefault();

            const list = queueList();
            const cards = Array.from(list.querySelectorAll('[data-queue-id]'));
            const dragged = cards.find(function (c) { return c.getAttribute('data-queue-id') === String(draggedId); });
            if (!dragged || dragged === target) return;

            const rect = target.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            if (after) target.after(dragged);
            else target.before(dragged);

            renumberBadges();
            scheduleSave();
        });
    })();
    </script>
    @endscript
    @endif
</div>
