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

	return self;
}( jQuery ));
