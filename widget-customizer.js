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
		 * Set up the control
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
		 * @param {object} [instance_override]  When the model changes, the instance is sent this way
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
			jqxhr.fail( function (jqXHR, textStatus ) {
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
		 * Expand the accordion section containing a control
		 */
		expandControlSection: function () {
			var section = this.container.closest( '.accordion-section' );
			if ( ! section.hasClass('open') ) {
				section.find('.accordion-section-title:first').trigger('click');
			}
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
		 * @param {boolean|undefined} [do_expand] If not supplied, will be inverse of current visibility
		 */
		toggleForm: function ( do_expand ) {
			var control = this;
			var widget = control.container.find('div.widget:first');
			var inside = widget.find('.widget-inside:first');
			if ( typeof do_expand === 'undefined' ) {
				do_expand = ! inside.is(':visible');
			}
			if ( do_expand ) {
				control.container.trigger('expand');
				inside.slideDown('fast');
			}
			else {
				control.container.trigger('collapse');
				inside.slideUp('fast', function() {
					widget.css({'width':'', 'margin':''});
				});
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
		 * Inverse of WidgetCustomizer.getControlInstanceForWidget
		 * @return {jQuery}
		 */
		getPreviewWidgetElement: function () {
			var control = this;
			var iframe_contents = $('#customize-preview iframe').contents();
			return iframe_contents.find('#' + control.params.widget_id);
		},

		/**
		 * Inside of the customizer preview, scroll the widget into view
		 */
		scrollPreviewWidgetIntoView: function () {
			var control = this;
			var widget_el = control.getPreviewWidgetElement();
			if ( widget_el.length ) {
				widget_el[0].scrollIntoView( false );
			}
		},

		/**
		 * Highlight the widget control and section
		 */
		highlightSectionAndControl: function() {
			var control = this;
			var target_element;
			if ( control.container.is(':hidden') ) {
				target_element = control.container.closest('.control-section');
			}
			else {
				target_element = control.container;
			}

			$('.widget-customizer-highlighted').removeClass('widget-customizer-highlighted');
			target_element.addClass('widget-customizer-highlighted');
			setTimeout( function () {
				target_element.removeClass('widget-customizer-highlighted');
			}, 500 );
		},

		/**
		 * Add the widget-customizer-highlighted-widget class to the widget for 500ms
		 */
		highlightPreviewWidget: function () {
			var control = this;
			var widget_el = control.getPreviewWidgetElement();
			var root_el = widget_el.closest('html');
			root_el.find('.widget-customizer-highlighted-widget').removeClass('widget-customizer-highlighted-widget');
			widget_el.addClass('widget-customizer-highlighted-widget');
			setTimeout( function () {
				widget_el.removeClass('widget-customizer-highlighted-widget');
			}, 500 );
		},

		/**
		 * Highlight widgets in the preview when
		 */
		editingEffects: function() {
			var control = this;

			// Highlight whenever hovering or clicking over the form
			control.container.on('mouseenter click', function () {
				control.highlightPreviewWidget();
			});

			// Highlight when the setting is updated
			control.setting.bind( function() {
				control.scrollPreviewWidgetIntoView();
				control.highlightPreviewWidget();
			});

			// Highlight when the widget form is expanded
			control.container.on('expand', function () {
				control.scrollPreviewWidgetIntoView();
			});

		}
	});

	/**
	 * Given a widget_id for a widget appearing in the preview.
	 * @param {string} widget_id
	 * @return {null|object}
	 */
	self.getControlInstanceForWidget = function ( widget_id ) {
		var widget_control = null;
		wp.customize.control.each(function ( control ) {
			if ( control.params.type === 'widget_form' && control.params.widget_id === widget_id ) {
				widget_control = control;
				// @todo How do we break?
			}
		});
		return widget_control;
	};
	
	self.initalizeSortables = function  ()
	{
		$(".accordion-section-content").sortable({
			update: function  (e, ui)
			{
				var sidebar_id = $($(this).find('.sidebar')[0]).val(); 
				var widgets_array = $(this).sortable('toArray');
				var widget_ids = new Array();
				$.each(widgets_array, function  (k,v){
					widget_ids.push(v);
				});
				//@todo ajax load start state
				var widgets_order_value = widget_ids;
				var data_obj = {
						"action": "update_widget_order",
						"savewidgets": self.widget_order_nonce
					};
				data_obj['sidebars['+sidebar_id+']'] = widgets_order_value; 
				$.ajax({
					method: 'POST',
					url: wp.ajax.settings.url,
					data: data_obj,
					success: function  ()
					{
						//@todo enable load states
						//@todo what's the best way to trigger a customizer refresh?
					}
				});
			}
		}); 
	};
	
	//@todo where is the best place to hook this inside the wp.customize API?
	self.initalizeSortables();
	// Note that 'widget_form' must match the Widget_Form_WP_Customize_Control::$type
	customize.controlConstructor.widget_form = self.constuctor;

	return self;
}( jQuery ));
