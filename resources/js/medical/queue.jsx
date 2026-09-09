import React from 'react';
import ReactDOM from 'react-dom/client';
import QueueList from './QueueList';

const container = document.getElementById('react-queue-container');

if (container) {
    let props = {};
    try {
        props = JSON.parse(container.dataset.props || '{}');
    } catch (e) {
        console.error('Invalid queue props JSON', e);
    }
    const root = ReactDOM.createRoot(container);
    root.render(<QueueList {...props} />);
}
