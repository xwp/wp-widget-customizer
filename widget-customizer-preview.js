/*global jQuery, WidgetCustomizerPreview_exports, _ */
/*exported WidgetCustomizerPreview */
var WidgetCustomizerPreview = (function ($) {
	'use strict';

	var self = {
		rendered_sidebars: {}, // @todo Make rendered a property of the Backbone model
		sidebars_eligible_for_post_message: {},
		rendered_widgets: {}, // @todo Make rendered a property of the Backbone model
		widgets_eligible_for_post_message: {},
		registered_sidebars: [], // @todo Make a Backbone collection
		registered_widgets: {}, // @todo Make array, Backbone collection
		widget_selectors: [],
		render_widget_ajax_action: null,
		render_widget_nonce_value: null,
		render_widget_nonce_post_key: null,
		preview: null,
		i18n: {},

		init: function () {
			this.buildWidgetSelectors();
			this.highlightControls();
			this.livePreview();

			self.preview.bind( 'active', function() {
				self.preview.send( 'rendered-sidebars', self.rendered_sidebars ); // @todo Only send array of IDs
				self.preview.send( 'rendered-widgets', self.rendered_widgets ); // @todo Only send array of IDs
			} );
		},

		/**
		 * Calculate the selector for the sidebar's widgets based on the registered sidebar's info
		 */
		buildWidgetSelectors: function () {
			$.each( self.registered_sidebars, function ( i, sidebar ) {
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
		 * Obtain a widget instance if it was added to the provided sidebar
		 * This addresses a race condition where a widget is moved between sidebars
		 * We cannot use ID selector because jQuery will only return the first one
		 * that matches. We have to resort to an [id] attribute selector
		 *
		 * @param {String} sidebar_id
		 * @param {String} widget_id
		 * @return {jQuery}
		 */
		getSidebarWidgetElement: function ( sidebar_id, widget_id ) {
			return $( '[id=' + widget_id + ']' ).filter( function () {
				return $( this ).data( 'widget_customizer_sidebar_id' ) === sidebar_id;
			} );
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

			// Open expand the widget control when shift+clicking the widget element
			$(document).on( 'click', selector, function ( e ) {
				if ( ! e.shiftKey ) {
					return;
				}
				e.preventDefault();
				var control = parent.WidgetCustomizer.getWidgetFormControlForWidget( $(this).prop('id') );
				if ( control ) {
					control.focus();
				}
			});
		},

		/**
		 * if the containing sidebar is eligible, and if there are sibling widgets the sidebar currently rendered
		 * @param {String} sidebar_id
		 * @return {Boolean}
		 */
		sidebarCanLivePreview: function ( sidebar_id ) {
			if ( ! self.current_theme_supports ) {
				return false;
			}
			if ( ! self.sidebars_eligible_for_post_message[sidebar_id] ) {
				return false;
			}
			var widget_ids = wp.customize( sidebar_id_to_setting_id( sidebar_id ) )();
			var rendered_widget_ids = _( widget_ids ).filter( function ( widget_id ) {
				return 0 !== self.getSidebarWidgetElement( sidebar_id, widget_id ).length;
			} );
			if ( rendered_widget_ids.length === 0 ) {
				return false;
			}
			return true;
		},


		/**
		 * We can only know if a sidebar can be live-previewed by letting the
		 * preview tell us, so this updates the parent's transports to
		 * postMessage when it is available. If there is a switch from
		 * postMessage to refresh, the preview window will request a refresh.
		 * @param {String} sidebar_id
		 */
		refreshTransports: function () {
			var changed_to_refresh = false;
			$.each( self.rendered_sidebars, function ( sidebar_id ) {
				var setting_id = sidebar_id_to_setting_id( sidebar_id );
				var setting = parent.wp.customize( setting_id );
				var sidebar_transport = self.sidebarCanLivePreview( sidebar_id ) ? 'postMessage' : 'refresh';
				if ( 'refresh' === sidebar_transport && 'postMessage' === setting.transport ) {
					changed_to_refresh = true;
				}
				setting.transport = sidebar_transport;

				var widget_ids = wp.customize( setting_id )();
				$.each( widget_ids, function ( i, widget_id ){
					var setting_id = widget_id_to_setting_id( widget_id );
					var setting = parent.wp.customize( setting_id );
					var widget_transport = 'refresh';
					var id_base = widget_id_to_base( widget_id );
					if ( self.current_theme_supports && sidebar_transport === 'postMessage' && self.widgets_eligible_for_post_message[id_base] ) {
						widget_transport = 'postMessage';
					}
					if ( 'refresh' === widget_transport && 'postMessage' === setting.transport ) {
						changed_to_refresh = true;
					}
					setting.transport = widget_transport;
				} );
			} );
			if ( changed_to_refresh ) {
				self.preview.send( 'refresh' );
			}
		},

		/**
		 * Set up the ability for the widget to be previewed without doing a preview refresh
		 */
		livePreview: function () {
			var already_bound_widgets = {};

			var bind_widget_setting = function( widget_id ) {
				var setting_id = widget_id_to_setting_id( widget_id );
				var binder = function( value ) {
					already_bound_widgets[widget_id] = true;
					value.bind( function( to, from ) {
						// Workaround for http://core.trac.wordpress.org/ticket/26061;
						// once fixed, this conditional can be eliminated
						if ( _.isEqual( from, to ) ) {
							return;
						}

						var widget_setting_id = widget_id_to_setting_id( widget_id );
						if ( parent.wp.customize( widget_setting_id ).transport !== 'postMessage' ) {
							return;
						}

						var customized = {};
						var sidebar_id = null;
						wp.customize.each( function ( setting, setting_id ) {
							var matches = setting_id.match( /^sidebars_widgets\[(.+)\]/ );
							if ( ! matches ) {
								return;
							}
							var other_sidebar_id = matches[1];
							if ( setting().indexOf( widget_id ) !== -1 ) {
								sidebar_id = other_sidebar_id;
							}
							customized[sidebar_id_to_setting_id( other_sidebar_id )] = setting();
						} );
						if ( ! sidebar_id ) {
							throw new Error( 'Widget does not exist in a sidebar.' );
						}

						var data = {
							widget_customizer_render_widget: 1,
							action: self.render_widget_ajax_action,
							widget_id: widget_id,
							setting_id: setting_id,
							setting: JSON.stringify( to )
						};

						customized[setting_id] = to;
						data.customized = JSON.stringify(customized);
						data[self.render_widget_nonce_post_key] = self.render_widget_nonce_value;

						$.post( self.request_uri, data, function ( r ) {
							if ( ! r.success ) {
								throw new Error( r.data && r.data.message ? r.data.message : 'FAIL' );
							}

							var old_widget = self.getSidebarWidgetElement( sidebar_id, widget_id );
							var new_widget = $( r.data.rendered_widget );
							new_widget.data( 'widget_customizer_sidebar_id', sidebar_id );
							if ( new_widget.length && old_widget.length ) {
								old_widget.replaceWith( new_widget );
							} else if ( ! new_widget.length && old_widget.length ) {
								old_widget.remove();
							} else if ( new_widget.length && ! old_widget.length ) {
								var sidebar_widgets = wp.customize( sidebar_id_to_setting_id( r.data.sidebar_id ) )();
								var position = sidebar_widgets.indexOf( widget_id );
								if ( -1 === position ) {
									throw new Error( 'Unable to determine new widget position in sidebar' );
								}
								if ( sidebar_widgets.length === 1 ) {
									throw new Error( 'Unexpected postMessage for adding first widget to sidebar; refresh must be used instead.' );
								}

								var get_widget_elements = function ( widget_ids ) {
									var widget_elements = [];
									_( widget_ids ).each( function ( widget_id ) {
										var widget = self.getSidebarWidgetElement( sidebar_id, widget_id );
										if ( widget.length ) {
											widget_elements.push( widget[0] );
										}
									} );
									return widget_elements;
								};

								var before_widget_ids = ( position !== 0 ? sidebar_widgets.slice( 0, position ) : [] );
								var before_widgets = jQuery().add( get_widget_elements( before_widget_ids ) );
								var before_widget = before_widgets.last();

								var after_widget_ids = sidebar_widgets.slice( position + 1 );
								var after_widgets = jQuery().add( get_widget_elements( after_widget_ids ) );
								var after_widget = after_widgets.first();

								if ( before_widget.length ) {
									before_widget.after( new_widget );
								} else if ( after_widget.length ) {
									after_widget.before( new_widget );
								} else {
									throw new Error( 'Unable to locate adjacent widget in sidebar.' );
								}
							}

							// Update widget visibility
							self.rendered_widgets[widget_id] = ( 0 !== self.getSidebarWidgetElement( sidebar_id, widget_id ).length );

							self.preview.send( 'rendered-widgets', self.rendered_widgets );
							self.preview.send( 'widget-updated', widget_id );
							wp.customize.trigger( 'sidebar-updated', sidebar_id );
							wp.customize.trigger( 'widget-updated', widget_id );
							self.refreshTransports();
						} );
					} );
				};
				wp.customize( setting_id, binder );
				already_bound_widgets[setting_id] = binder;
			};

			$.each( self.rendered_sidebars, function ( sidebar_id ) {
				var setting_id = sidebar_id_to_setting_id( sidebar_id );
				wp.customize( setting_id, function( value ) {
					// Initially keep track of the sidebars with which widgets are associated.
					// Henceforth we must always scope the widget_id by the associated sidebar_id (see self.getSidebarWidgetElement)
					_( value() ).each( function ( widget_id ) {
						$( '#' + widget_id ).data( 'widget_customizer_sidebar_id', sidebar_id );
					} );

					value.bind( function( to, from ) {
						// Workaround for http://core.trac.wordpress.org/ticket/26061;
						// once fixed, this conditional can be eliminated
						if ( _.isEqual( from, to ) ) {
							return;
						}

						// Delete the widget from the DOM if it no longer exists in the sidebar
						$.each( from, function ( i, old_widget_id ) {
							if ( -1 === to.indexOf( old_widget_id ) ) {
								self.getSidebarWidgetElement( sidebar_id, old_widget_id ).remove();
							}
						} );

						// Sort widgets: reorder relative to the first widget rendered
						var first_rendered_widget_id = _( to ).find( function ( widget_id ) {
							return 0 !== self.getSidebarWidgetElement( sidebar_id, widget_id ).length;
						} );
						var first_rendered_widget = self.getSidebarWidgetElement( sidebar_id, first_rendered_widget_id );
						_.chain( to.slice(0) ).reverse().each( function ( widget_id ) {
							if ( first_rendered_widget_id !== widget_id ) {
								var widget = self.getSidebarWidgetElement( sidebar_id, widget_id );
								first_rendered_widget.after( widget );
							}
						} );

						// Create settings for newly-created widgets
						$.each( to, function ( i, widget_id ) {
							var setting_id = widget_id_to_setting_id( widget_id );
							var setting = wp.customize( setting_id );
							if ( ! setting ) {
								setting = wp.customize.create( setting_id, {} );
							}

							// @todo Is there another way to check if we bound?
							if ( ! already_bound_widgets[widget_id] ) {
								bind_widget_setting( widget_id );
							}

							// Force the callback to fire if this widget is newly-added
							if ( from.indexOf( widget_id ) === -1 ) {
								self.refreshTransports();
								var parent_setting = parent.wp.customize( setting_id );
								if ( 'postMessage' === parent_setting.transport ) {
									setting.callbacks.fireWith( setting, [ setting(), null ] );
								} else {
									self.preview.send( 'refresh' );
								}
							}
						} );

						// If a widget was removed so that no widgets remain rendered in sidebar, we need to disable postMessage
						self.refreshTransports();
						wp.customize.trigger( 'sidebar-updated', sidebar_id );
					} );
				} );
			} );

			$.each( self.registered_widgets, function ( widget_id ) {
				var setting_id = widget_id_to_setting_id( widget_id );
				if ( ! wp.customize.has( setting_id ) ) {
					// Used to have to do this: wp.customize.create( setting_id, instance );
					// Now that the settings are registered at the `wp` action, it is late enough
					// for all filters to be added, e.g. sidebars_widgets for Widget Visibility
					throw new Error( 'Expected customize to have registered setting for widget ' + widget_id );
				}
				bind_widget_setting( widget_id );
			} );

			// Opt-in to LivePreview
			self.refreshTransports();
		}
	};

	$.extend(self, WidgetCustomizerPreview_exports);

	/**
	 * Capture the instance of the Preview since it is private
	 */
	var OldPreview = wp.customize.Preview;
	wp.customize.Preview = OldPreview.extend( {
		initialize: function( params, options ) {
			self.preview = this;
			OldPreview.prototype.initialize.call( this, params, options );
		}
	} );

	/**
	 * @param {String} widget_id
	 * @returns {String}
	 */
	function widget_id_to_setting_id( widget_id ) {
		var setting_id = null;
		var matches = widget_id.match(/^(.+?)(?:-(\d+)?)$/);
		if ( matches ) {
			setting_id = 'widget_' + matches[1] + '[' + matches[2] + ']';
		} else {
			setting_id = 'widget_' + widget_id;
		}
		return setting_id;
	}

	/**
	 * @param {String} widget_id
	 * @returns {String}
	 */
	function widget_id_to_base( widget_id ) {
		return widget_id.replace( /-\d+$/, '' );
	}

	/**
	 * @param {String} sidebar_id
	 * @returns {string}
	 */
	function sidebar_id_to_setting_id( sidebar_id ) {
		return 'sidebars_widgets[' + sidebar_id + ']';
	}

	// @todo on customize ready?
	$(function () {
		self.init();
	});

	return self;
}( jQuery ));
