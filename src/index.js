import React from 'react';
import ReactDOM from 'react-dom';
import { registerPlugin } from '@wordpress/plugins';
import AdminPage from './AdminPage.js';
import PostSidebar from './PostSidebar.js';

registerPlugin('colby-groups', {
    icon: 'smiley',
    // eslint-disable-next-line react/display-name
    render: () => (
        <div className="colby-groups-meta">
            <PostSidebar />
        </div>
    ),
});

ReactDOM.render(<AdminPage />, document.querySelector('#colby-groups-admin-page'));
