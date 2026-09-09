import React, { useState, useEffect, useCallback, useRef } from 'react';

/**
 * Appointments (OPD) list — React rebuild of the Blade table on
 * /medical/appointments (Appointments tab).
 *
 * Props:
 * - initialAppointments: rows mapped by AppointmentController::mapAppointmentsForReact()
 * - doctors: [{ id, name }] (already doctor-fenced server-side)
 * - filters: { date, doctor_id, status } current values
 * - dataUrl: JSON feed (same filters as query params, polled every 10s)
 * - indexUrl: reset link (full page load)
 * - canDeleteFinalized: false hides Cancel on completed/paid rows, except
 *   for the treating doctor's own rows (row.is_own_doctor)
 *
 * Row actions POST through the normal web routes (dynamic form submit) so
 * flash messages + validation behave exactly like the Blade list. Transfer
 * reuses the existing Blade modal via window.openTransferModal(id); freshly
 * polled rows are merged into window.transferUrls/transferInfo for it.
 */
const STATUS_OPTIONS = [
    ['scheduled', 'Scheduled'],
    ['checked_in', 'Checked In'],
    ['in_progress', 'In Progress'],
    ['completed', 'Completed'],
    ['cancelled', 'Cancelled'],
    ['no_show', 'No Show'],
];

const statusBadge = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-success';
        case 'scheduled':
            return 'bg-primary';
        case 'in_progress':
            return 'bg-warning text-dark';
        case 'cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
};

const statusLabel = (status) => (status || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/** Full-page POST (same UX + flash messages as the Blade list). */
const submitForm = (url, method = 'POST') => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.style.display = 'none';
    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrfToken();
    form.appendChild(token);
    if (method !== 'POST') {
        const spoof = document.createElement('input');
        spoof.type = 'hidden';
        spoof.name = '_method';
        spoof.value = method;
        form.appendChild(spoof);
    }
    document.body.appendChild(form);
    form.submit();
};

const AppointmentList = ({ initialAppointments, doctors, filters: initialFilters, dataUrl, indexUrl, canDeleteFinalized }) => {
    const [appointments, setAppointments] = useState(initialAppointments || []);
    const [filters, setFilters] = useState({
        date: initialFilters?.date || '',
        doctor_id: initialFilters?.doctor_id || '',
        status: initialFilters?.status || '',
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [dragId, setDragId] = useState(null);
    const filtersRef = useRef(filters);
    filtersRef.current = filters;
    const listRef = useRef(null);
    const prevTopsRef = useRef(new Map());

    const syncTransferMaps = useCallback((rows) => {
        // The Blade transfer modal reads these globals; keep them fresh so
        // transfer works for rows that arrived via polling too.
        window.transferUrls = window.transferUrls || {};
        window.transferInfo = window.transferInfo || {};
        rows.forEach((row) => {
            window.transferUrls[row.id] = row.transfer_url;
            window.transferInfo[row.id] = row.transfer_info;
        });
    }, []);

    const fetchRows = useCallback((activeFilters) => {
        if (!dataUrl) return Promise.resolve();
        setLoading(true);
        const params = new URLSearchParams();
        if (activeFilters.date) params.set('date', activeFilters.date);
        if (activeFilters.doctor_id) params.set('doctor_id', activeFilters.doctor_id);
        if (activeFilters.status) params.set('status', activeFilters.status);
        const url = params.toString() ? `${dataUrl}?${params}` : dataUrl;
        return fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((res) => {
                if (!res.ok) throw new Error(`Appointments refresh failed (${res.status})`);
                return res.json();
            })
            .then((data) => {
                const rows = Array.isArray(data) ? data : [];
                setAppointments(rows);
                syncTransferMaps(rows);
                setError(null);
            })
            .catch((err) => setError(err.message))
            .finally(() => setLoading(false));
    }, [dataUrl, syncTransferMaps]);

    // Initial transfer-map sync for server-rendered rows.
    useEffect(() => {
        syncTransferMaps(initialAppointments || []);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Live refresh every 10 seconds with the current filters.
    useEffect(() => {
        if (!dataUrl) return undefined;
        const interval = setInterval(() => fetchRows(filtersRef.current), 10000);
        return () => clearInterval(interval);
    }, [dataUrl, fetchRows]);

    const onFilterChange = (name, value) => {
        const next = { ...filtersRef.current, [name]: value };
        setFilters(next);
        fetchRows(next);
    };

    const onCancel = (row) => {
        // Completed / paid rows are finalized — the treating doctor or an
        // admin only (the server enforces this too; this just hides the button).
        if (row.is_finalized && !canDeleteFinalized && !row.is_own_doctor) {
            window.alert('Only the treating doctor or an administrator may delete a completed or paid appointment.');
            return;
        }
        if (window.confirm('Are you sure you want to cancel this appointment?')) {
            submitForm(row.cancel_url, 'DELETE');
        }
    };

    const onTransfer = (row) => {
        if (typeof window.openTransferModal === 'function') {
            window.openTransferModal(row.id);
        }
    };

    // Visual-only drag reorder (session only). Other rows glide to their
    // new spots with a FLIP animation instead of snapping.
    const moveRow = (fromId, toId, after) => {
        if (fromId === toId) return;
        // Snapshot row tops before the reorder so we can animate the delta.
        const tops = new Map();
        listRef.current?.querySelectorAll('tr[data-row-id]').forEach((tr) => {
            tops.set(Number(tr.getAttribute('data-row-id')), tr.getBoundingClientRect().top);
        });
        prevTopsRef.current = tops;
        setAppointments((prev) => {
            const from = prev.findIndex((r) => r.id === fromId);
            const to = prev.findIndex((r) => r.id === toId);
            if (from < 0 || to < 0) return prev;
            const next = [...prev];
            const [moved] = next.splice(from, 1);
            let insertAt = next.findIndex((r) => r.id === toId) + (after ? 1 : 0);
            next.splice(insertAt, 0, moved);
            return next;
        });
    };

    // FLIP: after each reorder while dragging, slide rows from their old
    // tops to their new ones.
    useEffect(() => {
        if (dragId === null) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const tops = prevTopsRef.current;
        prevTopsRef.current = new Map();
        if (!tops || tops.size === 0) return;
        const rows = listRef.current?.querySelectorAll('tr[data-row-id]');
        if (!rows) return;
        const animated = [];
        rows.forEach((tr) => {
            const id = Number(tr.getAttribute('data-row-id'));
            if (!tops.has(id)) return;
            const delta = tops.get(id) - tr.getBoundingClientRect().top;
            if (delta) {
                tr.style.transition = 'none';
                tr.style.transform = `translateY(${delta}px)`;
                animated.push(tr);
            }
        });
        if (animated.length === 0) return;
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                animated.forEach((tr) => {
                    tr.style.transition = 'transform .3s cubic-bezier(.2,.85,.35,1)';
                    tr.style.transform = '';
                });
            });
        });
    }, [appointments, dragId]);

    return (
        <div>
            <div className="row g-2 mb-3">
                <div className="col-md-3">
                    <input
                        type="date"
                        className="form-control"
                        value={filters.date}
                        onChange={(e) => onFilterChange('date', e.target.value)}
                        aria-label="Filter by date"
                    />
                </div>
                <div className="col-md-3">
                    <select
                        className="form-select"
                        value={filters.doctor_id}
                        onChange={(e) => onFilterChange('doctor_id', e.target.value)}
                        aria-label="Filter by doctor"
                    >
                        <option value="">All Doctors</option>
                        {(doctors || []).map((d) => (
                            <option key={d.id} value={d.id}>{d.name}</option>
                        ))}
                    </select>
                </div>
                <div className="col-md-3">
                    <select
                        className="form-select"
                        value={filters.status}
                        onChange={(e) => onFilterChange('status', e.target.value)}
                        aria-label="Filter by status"
                    >
                        <option value="">All Status</option>
                        {STATUS_OPTIONS.map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                </div>
                <div className="col-md-3 text-end">
                    <a href={indexUrl} className="btn btn-secondary">Reset</a>
                    {loading && <span className="ms-2 spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true" />}
                </div>
            </div>

            {error && (
                <div className="alert alert-warning py-2" role="alert">
                    <i className="bi bi-exclamation-triangle me-1"></i>{error}
                </div>
            )}

            {appointments.length === 0 ? (
                <div className="empty-fill text-muted">
                    <i className="bi bi-calendar-x fs-2 d-block mb-2"></i>
                    No appointments found for this date.
                </div>
            ) : (
            <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style={{ width: 42 }}></th>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Phone No</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Serial</th>
                            <th>Status</th>
                            <th className="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody ref={listRef}>
                        {appointments.map((row, index) => (
                            <tr
                                key={row.id}
                                data-row-id={row.id}
                                draggable
                                onDragStart={(e) => {
                                    setDragId(row.id);
                                    e.dataTransfer.effectAllowed = 'move';
                                    e.dataTransfer.setData('text/plain', String(row.id));
                                    // Hide the native drag ghost (translucent copy under
                                    // the cursor) — the elevated source row plus the
                                    // gliding siblings already show the drag state.
                                    try {
                                        const blank = new Image();
                                        blank.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
                                        e.dataTransfer.setDragImage(blank, 0, 0);
                                    } catch (_) {}
                                }}
                                onDragOver={(e) => {
                                    if (dragId === null || dragId === row.id) return;
                                    e.preventDefault();
                                    e.dataTransfer.dropEffect = 'move';
                                    const rect = e.currentTarget.getBoundingClientRect();
                                    moveRow(dragId, row.id, (e.clientY - rect.top) > rect.height / 2);
                                }}
                                onDragEnd={() => setDragId(null)}
                                className={dragId === row.id ? 'dragging' : ''}
                            >
                                <td className="text-center">
                                    <i className="bi bi-grip-vertical" title="Drag to reorder" style={{ cursor: 'grab' }}></i>
                                </td>
                                <td>{index + 1}</td>
                                <td>
                                    {row.has_patient ? (
                                        <a className="fw-semibold text-decoration-none" href={row.show_url}>{row.patient_name}</a>
                                    ) : (
                                        <span className="text-muted">N/A</span>
                                    )}
                                </td>
                                <td className={row.has_patient ? '' : 'text-muted'}>{row.patient_phone}</td>
                                <td>{row.doctor_name}</td>
                                <td>{row.date_display}</td>
                                <td>{row.time_display}</td>
                                <td>#{row.serial_number}</td>
                                <td>
                                    <span className={`badge ${statusBadge(row.status)}`}>
                                        {statusLabel(row.status)}
                                    </span>
                                </td>
                                <td className="text-end text-nowrap">
                                    <a href={row.edit_url} className="btn btn-sm btn-outline-secondary me-1" title="Edit">
                                        <i className="bi bi-pencil-square"></i>
                                    </a>
                                    {row.can_checkin && (
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-success me-1"
                                            title="Check in"
                                            onClick={() => submitForm(row.checkin_url, 'POST')}
                                        >
                                            <i className="bi bi-box-arrow-in-right me-1"></i>Check in
                                        </button>
                                    )}
                                    {row.can_transfer && (
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-info me-1"
                                            title="Transfer to another date"
                                            onClick={() => onTransfer(row)}
                                        >
                                            <i className="bi bi-calendar-date"></i>
                                        </button>
                                    )}
                                    {(!row.is_finalized || canDeleteFinalized || row.is_own_doctor) && (
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-danger"
                                            title="Cancel"
                                            onClick={() => onCancel(row)}
                                        >
                                            <i className="bi bi-x-lg"></i>
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            )}
        </div>
    );
};

export default AppointmentList;
