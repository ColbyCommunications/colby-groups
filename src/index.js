import React, { Component } from 'react';
import Loader from 'colby-loader';
import ReactDOM from 'react-dom';
import style from './style.scss';

const ColbyGroupsConfigSidebar = class ColbyGroupsConfigSidebar extends Component {
    render() {
        return (
            <Loader>
                <div className={style.foo}>hello from javascript!!!</div>
            </Loader>
        );
    }
};

ReactDOM.render(
    <ColbyGroupsConfigSidebar />,
    document.querySelector('.colby-groups-sidebar-mount')
);
