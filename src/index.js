import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Loader from 'colby-loader';
import Autosuggest from 'react-autosuggest';
import _remove from 'lodash/remove';
import style from './style.scss';

const { Fragment } = wp.element;
const { registerPlugin } = wp.plugins;
const { withSelect, withDispatch } = wp.data;
const { PluginSidebar } = wp.editPost;
const el = wp.element.createElement;
const { CheckboxControl, PanelBody, PanelRow, Panel, TextControl } = wp.components;

const ColbyBase = window.colbyBase || {};
const groupsEndpoint = `${ColbyBase.siteProtocol}${ColbyBase.host}/wp-json/colby-groups/v1/groups`;
const groupsForPostEndpoint = `${ColbyBase.siteProtocol}${ColbyBase.host}/wp-json/colby-groups/v1/groupsForPost/%blogId%/%postId%`;

const mapSelectToProps = select => {
    return {
        restrictToGroups: select('core/editor').getEditedPostAttribute('meta')
            .sidebar_plugin_meta_block_field,
    };
};

const mapDispatchToProps = dispatch => {
    return {
        setMetaFieldValue: value => {
            dispatch('core/editor').editPost({
                meta: { sidebar_plugin_meta_block_field: value },
            });
        },
    };
};

const PluginComponent = class ColbyGroupsConfigSidebar extends Component {
    state = {
        groupData: [],
        selectedGroups: [],
        loading: false,
        filter: '',
        suggestions: [],
    };

    handleCheckboxChange = content => {
        const { setMetaFieldValue } = this.props;
        setMetaFieldValue(content);
        this.setState({
            loading: true,
        });

        const $this = this;
        fetch(groupsEndpoint)
            .then(response => response.json())
            .then(json => {
                $this.setState({
                    groupData: json,
                    loading: false,
                });
            });
    };

    getSuggestions = value => {
        const { groupData } = this.state;
        const inputValue = value.trim().toLowerCase();
        const inputLength = inputValue.length;

        return inputLength === 0
            ? []
            : groupData.filter(
                  group => group.group_name.toLowerCase().slice(0, inputLength) === inputValue
              );
    };

    onSuggestionSelect = (event, { suggestionValue, suggestionIndex }) => {
        const { selectedGroups, groupData } = this.state;
        const groupObj = groupData.find(g => g.group_name === suggestionValue);
        selectedGroups.push(groupObj);
        this.setState({
            selectedGroups,
            filter: '',
        });
    };

    removeGroup = group => {
        const { selectedGroups } = this.state;
        _remove(selectedGroups, g => g.ID === group);
        this.setState({
            selectedGroups,
        });
    };

    render() {
        const { loading, groupData, filter, suggestions, selectedGroups } = this.state;
        const { restrictToGroups } = this.props;
        return (
            <div className="test">
                <Fragment>
                    <PluginSidebar name="my-plugin-sidebar" icon="groups" title="Colby Groups">
                        <PanelBody title="Colby Groups" icon="groups" initialOpen>
                            <PanelRow>
                                <CheckboxControl
                                    label="Restrict to Colby Groups"
                                    checked={restrictToGroups}
                                    onChange={this.handleCheckboxChange}
                                />
                            </PanelRow>
                            <PanelRow>
                                {restrictToGroups && loading && <Loader loading type="inline" />}
                                {restrictToGroups && !loading && groupData.length > 0 && (
                                    <div>
                                        <div style={{ marginBottom: '15px' }}>
                                            <Autosuggest
                                                suggestions={suggestions}
                                                onSuggestionsFetchRequested={({ value }) => {
                                                    this.setState({
                                                        suggestions: this.getSuggestions(value),
                                                    });
                                                }}
                                                onSuggestionsClearRequested={() => {
                                                    this.setState({
                                                        suggestions: [],
                                                    });
                                                }}
                                                getSuggestionValue={group => group.group_name}
                                                renderSuggestion={group => (
                                                    <div>{group.group_name}</div>
                                                )}
                                                inputProps={{
                                                    placeholder: 'Group name',
                                                    value: filter,
                                                    onChange: (event, { newValue }) => {
                                                        this.setState({
                                                            filter: newValue,
                                                        });
                                                    },
                                                }}
                                                onSuggestionSelected={this.onSuggestionSelect}
                                            />
                                        </div>
                                        {selectedGroups.length > 0 && (
                                            <div>
                                                <table className={style.colbyGroupsPostTable}>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>&nbsp;</th>
                                                    </tr>
                                                    {selectedGroups.map(group => (
                                                        <tr key={group.group_name}>
                                                            <td>{group.group_name}</td>
                                                            <td>
                                                                <button
                                                                    style={{
                                                                        color: 'red',
                                                                        cursor: 'pointer',
                                                                    }}
                                                                    type="button"
                                                                    onClick={this.removeGroup.bind(
                                                                        this,
                                                                        group.ID
                                                                    )}
                                                                >
                                                                    Delete
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </table>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </PanelRow>
                        </PanelBody>
                    </PluginSidebar>
                </Fragment>
            </div>
        );
    }
};

PluginComponent.propTypes = {
    restrictToGroups: PropTypes.bool,
    setMetaFieldValue: PropTypes.func.isRequired,
};

PluginComponent.defaultProps = {
    restrictToGroups: false,
};

const MetaBlockField = withDispatch(mapDispatchToProps)(
    withSelect(mapSelectToProps)(PluginComponent)
);

registerPlugin('my-plugin-sidebar', {
    render: () => (
        <div className="colby-groups-meta" style={{}}>
            <MetaBlockField />
        </div>
    ),
});
