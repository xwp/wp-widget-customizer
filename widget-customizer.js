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

		/**
		 *
		 */
		ready: function() {
			var control = this;

			control.setting.bind( function( to ) {
				control.updateWidget( to );
			});

			control.container.find( '.widget-control-update' ).on( 'click', function (e) {
				control.updateWidget();
			});

			control.setting.previewer.channel.bind( 'synced', function () {
				control.container.removeClass( 'previewer-loading' );
			});

			control.setupControlToggle();
			control.setupWidgetTitle();
			control.editingEffects();
		},

		/**
		 * @param {object} instance_override  When the model changes, the instance is sent this way
		 */
		updateWidget: function ( instance_override ) {
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

		/**
		 * Show/hide the control when clicking on the form title
		 */
		setupControlToggle: function() {
			var control = this;
			control.container.find('.widget-top').on( 'click', function (e) {
				control.toggleForm();
				e.preventDefault();
			} );
		},

		/**
		 * Expand the widget form control
		 */
		expandForm: function () {
			this.toggleForm( true );
		},

		/**
		 * Collapse the widget form control
		 */
		collapseForm: function () {
			this.toggleForm( false );
		},

		/**
		 * Expand or collapse the widget control
		 * @param {boolean|undefined} [collapsed] If not supplied, will be inverse of current visibility
		 */
		toggleForm: function ( collapsed ) {
			var control = this;
			var widget = control.container.find('div.widget:first');
			var inside = widget.find('.widget-inside:first');
			if ( typeof collapsed === 'undefined' ) {
				collapsed = ! inside.is(':hidden');
			}
			if ( collapsed ) {
				inside.slideUp('fast', function() {
					widget.css({'width':'', 'margin':''});
				});
			}
			else {
				inside.slideDown('fast');
			}
		},

		/**
		 * Update the title of the form if a title field is entered
		 */
		setupWidgetTitle: function () {
			var control = this;
			control.setting.bind( function() {
				control.updateInWidgetTitle();
			});
			control.updateInWidgetTitle();
		},

		/**
		 * Set the widget control title based on any title setting
		 */
		updateInWidgetTitle: function () {
			var control = this;
			var title = control.setting().title;
			var in_widget_title = control.container.find('.in-widget-title');
			if ( title ) {
				in_widget_title.text( ': ' + title );
			}
			else {
				in_widget_title.text( '' );
			}
		},

		/**
		 * Highlight widgets in the preview when
		 * @todo Add support for focus in addition to hover
		 * @todo Should a widget remain highlighted as long as the widget form is expanded?
		 */
		editingEffects: function() {
			var control = this;
			var widget_id;

			/* On control hover */
			$(control.container).hover(
				function () {
					var toRemove = 'customize-control-widget_';
					widget_id = '#' + $(this).attr('id').replace( toRemove, '' );

					$('iframe').contents().find(widget_id).css({
						'border-radius' : '2px',
						'outline' : 'none',
						'box-shadow' : '0 0 3px #CE0000'
					});
				},
				function () {
					$('iframe').contents().find(widget_id).css({ 'box-shadow' : 'none' });
				}
			);

			/* On control input click */
			$(control.container).find('input').click( function () {
				var toRemove = 'customize-control-widget_';
				widget_id = '#' + $(this).closest(control.container).attr('id').replace( toRemove, '' );

				$('iframe').contents().find('body, html').animate({
					scrollTop: $('iframe').contents().find(widget_id).offset().top-20
				}, 1000);

			});
		}
	});

	// Note that 'widget_form' must match the Widget_Form_WP_Customize_Control::$type
	customize.controlConstructor.widget_form = self.constuctor;

	return self;
}( jQuery ));
