import React from 'react';
import ReactDOM from 'react-dom/client';
import PatientList from './PatientList';

const container = document.getElementById('react-patients-container');

if (container) {
    let props = {};
    try {
        props = JSON.parse(container.dataset.props || '{}');
    } catch (e) {
        console.error('Invalid patients props JSON', e);
    }
    const root = ReactDOM.createRoot(container);
    root.render(<PatientList {...props} />);
}
