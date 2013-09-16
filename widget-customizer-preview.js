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
		parent.jQuery("li.selected").removeClass('selected');
		parent.jQuery(widgetId).toggleClass('selected');
	});

	return self;
}( jQuery ));
