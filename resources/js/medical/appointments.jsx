import React from 'react';
import ReactDOM from 'react-dom/client';
import AppointmentList from './AppointmentList';

const container = document.getElementById('react-appointments-container');

if (container) {
    let props = {};
    try {
        props = JSON.parse(container.dataset.props || '{}');
    } catch (e) {
        console.error('Invalid appointments props JSON', e);
    }
    const root = ReactDOM.createRoot(container);
    root.render(<AppointmentList {...props} />);
}
