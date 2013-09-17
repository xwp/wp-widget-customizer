/*global wp, jQuery */
var WidgetCustomizerPreview = (function ($) {
	'use strict';

	var self = {
		rendered_sidebars: []
	};

	$(function () {
		parent.jQuery('.control-section[id^="accordion-section-sidebar-widgets-"]').hide();
		$.each( self.rendered_sidebars, function ( i, sidebar_id ) {
			parent.jQuery('#accordion-section-sidebar-widgets-' + sidebar_id).show();
		});
	});

	$('.widget').click(function() {
		var widgetId = '#customize-control-widget_'+jQuery(this).attr('id');
		var widget = parent.jQuery(widgetId).children('div.widget');
		var inside = widget.children('.widget-inside');

		if ( inside.is(':hidden') ) {
			inside.slideDown('fast');
		} else {
			inside.slideUp('fast', function() {
				widget.css({'width':'', margin:''});
			});
		}
	});

	return self;
}( jQuery ));