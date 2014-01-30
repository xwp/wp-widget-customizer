/*global jQuery, wp */
jQuery( function ($) {
	wp.customize.bind( 'sidebar-updated', function ( sidebar_id ) {
		if ( 'sidebar-3' === sidebar_id && $.isFunction( $.fn.masonry ) ) {
			var widget_area = $( '#supplementary .widget-area' );
			widget_area.masonry( 'reloadItems' );
			widget_area.masonry();
		}
	} );
} );
