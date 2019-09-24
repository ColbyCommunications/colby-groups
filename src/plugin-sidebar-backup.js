( function( wp ) {
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var el = wp.element.createElement;
	var Checkbox = wp.components.CheckboxControl
	var withSelect = wp.data.withSelect;
	var withDispatch = wp.data.withDispatch;
    
    var mapSelectToProps = (select) => {
		return {
			metaFieldValue: select( 'core/editor' )
				.getEditedPostAttribute( 'meta' )
				[ 'sidebar_plugin_meta_block_field' ]
		}
    }
    
    var mapDispatchToProps = (dispatch) => {
		return {
			setMetaFieldValue: (value) => {
				dispatch( 'core/editor' ).editPost(
					{ meta: { sidebar_plugin_meta_block_field: value } }
				);
			}
		}
	}

    var MetaBlockField = (props) => {
		return el('div', { className: 'test' }, el(Checkbox,
			{
				label: 'Restrict to Colby Groups',
			checked: props.metaFieldValue,
			onChange:function( content ) {
				props.setMetaFieldValue( content );
            }},
					
		));
    }
    
    var MetaBlockFieldWithData = withSelect( mapSelectToProps )( MetaBlockField );
    var MetaBlockFieldWithDataAndActions = withDispatch( mapDispatchToProps )( MetaBlockFieldWithData );

	registerPlugin('my-plugin-sidebar', {
		render: () => {
			return el( PluginSidebar,
				{
					name: 'my-plugin-sidebar',
					icon: 'groups',
					title: 'Colby Groups',
				},
				el( 'div',
					{ className: 'colby-groups-meta' },
					el( MetaBlockFieldWithDataAndActions )
				)
			);
		}
	});
} )( window.wp );

// const { Fragment } = wp.element;
// const { registerPlugin } = wp.plugins;
// const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
// const { PanelBody, PanelRow } = wp.components;
 
// const PluginSidebarDemo = () => (
//   <Fragment>
//     <PluginSidebarMoreMenuItem target="namespace-plugin-name">
//       {__("Plugin Sidebar Demo", "namespace-plugin-name")}
//     </PluginSidebarMoreMenuItem>
//     <PluginSidebar
//       name="namespace-plugin-name"
//       title={__("Plugin Sidebar Demo", "namespace-plugin-name")}
//     >
//       <PanelBody>
//         <PanelRow>
//           {__("Plugin Sidebar Content", "namespace-plugin-name")}
//         </PanelRow>
//       </PanelBody>
//     </PluginSidebar>
//   </Fragment>
// );
 
// registerPlugin("namespace-plugin-name", {
//   icon: "admin-plugins", // The Plugin Dashicon
//   render: PluginSidebarDemo
// });