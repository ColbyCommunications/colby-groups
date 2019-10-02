// import React, { Component } from 'react';
// import PropTypes from 'prop-types';
// import Loader from 'colby-loader';

// const { Fragment } = wp.element;
// const { registerPlugin } = wp.plugins;
// const { withSelect, withDispatch } = wp.data;
// const { PluginSidebar } = wp.editPost;
// const el = wp.element.createElement;
// const { CheckboxControl, PanelBody, PanelRow, Panel } = wp.components;

// const ColbyGroups = window.colbyGroups || {};
// const groupsEndpoint = `${ColbyGroups.siteUrl}/wp-json/colby-groups/v1/groups`;
// const groupsForPostEndpoint = `${ColbyGroups.siteUrl}/wp-json/colby-groups/v1/groupsForPost/%blogId%/%postId%`;

// const mapSelectToProps = select => {
//     return {
//         restrictToGroups: select('core/editor').getEditedPostAttribute('meta')
//             .sidebar_plugin_meta_block_field,
//     };
// };

// const mapDispatchToProps = dispatch => {
//     return {
//         setMetaFieldValue: value => {
//             dispatch('core/editor').editPost({
//                 meta: { sidebar_plugin_meta_block_field: value },
//             });
//         },
//     };
// };

// const MetaBlockField = withDispatch( mapDispatchToProps )(withSelect( mapSelectToProps )((props) => {
// 	return (
// 		<div className="test">
// 		<Fragment>
// 				<PluginSidebar name="my-plugin-sidebar" icon="groups" title="Colby Groups">
// 					<PanelBody title="Colby Groups"
//             icon="groups"
//             initialOpen={ true }>
// 						<PanelRow>
// 			<CheckboxControl label="Restrict to Colby Groups" checked={props.metaFieldValue} onChange={(content) => {
// 				props.setMetaFieldValue(content);
// 						}} />

// 			</PanelRow>
// 			<PanelRow>
// 			{props.metaFieldValue && (<div>foo</div>)}
// 			</PanelRow>
// 					</PanelBody>
// 				</PluginSidebar>
// 			</Fragment>
// 		</div>
// 	);
// }));

// const PluginComponent = class ColbyGroupsConfigSidebar extends Component {
//     state = {
//         groupData: [],
//         selectedGroups: [],
//         loading: false,
//     };

//     handleCheckboxChange = content => {
//         this.props.setMetaFieldValue(content);
//         this.setState({
//             loading: true,
//         });
//         console.log(groupsEndpoint);
//         const $this = this;
//         fetch(groupsEndpoint)
//             .then(response => response.json())
//             .then(json => {
//                 $this.setState({
//                     groupData: json,
//                     loading: false,
//                 });
//             });
//     };

//     render() {
//         console.log(this.state);
//         return (
//             <div className="test">
//                 <Fragment>
//                     <PluginSidebar
//                         name="my-plugin-sidebar"
//                         icon="groups"
//                         title="Colby Groups"
//                     >
//                         <PanelBody
//                             title="Colby Groups"
//                             icon="groups"
//                             initialOpen
//                         >
//                             <PanelRow>
//                                 <CheckboxControl
//                                     label="Restrict to Colby Groups"
//                                     checked={this.props.restrictToGroups}
//                                     onChange={this.handleCheckboxChange}
//                                 />
//                             </PanelRow>
//                             <PanelRow>
//                                 {this.props.restrictToGroups && (
//                                     <Loader loading={this.state.loading} />
//                                 )}
//                             </PanelRow>
//                         </PanelBody>
//                     </PluginSidebar>
//                 </Fragment>
//             </div>
//         );
//     }
// };

// PluginComponent.propTypes = {
//     restrictToGroups: PropTypes.bool,
//     setMetaFieldValue: PropTypes.func,
// };

// const MetaBlockField = withDispatch(mapDispatchToProps)(
//     withSelect(mapSelectToProps)(PluginComponent)
// );

// registerPlugin('my-plugin-sidebar', {
//     render: () => (
//         <div className="colby-groups-meta">
//             <MetaBlockField />
//         </div>
//     ),
// });
