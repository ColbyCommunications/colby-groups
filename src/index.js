import React from 'react';
import ReactDOM from 'react-dom';
import AdminPage from './AdminPage.js';
import PostSidebar from './PostSidebar.js';

const { registerPlugin } = wp.plugins;

registerPlugin('colby-groups', {
    icon: 'smiley',
    // eslint-disable-next-line react/display-name
    render: () => (
        <div className="colby-groups-meta">
            <PostSidebar />
        </div>
    ),
});

if (document.querySelector('#colby-groups-admin-page')) {
    ReactDOM.render(<AdminPage />, document.querySelector('#colby-groups-admin-page'));
}
