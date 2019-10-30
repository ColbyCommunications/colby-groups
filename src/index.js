import React from 'react';
import ReactDOM from 'react-dom';
import AdminPage from './AdminPage.js';
// import PostSidebar from './PostSidebar.js';
import PostSidebar2 from './PostSidebar2.js';

// const { registerPlugin } = wp.plugins;
const { registerBlockType } = wp.blocks;

registerBlockType('colby_groups', {
    title: 'Meta Block',
    icon: 'smiley',
    category: 'common',

    attributes: {
        restrictToGroups: {
            type: 'string',
            source: 'meta',
            meta: 'colby_groups_meta_restrict_to_groups',
        },
        selectedGroups: {
            type: 'string',
            source: 'meta',
            meta: 'colby_groups_meta_selected_groups',
        },
    },

    edit() {
        return <PostSidebar2 />;
    },

    // No information saved to the block
    // Data is saved to post meta via attributes
    save() {
        return null;
    },
});

if (document.querySelector('#colby-groups-admin-page')) {
    ReactDOM.render(<AdminPage />, document.querySelector('#colby-groups-admin-page'));
}
