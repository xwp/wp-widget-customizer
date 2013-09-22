/*global wp, jQuery */
var WidgetCustomizerPreview = (function ($) {
	'use strict';

	var self = {
		rendered_sidebars: [],

		init: function () {
			this.toggleSections();
			this.highlightControls();
		},

		toggleSections: function () {
			parent.jQuery('.control-section[id^="accordion-section-sidebar-widgets-"]').hide();
			$.each( self.rendered_sidebars, function ( i, sidebar_id ) {
				parent.jQuery('#accordion-section-sidebar-widgets-' + sidebar_id).show();
			});
		},

		highlightControls: function() {
			$(document).on( 'click', '.widget', function () {
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

	$(function () {
		self.init();
	});

	return self;
}( jQuery ));
