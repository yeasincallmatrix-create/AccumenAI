<div>
    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
        <small id="queue-save-state" class="text-muted"></small>
        <small class="text-muted" wire:loading wire:target="startProgress,complete,cancel,updateQueueOrder,loadQueue">
            <span class="spinner-border spinner-border-sm me-1"></span>Updating…
        </small>
    </div>

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
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="medical-queue-list" data-queue-list>
                    @foreach($items as $item)
                        <tr data-queue-id="{{ $item['id'] }}"
                            wire:key="queue-{{ $item['id'] }}"
                            class="{{ $item['status'] === 'in_progress' ? 'table-success' : '' }}">
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
                                <span class="badge bg-{{ $item['status'] === 'in_progress' ? 'success' : 'info' }}">
                                    {{ ucfirst(str_replace('_', ' ', $item['status'])) }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap col-action">
                                <a href="{{ route('medical.appointments.edit', $item['id']) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                @if($canManage)
                                    @if($item['status'] === 'checked_in')
                                        @if($item['fee_required'])
                                            <button type="button" class="btn btn-sm btn-outline-info" title="Start consultation — fee due"
                                                    data-fee-url="{{ route('medical.appointments.collect-fee', $item['id']) }}"
                                                    data-fee-action="start"
                                                    data-fee-patient="{{ $item['patient_name'] }}"
                                                    data-fee-amount="{{ number_format((float) $item['fee_amount'], 2, '.', '') }}"
                                                    data-fee-type="{{ $item['fee_type'] }}"
                                                    onclick="openFeeModal(this)">
                                                <i class="bi bi-play-circle"></i>
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-info"
                                                     wire:click="startProgress({{ $item['id'] }})" wire:loading.attr="disabled" title="Start consultation">
                                                <i class="bi bi-play-circle"></i>
                                            </button>
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
                                            <button type="button" class="btn btn-sm btn-outline-success"
                                                     wire:click="complete({{ $item['id'] }})" wire:loading.attr="disabled" title="Complete">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        @endif
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            wire:click="cancel({{ $item['id'] }})" wire:loading.attr="disabled"
                                            wire:confirm="Cancel this appointment? It will leave the queue." title="Cancel">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
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
        let saveTimer = null;
        let saving = false;
        let pendingOrder = null;
        let lastSent = null;

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
        function escHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function failSave(err) {
            try { console.error('[queue-save] failed', err); } catch (_) {}
            let msg = 'Save failed — drag again';
            try {
                const m = err && (err.message || (err.response && err.response.status));
                if (m) msg = 'Save failed (' + escHtml(m) + ') — drag again';
            } catch (_) {}
            setSaveState('<i class="bi bi-exclamation-triangle me-1"></i>' + msg);
        }
        function persist(order) {
            const key = order.join(',');
            if (key === lastSent) return; // server already holds this order
            lastSent = key;
            saving = true;
            setSaveState('<span class="spinner-border spinner-border-sm me-1"></span>Saving…');
            let request;
            try {
                request = $wire.updateQueueOrder(order);
            } catch (err) {
                saving = false;
                lastSent = null;
                failSave(err);
                return;
            }
            // Failsafe: the spinner must never spin forever — if the
            // roundtrip neither resolves nor rejects, reset and offer retry.
            const watchdog = setTimeout(function () {
                if (!saving) return;
                saving = false;
                lastSent = null;
                setSaveState('<i class="bi bi-exclamation-triangle me-1"></i>Taking too long — drag again to retry');
            }, 15000);
            Promise.resolve(request).then(function () {
                clearTimeout(watchdog);
                saving = false;
                setSaveState('<i class="bi bi-check-lg me-1"></i>Saved');
                if (pendingOrder) { // a newer drop landed mid-flight
                    const next = pendingOrder;
                    pendingOrder = null;
                    persist(next);
                }
            }).catch(function (err) {
                clearTimeout(watchdog);
                saving = false;
                lastSent = null; // allow retry of the same order
                failSave(err);
            });
        }
        // Debounced persist: rapid successive drops collapse into one
        // roundtrip instead of one overlapping request per drop.
        function scheduleSave() {
            clearTimeout(saveTimer);
            if (saving) { pendingOrder = currentOrder(); return; }
            saveTimer = setTimeout(function () {
                if (saving) { pendingOrder = currentOrder(); return; }
                persist(currentOrder());
            }, 700);
        }

        document.addEventListener('dragstart', function (e) {
            const card = queueRowFromEvent(e);
            if (!card) return;
            draggedId = card.getAttribute('data-queue-id');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', draggedId); } catch (_) {}
            card.classList.add('dragging');
        });

        document.addEventListener('dragend', function () {
            const list = queueList();
            if (list) list.querySelectorAll('[data-queue-id]').forEach(function (c) {
                c.classList.remove('dragging', 'border-primary');
            });
            draggedId = null;
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
