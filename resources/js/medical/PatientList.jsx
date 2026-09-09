import React, { useState, useEffect, useCallback, useMemo } from 'react';

/**
 * Searchable patient list with 10s live refresh.
 *
 * Props:
 * - initialPatients: array of { id, mr_number, name, age, gender, phone, blood_group, is_active }
 * - refreshUrl: JSON endpoint polled every 10s (same-origin, session auth)
 */
const PatientList = ({ initialPatients, refreshUrl }) => {
    const [patients, setPatients] = useState(initialPatients || []);
    const [search, setSearch] = useState('');
    const [error, setError] = useState(null);
    const [updatedAt, setUpdatedAt] = useState(null);

    const refresh = useCallback(() => {
        if (!refreshUrl) return;
        fetch(refreshUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((res) => {
                if (!res.ok) throw new Error(`Patient refresh failed (${res.status})`);
                return res.json();
            })
            .then((data) => {
                setPatients(Array.isArray(data) ? data : data.patients || []);
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
        const q = search.trim().toLowerCase();
        if (!q) return patients;
        return patients.filter((p) =>
            [p.name, p.mr_number, p.phone].some((v) => (v || '').toString().toLowerCase().includes(q)),
        );
    }, [patients, search]);

    return (
        <div>
            <div className="row g-2 align-items-center mb-3">
                <div className="col-md-6">
                    <div className="input-group">
                        <span className="input-group-text"><i className="bi bi-search"></i></span>
                        <input
                            type="search"
                            className="form-control"
                            placeholder="Search by name, MR number or phone…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                </div>
                <div className="col-md-6 text-md-end text-muted small">
                    {filtered.length} of {patients.length} patients
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
                    <i className="bi bi-person me-1"></i>No patients found.
                </div>
            ) : (
                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>MR No</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Phone</th>
                                <th>Blood</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((p) => (
                                <tr key={p.id}>
                                    <td className="font-monospace small">{p.mr_number}</td>
                                    <td className="fw-semibold">{p.name}</td>
                                    <td>{p.age ?? '—'}</td>
                                    <td className="text-capitalize">{p.gender ?? '—'}</td>
                                    <td>{p.phone ?? '—'}</td>
                                    <td>{p.blood_group ?? '—'}</td>
                                    <td>
                                        {p.is_active
                                            ? <span className="badge bg-success">Active</span>
                                            : <span className="badge bg-secondary">Inactive</span>}
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

export default PatientList;
