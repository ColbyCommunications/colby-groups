import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Loader from 'colby-loader';
import Autosuggest from 'react-autosuggest';
import _remove from 'lodash/remove';
import style from './style.scss';

const { Fragment } = wp.element;
const Fetch = wp.apiFetch;
const { withSelect, withDispatch } = wp.data;
const { PluginSidebar } = wp.editPost;
const { CheckboxControl, PanelBody, PanelRow } = wp.components;
const ColbyBase = window.colbyBase || {};
const groupsEndpoint = `${ColbyBase.siteProtocol}${ColbyBase.host}/wp-json/colby-groups/v1/groups`;

const mapSelectToProps = select => {
    return {
        restrictToGroups: select('core/editor').getEditedPostAttribute('meta')
            .colby_groups_meta_restrcit_to_groups,
        selectedGroups: select('core/editor').getEditedPostAttribute('meta')
            .colby_groups_meta_selected_groups,
    };
};

const mapDispatchToProps = dispatch => {
    return {
        setRestrictToGroups: value => {
            dispatch('core/editor').editPost({
                meta: { colby_groups_meta_restrcit_to_groups: value },
            });
        },
        setSelectedGroups: value => {
            dispatch('core/editor').editPost({
                meta: { colby_groups_meta_selected_groups: value },
            });
        },
    };
};

const ColbyGroupsConfigSidebar = class ColbyGroupsConfigSidebar extends Component {
    state = {
        groupData: [],
        selectedGroups: this.props.selectedGroups // eslint-disable-line react/destructuring-assignment
            ? JSON.parse(this.props.selectedGroups) // eslint-disable-line react/destructuring-assignment
            : [],
        loading: false,
        filter: '',
        suggestions: [],
    };

    componentDidMount = () => {
        const { restrictToGroups } = this.props;
        if (restrictToGroups) {
            this.getGroups();
        }
    };

    handleCheckboxChange = content => {
        const { setRestrictToGroups } = this.props;
        setRestrictToGroups(content);
        this.getGroups();
    };

    getGroups = () => {
        this.setState({
            loading: true,
        });

        Fetch({ url: groupsEndpoint }).then(response => {
            this.setState({
                groupData: response,
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
            : groupData.filter(group => group.group_name.toLowerCase().includes(inputValue));
    };

    onSuggestionSelect = (event, { suggestionValue }) => {
        const { setSelectedGroups } = this.props;
        const { groupData, selectedGroups } = this.state;
        const groupObj = groupData.find(g => g.group_name === suggestionValue);
        let newGroups = [];
        if (selectedGroups) {
            newGroups = [...selectedGroups, groupObj];
        } else {
            newGroups.push(groupObj);
        }
        this.setState({
            filter: '',
            selectedGroups: newGroups,
        });
        setSelectedGroups(JSON.stringify(newGroups));
    };

    removeGroup = group => {
        const { selectedGroups } = this.state;
        const { setSelectedGroups } = this.props;
        _remove(selectedGroups, g => g.ID === group);
        this.setState({
            filter: '',
            selectedGroups,
        });
        setSelectedGroups(JSON.stringify(selectedGroups));
    };

    render() {
        // console.log(this.state);
        const { loading, groupData, filter, suggestions, selectedGroups } = this.state;
        const { restrictToGroups } = this.props;

        return (
            <div>
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
                                                    <thead>
                                                        <tr
                                                            style={{
                                                                backgroundColor: '#426a92',
                                                                color: '#fff',
                                                            }}
                                                        >
                                                            <th>Name</th>
                                                            <th>&nbsp;</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {selectedGroups.map(group => (
                                                            <tr key={group.group_name}>
                                                                <td>{group.group_name}</td>
                                                                <td>
                                                                    <button
                                                                        className={style.deleteBtn}
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
                                                    </tbody>
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

ColbyGroupsConfigSidebar.propTypes = {
    restrictToGroups: PropTypes.bool,
    selectedGroups: PropTypes.string,
    setRestrictToGroups: PropTypes.func.isRequired,
    setSelectedGroups: PropTypes.func.isRequired,
};

ColbyGroupsConfigSidebar.defaultProps = {
    restrictToGroups: false,
    selectedGroups: [],
};

const MetaBlockField = withDispatch(mapDispatchToProps)(
    withSelect(mapSelectToProps)(ColbyGroupsConfigSidebar)
);

export default MetaBlockField;
