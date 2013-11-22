/*global jQuery, WidgetCustomizerPreview_exports, _ */
/*exported WidgetCustomizerPreview */
var WidgetCustomizerPreview = (function ($) {
	'use strict';

	var self = {
		rendered_sidebars: [],
		rendered_widgets: [],
		registered_sidebars: {},
		widget_selectors: [],
		render_widget_ajax_action: null,
		render_widget_nonce_value: null,
		render_widget_nonce_post_key: null,
		i18n: {},

		init: function () {
			this.buildWidgetSelectors();
			this.toggleSections();
			this.highlightControls();
			this.livePreview();
		},

		/**
		 * Calculate the selector for the sidebar's widgets based on the registered sidebar's info
		 */
		buildWidgetSelectors: function () {
			$.each( self.registered_sidebars, function ( id, sidebar ) {
				var widget_tpl = [
					sidebar.before_widget.replace('%1$s', '').replace('%2$s', ''),
					sidebar.before_title,
					sidebar.after_title,
					sidebar.after_widget
				].join('');
				var empty_widget = $(widget_tpl);
				var widget_selector = empty_widget.prop('tagName');
				var widget_classes = empty_widget.prop('className').replace(/^\s+|\s+$/g, '');
				if ( widget_classes ) {
					widget_selector += '.' + widget_classes.split(/\s+/).join('.');
				}
				self.widget_selectors.push(widget_selector);
			});
		},

		/**
		 * @todo This will fail if a sidebar does not have at least one widget. Can be fixed with http://core.trac.wordpress.org/ticket/25368
		 * @todo Use a method off of parent.WidgetCustomizer
		 * @todo Use postMessage instead of accessing parent window?
		 */
		toggleSections: function () {
			parent.jQuery('.control-section[id^="accordion-section-sidebar-widgets-"]').hide();
			$.each( self.rendered_sidebars, function ( i, sidebar_id ) {
				parent.jQuery('#accordion-section-sidebar-widgets-' + sidebar_id).show();
			});
		},

		/**
		 *
		 */
		highlightControls: function() {

			var selector = this.widget_selectors.join(',');

			$(selector).attr( 'title', self.i18n.widget_tooltip );

			$(document).on( 'mouseenter', selector, function () {
				var control = parent.WidgetCustomizer.getWidgetFormControlForWidget( $(this).prop('id') );
				if ( control ) {
					control.highlightSectionAndControl();
				}
			});

			// @todo click can interfere with interacting with the widget in the preview window; better to make a EDIT link overlay appear when hovering over the widget?
			$(document).on( 'click', selector, function () {
				var control = parent.WidgetCustomizer.getWidgetFormControlForWidget( $(this).prop('id') );
				if ( control ) {
					control.expandControlSection();
					control.expandForm();
					control.container.find(':input:visible:first').focus();
				}
			});
		},

		/**
		 *
		 */
		livePreview: function () {
			var already_bound_widgets = {};

			var bind_widget_setting = function( widget_id ) {
				var setting_id = widget_id_to_setting_id( widget_id );
				var binder = function( value ) {
					already_bound_widgets[widget_id] = true;
					var initial_value = value();
					var update_count = 0;
					value.bind( function( to ) {
						// Workaround for http://core.trac.wordpress.org/ticket/26061;
						// once fixed, eliminate initial_value, update_count, and this conditional
						update_count += 1;
						if ( 1 === update_count && _.isEqual( initial_value, to ) ) {
							return;
						}

						var id_base;
						var widget_number = null;
						var matches = widget_id.match( /^(.+)-(\d+)$/ );
						if ( matches ) {
							id_base = matches[1];
							widget_number = parseInt( matches[2], 10 );
						}
						else {
							// could be an old single widget, or adding a new widget
							id_base = widget_id;
						}

						var sidebar_id = null;
						var sidebar_widgets = [];
						wp.customize.each( function ( setting, setting_id ) {
							var matches = setting_id.match( /^sidebars_widgets\[(.+)\]/ );
							if ( matches && setting().indexOf( widget_id ) !== -1 ) {
								sidebar_id = matches[1];
								sidebar_widgets = setting();
							}
						} );
						if ( ! sidebar_id ) {
							throw new Error( 'Widget does not exist in a sidebar.' );
						}

						var data = {
							widget_customizer_render_widget: 1,
							action: self.render_widget_ajax_action,
							widget_id: widget_id,
							setting_id: setting_id,
							instance: JSON.stringify( to )
						};
						var customized = {};
						customized['sidebars_widgets[' + sidebar_id + ']'] = sidebar_widgets;
						customized[setting_id] = to;
						data.customized = JSON.stringify(customized);
						data[self.render_widget_nonce_post_key] = self.render_widget_nonce_value;

						$.post( self.request_uri, data, function ( r ) {
							if ( ! r.success ) {
								throw new Error( r.data && r.data.message ? r.data.message : 'FAIL' );
							}

							// @todo We should tell the preview that synced has happened after the Ajax finishes
							// @todo Fire jQuery event to indicate that a widget was updated; here widgets can re-initialize them if they support live widgets
							var old_widget = $( '#' + widget_id );
							var new_widget = $( r.data.rendered_widget );
							if ( new_widget.length && old_widget.length ) {
								old_widget.replaceWith( new_widget );
							}
							else if ( ! new_widget.length && old_widget.length ) {
								old_widget.remove();
							}
							else if ( new_widget.length && ! old_widget.length ) {
								var sidebar_widgets = wp.customize('sidebars_widgets[' + r.data.sidebar_id + ']')();
								var position = sidebar_widgets.indexOf( widget_id );
								if ( -1 === position ) {
									throw new Error( 'Unable to determine new widget position in sidebar' );
								}
								if ( sidebar_widgets.length === 1 ) {
									throw new Error( 'Unexpected postMessage for adding first widget to sidebar; refresh must be used instead.' );
								}
								if ( position > 0 ) {
									var before_widget = $( '#' + sidebar_widgets[ position - 1 ] );
									before_widget.after( new_widget );
								}
								else {
									var after_widget = $( '#' + sidebar_widgets[ position + 1 ] );
									after_widget.before( new_widget );
								}
							}
						} );
					} );
				};
				wp.customize( setting_id, binder );
				already_bound_widgets[setting_id] = binder;
			};

			$.each( self.rendered_sidebars, function ( i, sidebar_id ) {
				var setting_id = 'sidebars_widgets[' + sidebar_id + ']';
				wp.customize( setting_id, function( value ) {
					var initial_value = value();
					var update_count = 0;
					value.bind( function( to, from ) {
						// Workaround for http://core.trac.wordpress.org/ticket/26061;
						// once fixed, eliminate initial_value, update_count, and this conditional
						update_count += 1;
						if ( 1 === update_count && _.isEqual( initial_value, to ) ) {
							return;
						}

						// Sort widgets
						$.each( to, function ( i, widget_id ) {
							var widget = $( '#' + widget_id );
							widget.parent().append( widget );
						} );

						// Create settings for newly-created widgets
						$.each( to, function ( i, widget_id ) {
							var setting_id = widget_id_to_setting_id( widget_id );
							if ( ! wp.customize( setting_id ) ) {
								wp.customize.create( setting_id, {} );
							}
							// @todo Is there another way to check if we bound?
							if ( already_bound_widgets[widget_id] ) {
								return;
							}
							bind_widget_setting( widget_id );
						} );

						// Remove widgets (their DOM element and their setting) when removed from sidebar
						$.each( from, function ( i, old_widget_id ) {
							if ( -1 === to.indexOf( old_widget_id ) ) {
								var setting_id = widget_id_to_setting_id( old_widget_id );
								if ( wp.customize.has( setting_id ) ) {
									wp.customize.remove( setting_id );
									// @todo WARNING: If a widget is moved to another sidebar, we need to either not do this, or force a refresh when a widget is  moved to another sidebar
								}
								$( '#' + old_widget_id ).remove();
							}
						} );
					} );
				} );
			} );

			$.each( self.rendered_widgets, function ( widget_id ) {
				if ( ! wp.customize.has( widget_id_to_setting_id( widget_id ) ) ) {
					// Used to have to do this: wp.customize.create( setting_id, instance );
					// Now that the settings are registered at the `wp` action, it is late enough
					// for all filters to be added, e.g. sidebars_widgets for Widget Visibility
					throw new Error( 'Expected customize to have registerd setting for widget ' + widget_id );
				}
				bind_widget_setting( widget_id );
			} );
		}
	};

	$.extend(self, WidgetCustomizerPreview_exports);

	function widget_id_to_setting_id( widget_id ) {
		var setting_id = null;
		var matches = widget_id.match(/^(.+?)(?:-(\d+)?)$/);
		if ( matches ) {
			setting_id = 'widget_' + matches[1] + '[' + matches[2] + ']';
		}
		else {
			setting_id = 'widget_' + widget_id;
		}
		return setting_id;
	}

	$(function () {
		self.init();
	});

	return self;
}( jQuery ));
