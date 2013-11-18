/*global jQuery, WidgetCustomizerPreview_exports, _ */
/*exported WidgetCustomizerPreview */
var WidgetCustomizerPreview = (function ($) {
	'use strict';

	var self = {
		rendered_sidebars: [],
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
			$.each( self.initial_widget_setting_ids, function( widget_id, setting_id ) {
				wp.customize( setting_id, function( value ) {
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

						var data = {
							widget_customizer_render_widget: 1,
							action: self.render_widget_ajax_action,
							id_base: id_base,
							widget_number: widget_number,
							widget_id: widget_id,
							setting_id: setting_id,
							instance: JSON.stringify( to )
						};
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
								// @todo Inject widget in the proper place
							}
						} );
					} );
				} );
			} );
		}
	};

	$.extend(self, WidgetCustomizerPreview_exports);

	$(function () {
		self.init();
	});

	return self;
}( jQuery ));
