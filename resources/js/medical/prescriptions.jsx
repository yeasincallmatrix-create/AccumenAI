import React from 'react';
import ReactDOM from 'react-dom/client';
import PrescriptionList from './PrescriptionList';

const container = document.getElementById('react-prescriptions-container');

if (container) {
    let props = {};
    try {
        props = JSON.parse(container.dataset.props || '{}');
    } catch (e) {
        console.error('Invalid prescriptions props JSON', e);
    }
    const root = ReactDOM.createRoot(container);
    root.render(<PrescriptionList {...props} />);
}
