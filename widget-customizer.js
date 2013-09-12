/*global wp, jQuery, WidgetCustomizer_exports, console, alert */
var WidgetCustomizer = (function ($) {
	'use strict';

	var customize = wp.customize;
	var self = {
		control: null,
		ajax_action: null,
		nonce_value: null,
		nonce_post_key: null
	};
	$.extend(self, WidgetCustomizer_exports);

	self.constuctor = customize.Control.extend({
		ready: function() {
			var control = this;

			control.setting.bind( function( to ) {
				control.update_widget( to );
			});

			control.container.find( '.widget-control-update' ).on( 'click', function (e) {
				control.update_widget();
			});

			control.setting.previewer.channel.bind( 'synced', function () {
				control.container.removeClass( 'previewer-loading' );
			});

			control.controlTabs();
		},

		/**
		 * @param {object} instance_override  When the model changes, the instance is sent this way
		 */
		update_widget: function ( instance_override ) {
			var control = this;
			var data = control.container.find(':input').serialize();
			control.container.addClass( 'widget-form-loading' );
			control.container.addClass( 'previewer-loading' );
			control.container.find( '.widget-content' ).prop( 'disabled', true );

			var params = {};
			params.action = self.ajax_action;
			params[self.nonce_post_key] = self.nonce_value;
			if ( instance_override ) {
				params.json_instance_override = JSON.stringify( instance_override );
			}
			data += '&' + $.param( params );

			var jqxhr = $.post( wp.ajax.settings.url, data, function (r) {
				if ( r.success ) {
					control.container.find( '.widget-content' ).html( r.data.form );
					if ( ! instance_override ) {
						control.setting( r.data.instance );
					}
				}
				else {
					var message = 'FAIL';
					if ( r.data && r.data.message ) {
						message = r.data.message;
					}
					alert( message );
				}
			});
			jqxhr.fail( function (jqXHR, textStatus, errorThrown) {
				alert( textStatus );
			});
			jqxhr.always( function () {
				control.container.find( '.widget-content' ).prop( 'disabled', false );
				control.container.removeClass( 'widget-form-loading' );
			});
		},

		controlTabs: function() {
			$('.widget-top').click( function () {
				$(this).next().toggleClass('visible');
			});
		}

	});

	// Note that 'widget_form' must match the Widget_Form_WP_Customize_Control::$type
	customize.controlConstructor.widget_form = self.constuctor;



	return self;
}( jQuery ));
