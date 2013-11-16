/*global jQuery, WidgetCustomizerPreview_exports, _, console */
/*exported WidgetCustomizerPreview */
var WidgetCustomizerPreview = (function ($) {
	'use strict';

	var self = {
		rendered_sidebars: [],
		registered_sidebars: {},
		widget_selectors: [],
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
			$.each( self.initial_widget_setting_ids, function( i, setting_id ) {
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
						console.info( 'TODO: AJAX', setting_id, to );
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
