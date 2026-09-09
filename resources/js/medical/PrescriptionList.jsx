import React, { useState, useEffect, useCallback, useMemo } from 'react';

/**
 * Prescription list with status filter + 10s live refresh.
 *
 * Props:
 * - initialPrescriptions: array of
 *   { id, prescription_number, patient_name, doctor_name, prescription_date,
 *     is_finalized, items_count, show_url }
 * - refreshUrl: JSON endpoint polled every 10s (same-origin, session auth)
 */
const PrescriptionList = ({ initialPrescriptions, refreshUrl }) => {
    const [prescriptions, setPrescriptions] = useState(initialPrescriptions || []);
    const [filter, setFilter] = useState('all');
    const [error, setError] = useState(null);
    const [updatedAt, setUpdatedAt] = useState(null);

    const refresh = useCallback(() => {
        if (!refreshUrl) return;
        fetch(refreshUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((res) => {
                if (!res.ok) throw new Error(`Prescription refresh failed (${res.status})`);
                return res.json();
            })
            .then((data) => {
                setPrescriptions(Array.isArray(data) ? data : data.prescriptions || []);
                setError(null);
                setUpdatedAt(new Date());
            })
            .catch((err) => setError(err.message));
    }, [refreshUrl]);

    useEffect(() => {
        if (!refreshUrl) return undefined;
        const interval = setInterval(refresh, 10000);
        return () => clearInterval(interval);
    }, [refresh, refreshUrl]);

    const filtered = useMemo(() => {
        if (filter === 'finalized') return prescriptions.filter((p) => p.is_finalized);
        if (filter === 'draft') return prescriptions.filter((p) => !p.is_finalized);
        return prescriptions;
    }, [prescriptions, filter]);

    return (
        <div>
            <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div className="btn-group" role="group" aria-label="Prescription status filter">
                    {[
                        ['all', 'All'],
                        ['draft', 'Draft'],
                        ['finalized', 'Finalized'],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            className={`btn btn-sm ${filter === value ? 'btn-primary' : 'btn-outline-primary'}`}
                            onClick={() => setFilter(value)}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <div className="text-muted small">
                    {filtered.length} of {prescriptions.length} prescriptions
                    {updatedAt && ` · updated ${updatedAt.toLocaleTimeString()}`}
                </div>
            </div>

            {error && (
                <div className="alert alert-warning py-2" role="alert">
                    <i className="bi bi-exclamation-triangle me-1"></i>{error}
                </div>
            )}

            {filtered.length === 0 ? (
                <div className="alert alert-info mb-0">
                    <i className="bi bi-file-medical me-1"></i>No prescriptions found.
                </div>
            ) : (
                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>Rx No</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((p) => (
                                <tr key={p.id}>
                                    <td className="font-monospace small">{p.prescription_number}</td>
                                    <td className="fw-semibold">{p.patient_name}</td>
                                    <td>{p.doctor_name ?? '—'}</td>
                                    <td>{p.prescription_date ?? '—'}</td>
                                    <td><span className="badge bg-info text-dark">{p.items_count ?? 0}</span></td>
                                    <td>
                                        {p.is_finalized
                                            ? <span className="badge bg-success">Finalized</span>
                                            : <span className="badge bg-warning text-dark">Draft</span>}
                                    </td>
                                    <td className="text-end">
                                        {p.show_url && (
                                            <a href={p.show_url} className="btn btn-sm btn-outline-secondary">
                                                <i className="bi bi-eye me-1"></i>View
                                            </a>
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

export default PrescriptionList;
