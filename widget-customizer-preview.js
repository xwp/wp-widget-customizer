/*global wp, jQuery, WidgetCustomizerPreview_exports */
var WidgetCustomizerPreview = (function ($) {
	'use strict';

	var self = {
		rendered_sidebars: [],
		registered_sidebars: {},
		widget_selectors: [],

		init: function () {
			this.buildWidgetSelectors();
			this.toggleSections();
			this.highlightControls();
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
		 * @todo This also needs to expand the customizer section if it is not already expanded; at least it needs to highlight it
		 */
		highlightControls: function() {

			var selector = this.widget_selectors.join(',');
			$(document).on( 'click', selector, function () {

				var widget_id = '#customize-control-widget_' + $(this).attr('id');
				var widget = parent.jQuery(widget_id).children('div.widget');
				var inside = widget.children('.widget-inside');

				if ( inside.is(':hidden') ) {
					inside.slideDown('fast');
				} else {
					inside.slideUp('fast', function () {
						widget.css({'width':'', margin:''});
					});
				}
			});

		}
	};

	$.extend(self, WidgetCustomizerPreview_exports);

	$(function () {
		self.init();
	});

	return self;
}( jQuery ));
