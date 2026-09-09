import React, { useState, useEffect, useCallback } from 'react';

/**
 * Live OPD queue for one doctor + date.
 *
 * Props:
 * - doctorId: users.id of the doctor (QueueManager scope)
 * - date: Y-m-d queue date
 * - initialQueue: array of { id, serial, patient_name, status, estimated_time }
 * - refreshUrl: JSON endpoint polled every 10s (same-origin, session auth)
 */
const statusBadge = (status) => {
    switch (status) {
        case 'in_progress':
            return 'bg-success';
        case 'checked_in':
            return 'bg-warning text-dark';
        case 'scheduled':
            return 'bg-info text-dark';
        default:
            return 'bg-secondary';
    }
};

const QueueList = ({ doctorId, date, initialQueue, refreshUrl }) => {
    const [queue, setQueue] = useState(initialQueue || []);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [updatedAt, setUpdatedAt] = useState(null);

    const refresh = useCallback(() => {
        if (!refreshUrl) return;
        setLoading(true);
        fetch(refreshUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((res) => {
                if (!res.ok) throw new Error(`Queue refresh failed (${res.status})`);
                return res.json();
            })
            .then((data) => {
                setQueue(Array.isArray(data) ? data : data.queue || []);
                setError(null);
                setUpdatedAt(new Date());
            })
            .catch((err) => setError(err.message))
            .finally(() => setLoading(false));
    }, [refreshUrl]);

    // Refresh queue every 10 seconds.
    useEffect(() => {
        if (!refreshUrl) return undefined;
        const interval = setInterval(refresh, 10000);
        return () => clearInterval(interval);
    }, [refresh, refreshUrl]);

    return (
        <div>
            <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div className="text-muted small">
                    {updatedAt
                        ? `Last updated ${updatedAt.toLocaleTimeString()}`
                        : 'Live queue — auto-refreshes every 10 seconds'}
                    {loading && <span className="ms-2 spinner-border spinner-border-sm" role="status" aria-hidden="true" />}
                </div>
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={refresh} disabled={loading}>
                    <i className="bi bi-arrow-clockwise me-1"></i>Refresh now
                </button>
            </div>

            {error && (
                <div className="alert alert-warning py-2" role="alert">
                    <i className="bi bi-exclamation-triangle me-1"></i>{error}
                </div>
            )}

            {queue.length === 0 ? (
                <div className="alert alert-info mb-0">
                    <i className="bi bi-people me-1"></i>No patients in the live queue for this doctor and date.
                </div>
            ) : (
                <div className="d-flex flex-column gap-2">
                    {queue.map((item, index) => (
                        <div key={item.id} className="card shadow-sm">
                            <div className="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                                <div className="d-flex align-items-center gap-3">
                                    <span className="badge bg-primary rounded-pill fs-6">#{item.serial ?? index + 1}</span>
                                    <div>
                                        <div className="fw-semibold">{item.patient_name}</div>
                                        {item.estimated_time && (
                                            <div className="text-muted small">
                                                <i className="bi bi-clock me-1"></i>ETA {item.estimated_time}
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <span className={`badge ${statusBadge(item.status)}`}>
                                    {(item.status || '').replace(/_/g, ' ')}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

export default QueueList;
