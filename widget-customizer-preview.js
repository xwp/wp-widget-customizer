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
		 * @todo This also needs to expand the customizer section if it is not already expanded; at least it needs to highlight it. box-shadow: 0px 0px 10px red?
		 * @todo In addition to expanding the form, should we highlight the <div class="widget"> as a whole with box-shadow: 0px 0px 10px red?
		 */
		highlightControls: function() {

			var selector = this.widget_selectors.join(',');
			$(document).on( 'click', selector, function () {
				var control = parent.WidgetCustomizer.getControlInstanceForWidget( $(this).prop('id') );
				if ( control ) {
					control.expandForm();
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
