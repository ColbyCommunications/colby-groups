/* eslint-disable jsx-a11y/label-has-for */
/* eslint-disable jsx-a11y/label-has-associated-control */
import React, { Component } from 'react';
import Loader from 'colby-loader';
import Autosuggest from 'react-autosuggest';
import _remove from 'lodash/remove';
import style from './style.scss';

const Fetch = wp.apiFetch;
const ColbyBase = window.colbyBase || {};
const groupsEndpoint = `${ColbyBase.siteProtocol}${ColbyBase.host}/wp-json/colby-groups/v1/groups`;
const siteGroupsEndpointPost = '/colby-groups/v1/set-site-groups';
const siteGroupsEndpointGet = '/colby-groups/v1/get-site-groups';

const ColbyGroupsAdminPage = class ColbyGroupsAdminPage extends Component {
    state = {
        groupData: [],
        selectedGroups: [],
        loading: false,
        filter: '',
        suggestions: [],
    };

    componentDidMount = () => {
        this.getGroups();
        this.getSiteGroups();
    };

    getGroups = () => {
        this.setState({
            loading: true,
        });

        Fetch({ url: groupsEndpoint }).then(response => {
            this.setState({
                groupData: response,
            });
        });
    };

    getSiteGroups = () => {
        Fetch({ path: siteGroupsEndpointGet }).then(response => {
            this.setState({
                selectedGroups: response.groups,
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
    };

    removeGroup = group => {
        const { selectedGroups } = this.state;
        _remove(selectedGroups, g => g.ID === group);
        this.setState({
            filter: '',
            selectedGroups,
        });
    };

    handleSubmit = () => {
        const { selectedGroups } = this.state;
        Fetch({
            path: siteGroupsEndpointPost,
            method: 'POST',
            data: { groups: selectedGroups },
        });
    };

    render() {
        const { loading, groupData, filter, suggestions, selectedGroups } = this.state;

        return (
            <div style={{ paddingTop: '40px' }}>
                {loading && <Loader loading type="inline" />}
                {!loading && groupData.length > 0 && (
                    <div>
                        <div style={{ marginBottom: '15px' }}>
                            <label style={{ fontSize: '1.1em' }}>
                                Select groups to restrict this site to:
                            </label>
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
                                renderSuggestion={group => <div>{group.group_name}</div>}
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
                            <div style={{ width: '50%' }}>
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
                                            <tr
                                                key={group.group_name}
                                                style={{
                                                    backgroundColor: '#fff',
                                                }}
                                            >
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
                                <div style={{ marginTop: '10px' }}>
                                    <button
                                        type="button"
                                        disabled={selectedGroups.length === 0}
                                        className={style.saveBtn}
                                        style={{ float: 'right' }}
                                        onClick={this.handleSubmit}
                                    >
                                        Save
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        );
    }
};

export default ColbyGroupsAdminPage;
