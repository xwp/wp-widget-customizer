/*global wp, Backbone, _, jQuery, WidgetCustomizer_exports, alert */
/*exported WidgetCustomizer */
var WidgetCustomizer = (function ($) {
	'use strict';

	var customize = wp.customize;
	var self = {
		update_widget_ajax_action: null,
		update_widget_nonce_value: null,
		update_widget_nonce_post_key: null,
		i18n: {},
		available_widgets: [],
		active_sidebar_control: null,
		sidebars_eligible_for_post_message: {},
		widgets_eligible_for_post_message: {},
		current_theme_supports: false,
		previewer: null
	};
	$.extend(self, WidgetCustomizer_exports);

	// Lots of widgets expect this old ajaxurl global to be available
	if ( typeof window.ajaxurl === 'undefined' ) {
		window.ajaxurl = wp.ajax.settings.url;
	}

	// Unfortunately many widgets try to look for instances under div#widgets-right,
	// so we have to add that ID to a container div in the customizer for compat
	$( '#customize-theme-controls' ).closest( 'div:not([id])' ).attr( 'id', 'widgets-right' );

	/**
	 * Set up model
	 */
	var Widget = self.Widget = Backbone.Model.extend({
		id: null,
		temp_id: null,
		classname: null,
		control_tpl: null,
		description: null,
		is_disabled: null,
		is_multi: null,
		multi_number: null,
		name: null,
		id_base: null,
		transport: 'refresh',
		params: []
	});
	var WidgetLibrary = self.WidgetLibrary = Backbone.Collection.extend({
		model: Widget
	});
	self.available_widgets = new WidgetLibrary(self.available_widgets);

	/**
	 * Sidebar Widgets control
	 * Note that 'sidebar_widgets' must match the Sidebar_Widgets_WP_Customize_Control::$type
	 */
	customize.controlConstructor.sidebar_widgets = customize.Control.extend({

		/**
		 * Set up the control
		 */
		ready: function() {
			var control = this;
			control.control_section = control.container.closest( '.control-section' );
			control.section_content = control.container.closest( '.accordion-section-content' );
			control.setupModel();
			control.setupSortable();
			control.setupAddition();
		},

		/**
		 * Update ordering of widget control forms when the setting is updated
		 */
		setupModel: function() {
			var control = this;
			control.setting.bind( function( new_widget_ids, old_widget_ids ) {
				var removed_widget_ids = _( old_widget_ids ).difference( new_widget_ids );

				// Filter out any persistent widget_ids for widgets which have been deactivated
				new_widget_ids = _( new_widget_ids ).filter( function ( new_widget_id ) {
					var parsed_widget_id = parse_widget_id( new_widget_id );
					return !! self.available_widgets.findWhere({ id_base: parsed_widget_id.id_base } );
				} );

				var widget_form_controls = _( new_widget_ids ).map( function ( widget_id ) {
					var widget_form_control = self.getWidgetFormControlForWidget( widget_id );
					if ( ! widget_form_control ) {
						widget_form_control = control.addWidget( widget_id );
					}
					return widget_form_control;
				} );

				// Sort widget controls to their new positions
				widget_form_controls.sort(function ( a, b ) {
					var a_index = new_widget_ids.indexOf( a.params.widget_id );
					var b_index = new_widget_ids.indexOf( b.params.widget_id );
					if ( a_index === b_index ) {
						return 0;
					}
					return a_index < b_index ? -1 : 1;
				});

				var sidebar_widgets_add_control = control.section_content.find( '.customize-control-sidebar_widgets' );

				// Append the controls to put them in the right order
				var final_control_containers = _( widget_form_controls ).map( function( widget_form_controls ) {
					return widget_form_controls.container[0];
				} );

				// Re-sort widget form controls
				sidebar_widgets_add_control.before( final_control_containers );

				var must_refresh_preview = false;

				// If the widget was dragged into the sidebar, make sure the sidebar_id param is updated
				_( widget_form_controls ).each( function ( widget_form_control ) {
					if ( widget_form_control.params.sidebar_id !== control.params.sidebar_id ) {
						must_refresh_preview = true;
					}
					widget_form_control.params.sidebar_id = control.params.sidebar_id;
				} );

				// Delete any widget form controls for removed widgets
				_( removed_widget_ids ).each( function ( removed_widget_id ) {
					var removed_control = self.getWidgetFormControlForWidget( removed_widget_id );
					if ( ! removed_control ) {
						return;
					}

					// Detect if widget control was dragged to another sidebar and abort
					if ( ! $.contains( control.section_content[0], removed_control.container[0] ) ) {
						return;
					}

					wp.customize.control.remove( removed_control.id );
					removed_control.container.remove();

					// Move widget to inactive widgets sidebar
					var inactive_widgets = wp.customize.value( 'sidebars_widgets[wp_inactive_widgets]' )().slice();
					inactive_widgets.push( removed_widget_id );
					wp.customize.value( 'sidebars_widgets[wp_inactive_widgets]' )( _( inactive_widgets ).unique() );

					// Make old single widget available for adding again
					var widget = self.available_widgets.findWhere({ id_base: removed_control.params.widget_id_base });
					if ( widget && ! widget.get( 'is_multi' ) ) {
						widget.set( 'is_disabled', false );
					}
				} );

				if ( must_refresh_preview ) {
					self.previewer.refresh();
				}
			});
		},

		/**
		 * Allow widgets in sidebar to be re-ordered, and for the order to be previewed
		 */
		setupSortable: function () {
			var control = this;

			/**
			 * Update widget order setting when controls are re-ordered
			 */
			control.section_content.sortable({
				items: '> .customize-control-widget_form',
				handle: '.widget-top',
				axis: 'y',
				connectWith: '.accordion-section-content:has(.customize-control-sidebar_widgets)',
				update: function () {
					var widget_container_ids = control.section_content.sortable('toArray');
					var widget_ids = $.map( widget_container_ids, function ( widget_container_id ) {
						return $('#' + widget_container_id).find(':input[name=widget-id]').val();
					});
					control.setting( widget_ids );
				}
			});

			/**
			 * Expand other customizer sidebar section when dragging a control widget over it,
			 * allowing the control to be dropped into another section
			 */
			control.control_section.find( '.accordion-section-title').droppable({
				accept: '.customize-control-widget_form',
				over: function () {
					if ( ! control.control_section.hasClass('open') ) {
						control.control_section.addClass('open');
						control.section_content.toggle(false).slideToggle(150, function () {
							control.section_content.sortable( 'refreshPositions' );
						});
					}
				}
			});
		},

		/**
		 *
		 */
		setupAddition: function () {
			var control = this;

			control.container.find( '.add-new-widget' ).on( 'click keydown', function( event ) {
				if ( event.type === 'keydown' && ! ( event.which === 13 || event.which === 32 ) ) { // Enter or Spacebar
					return;
				}

				if ( ! $( 'body' ).hasClass( 'adding-widget' ) ) {
					self.availableWidgetsPanel.open( control );
				} else {
					self.availableWidgetsPanel.close();
				}
			} );

		},

		/**
		 * @param {string} widget_id or an id_base for adding a previously non-existing widget
		 * @returns {object} widget_form control instance
		 */
		addWidget: function ( widget_id ) {
			var control = this;
			var parsed_widget_id = parse_widget_id( widget_id );
			var widget_number = parsed_widget_id.number;
			var widget_id_base = parsed_widget_id.id_base;
			var widget = self.available_widgets.findWhere({id_base: widget_id_base});
			if ( ! widget ) {
				throw new Error( 'Widget unexpectedly not found.' );
			}
			if ( widget_number && ! widget.get( 'is_multi' ) ) {
				throw new Error( 'Did not expect a widget number to be supplied for a non-multi widget' );
			}

			// Set up new multi widget
			if ( widget.get( 'is_multi' ) && ! widget_number ) {
				widget.set( 'multi_number', widget.get( 'multi_number' ) + 1 );
				widget_number = widget.get( 'multi_number' );
			}

			var control_html = $( '#widget-tpl-' + widget.get('id') ).html();
			if ( widget.get( 'is_multi' ) ) {
				control_html = control_html.replace(/<[^<>]+>/g, function (m) {
					return m.replace( /__i__|%i%/g, widget_number );
				});
			}
			else {
				widget.set( 'is_disabled', true ); // Prevent single widget from being added again now
			}

			var customize_control_type = 'widget_form';
			var customize_control = $('<li></li>');
			customize_control.addClass( 'customize-control' );
			customize_control.addClass( 'customize-control-' + customize_control_type );
			customize_control.append( $( control_html ) );
			customize_control.find( '> .widget-icon' ).remove();
			if ( widget.get( 'is_multi' ) ) {
				customize_control.find( 'input[name="widget_number"]' ).val( widget_number );
				customize_control.find( 'input[name="multi_number"]' ).val( widget_number );
			}
			widget_id = customize_control.find('[name="widget-id"]' ).val();
			customize_control.hide(); // to be slid-down below

			var setting_id = 'widget_' + widget.get('id_base');
			if ( widget.get( 'is_multi' ) ) {
				setting_id += '[' + widget_number + ']';
			}
			customize_control.attr( 'id', 'customize-control-' + setting_id.replace( /\]/g, '' ).replace( /\[/g, '-' ) );

			control.container.after( customize_control );

			// Only create setting if it doesn't already exist (if we're adding a pre-existing inactive widget)
			var is_existing_widget = wp.customize.has( setting_id );
			if ( ! is_existing_widget ) {
				var setting_args = {
					transport: 'refresh', // preview window will opt-in to postMessage if available
					previewer: control.setting.previewer
				};
				var sidebar_can_live_preview = self.getPreviewWindow().WidgetCustomizerPreview.sidebarCanLivePreview( control.params.sidebar_id );
				var widget_can_live_preview = !! self.widgets_eligible_for_post_message[ widget_id_base ];
				if ( self.current_theme_supports && sidebar_can_live_preview && widget_can_live_preview ) {
					setting_args.transport = 'postMessage';
				}
				wp.customize.create( setting_id, setting_id, {}, setting_args );
			}

			var Constructor = wp.customize.controlConstructor[customize_control_type];
			var widget_form_control = new Constructor( setting_id, {
				params: {
					settings: {
						'default': setting_id
					},
					sidebar_id: control.params.sidebar_id,
					widget_id: widget_id,
					widget_id_base: widget.get( 'id_base' ),
					type: customize_control_type
				},
				previewer: control.setting.previewer
			} );
			wp.customize.control.add( setting_id, widget_form_control );

			// Add widget to this sidebar
			var sidebar_widgets = control.setting().slice();
			if ( -1 === sidebar_widgets.indexOf( widget_id ) ) {
				sidebar_widgets.push( widget_id );
				control.setting( sidebar_widgets );
			}

			// Make sure widget is removed from the other sidebars
			wp.customize.each( function ( other_setting ) {
				if ( other_setting.id === control.setting.id ) {
					return;
				}
				if ( 0 !== other_setting.id.indexOf( 'sidebars_widgets[' ) ) {
					return;
				}
				var other_sidebar_widgets = other_setting().slice();
				var i = other_sidebar_widgets.indexOf( widget_id );
				if ( -1 !== i ) {
					other_sidebar_widgets.splice( i );
					other_setting( other_sidebar_widgets );
				}
			} );

			var form_autofocus = function () {
				var first_inside_input = widget_form_control.container.find( '.widget-inside :input:visible:first' );
				if ( first_inside_input.length ) {
					first_inside_input.focus();
				} else {
					widget_form_control.container.find( '.widget-top .widget-action:first' ).focus();
				}
			};

			customize_control.slideDown(function () {
				widget_form_control.expandForm();

				if ( is_existing_widget ) {
					widget_form_control.updateWidget( widget_form_control.setting(), function () {
						form_autofocus();
					} );
				}
				else {
					form_autofocus();
				}
			});

			return widget_form_control;
		}

	});

	/**
	 * Widget Form control
	 * Note that 'widget_form' must match the Widget_Form_WP_Customize_Control::$type
	 */
	customize.controlConstructor.widget_form = customize.Control.extend({

		/**
		 * Set up the control
		 */
		ready: function() {
			var control = this;

			control.suppress_update = false;
			control.setting.bind( function( to, from ) {
				if ( ! _( from ).isEqual( to ) && ! control.suppress_update ) {
					control.updateWidget( to );
				}
			});

			var save_btn = control.container.find( '.widget-control-save' );
			save_btn.val( self.i18n.save_btn_label );
			save_btn.attr( 'title', self.i18n.save_btn_tooltip );
			save_btn.removeClass( 'button-primary' ).addClass( 'button-secondary' );
			save_btn.on( 'click', function (e) {
				e.preventDefault();
				control.updateWidget();
			});

			var close_btn = control.container.find( '.widget-control-close' );
			// @todo Hitting Enter on this link does nothing; will be resolved in core with <http://core.trac.wordpress.org/ticket/26633>
			close_btn.on( 'click', function (e) {
				e.preventDefault();
				control.collapseForm();
				control.container.find( '.widget-top .widget-action:first' ).focus(); // keyboard accessibility
			} );

			var remove_btn = control.container.find( 'a.widget-control-remove' );
			// @todo Hitting Enter on this link does nothing; will be resolved in core with <http://core.trac.wordpress.org/ticket/26633>
			remove_btn.on( 'click', function (e) {
				e.preventDefault();

				// Find an adjacent element to add focus to when this widget goes away
				var adjacent_focus_target;
				if ( control.container.next().is( '.customize-control-widget_form' ) ) {
					adjacent_focus_target = control.container.next().find( '.widget-action:first' );
				} else if ( control.container.prev().is( '.customize-control-widget_form' ) ) {
					adjacent_focus_target = control.container.prev().find( '.widget-action:first' );
				} else {
					adjacent_focus_target = control.container.next( '.customize-control-sidebar_widgets' ).find( '.add-new-widget:first' );
				}

				control.container.slideUp( function() {
					var sidebars_widgets_control = self.getSidebarWidgetControlContainingWidget( control.params.widget_id );
					if ( ! sidebars_widgets_control ) {
						throw new Error( 'Unable to find sidebars_widgets_control' );
					}
					var sidebar_widget_ids = sidebars_widgets_control.setting().slice();
					var i = sidebar_widget_ids.indexOf( control.params.widget_id );
					if ( -1 === i ) {
						throw new Error( 'Widget is not in sidebar' );
					}
					sidebar_widget_ids.splice( i, 1 );
					sidebars_widgets_control.setting( sidebar_widget_ids );
					adjacent_focus_target.focus(); // keyboard accessibility
				});
			} );
			remove_btn.text( self.i18n.remove_btn_label ); // wp_widget_control() outputs the link as "Delete"
			remove_btn.attr( 'title', self.i18n.remove_btn_tooltip );

			// Trigger widget form update when hitting Enter within an input
			control.container.find( '.widget-content' ).on( 'keydown', 'input', function(e) {
				if ( 13 === e.which ){ // Enter
					control.updateWidget();
					e.preventDefault();
				}
			});

			// Remove loading indicators when the setting is saved and the preview updates
			control.setting.previewer.channel.bind( 'synced', function () {
				control.container.removeClass( 'previewer-loading' );
			});
			self.previewer.bind( 'widget-updated', function ( updated_widget_id ) {
				if ( updated_widget_id === control.params.widget_id ) {
					control.container.removeClass( 'previewer-loading' );
				}
			} );

			control.setupControlToggle();
			control.setupWidgetTitle();
			control.editingEffects();
		},

		/**
		 * @param {object} [instance_override]  When the model changes, the instance is sent this way
		 * @param {function} [success_callback]  Function which is called when the request finishes
		 */
		updateWidget: function ( instance_override, success_callback ) {
			var control = this;
			var data = control.container.find(':input').serialize();

			control.container.addClass( 'widget-form-loading' );
			control.container.addClass( 'previewer-loading' );
			control.container.find( '.widget-content' ).prop( 'disabled', true );

			var params = {};
			params.action = self.update_widget_ajax_action;
			params[self.update_widget_nonce_post_key] = self.update_widget_nonce_value;
			if ( instance_override ) {
				params.json_instance_override = JSON.stringify( instance_override );
			}
			data += '&' + $.param( params );

			var jqxhr = $.post( wp.ajax.settings.url, data, function (r) {
				if ( r.success ) {
					control.container.find( '.widget-content' ).html( r.data.form );

					if ( ! instance_override ) { // @todo why?
						control.suppress_update = true; // We already updated it with r.data.form above
						control.setting( r.data.instance );
						control.suppress_update = false;
					}
					if ( success_callback ) {
						success_callback.call( null, control );
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
			var iframe_contents = $('#customize-preview').find('iframe').contents();
			return iframe_contents.find('#' + control.params.widget_id);
		},

		/**
		 * Inside of the customizer preview, scroll the widget into view
		 */
		scrollPreviewWidgetIntoView: function () {
			// @todo scrollIntoView() provides a robust but very poor experience. Animation is needed. See https://github.com/x-team/wp-widget-customizer/issues/16
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
			control.container.on( 'mouseenter click', function () {
				control.highlightPreviewWidget();
			});

			// Highlight when the setting is updated
			control.setting.bind( function() {
				control.scrollPreviewWidgetIntoView();
				control.highlightPreviewWidget();
			});

			// Highlight when the widget form is expanded
			control.container.on( 'expand', function () {
				control.scrollPreviewWidgetIntoView();
			});

		}
	});

	/**
	 * Capture the instance of the Previewer since it is private
	 */
	var OldPreviewer = wp.customize.Previewer;
	wp.customize.Previewer = OldPreviewer.extend( {
		initialize: function( params, options ) {
			self.previewer = this;
			OldPreviewer.prototype.initialize.call( this, params, options );
			this.bind( 'refresh', this.refresh );
		}
	} );

	/**
	 * Given a widget control, find the sidebar widgets control that contains it.
	 * @param {string} widget_id
	 * @return {object|null}
	 */
	self.getSidebarWidgetControlContainingWidget = function ( widget_id ) {
		var found_control = null;
		// @todo wp.customize.control needs the _.find method
		wp.customize.control.each( function ( control ) {
			if ( control.params.type === 'sidebar_widgets' && -1 !== control.setting().indexOf( widget_id ) ) {
				found_control = control;
			}
		});
		return found_control;
	};

	/**
	 * Given a widget_id for a widget appearing in the preview, get the widget form control associated with it
	 * @param {string} widget_id
	 * @return {object|null}
	 */
	self.getWidgetFormControlForWidget = function ( widget_id ) {
		var found_control = null;
		// @todo wp.customize.control needs the _.find method
		wp.customize.control.each( function ( control ) {
			if ( control.params.type === 'widget_form' && control.params.widget_id === widget_id ) {
				found_control = control;
			}
		});
		return found_control;
	};

	/**
	 * @returns {DOMWindow}
	 */
	self.getPreviewWindow = function (){
		return $( '#customize-preview' ).find( 'iframe' ).prop( 'contentWindow' );
	};

	/**
	 * Available Widgets Panel
	 */
	self.availableWidgetsPanel = {
		active_sidebar_widgets_control: null,
		selected_widget_tpl: null,

		/**
		 * Set up event listeners
		 */
		setup: function () {
			var panel = this;

			var update_available_widgets_list = function () {
				self.available_widgets.each(function ( widget ) {
					var widget_tpl = $( '#widget-tpl-' + widget.id );
					widget_tpl.toggle( ! widget.get( 'is_disabled' ) );
					if ( widget.get( 'is_disabled' ) && widget_tpl.is( panel.selected_widget_tpl ) ) {
						panel.selected_widget_tpl = null;
					}
				});
			};

			self.available_widgets.on( 'change', update_available_widgets_list );
			update_available_widgets_list();

			// If the available widgets panel is open and the customize controls are
			// interacted with (i.e. available widgets panel is blurred) then close the
			// available widgets panel.
			$( '#customize-controls' ).on( 'click keydown', function ( e ) {
				var is_add_new_widget_btn = $( e.target ).is( '.add-new-widget, .add-new-widget *' );
				if ( $( 'body' ).hasClass( 'adding-widget' ) && ! is_add_new_widget_btn ) {
					panel.close();
				}
			} );

			// Close the panel if the URL in the preview changes
			self.previewer.bind( 'url', function () {
				panel.close();
			} );

			// Submit a selection when clicked or keypressed
			$( '#available-widgets .widget-tpl' ).on( 'click keypress', function( event ) {

				// Only proceed with keypress if it is Enter or Spacebar
				if ( event.type === 'keydown' && ( event.which !== 13 && event.which !== 32 ) ) {
					return;
				}

				panel.submit( this );
			} );

			$( '#available-widgets' ).liveFilter(
				'#available-widgets-filter input',
				'.widget-tpl',
				{
					filterChildSelector: '.widget-title h4',
					after: function () {
						var filter_val = $( '#available-widgets-filter input' ).val();

						// Remove a widget from being selected if it is no longer visible
						if ( panel.selected_widget_tpl && ! panel.selected_widget_tpl.is( ':visible' ) ) {
							panel.selected_widget_tpl.removeClass( 'selected' );
							panel.selected_widget_tpl = null;
						}

						// If a widget was selected but the filter value has been cleared out, clear selection
						if ( panel.selected_widget_tpl && ! filter_val ) {
							panel.selected_widget_tpl.removeClass( 'selected' );
							panel.selected_widget_tpl = null;
						}

						// If a filter has been entered and a widget hasn't been selected, select the first one shown
						if ( ! panel.selected_widget_tpl && filter_val ) {
							var first_visible_widget = $( '#available-widgets > .widget-tpl:visible:first' );
							if ( first_visible_widget.length ) {
								panel.select( first_visible_widget );
							}
						}

					}
				}
			);

			// Select a widget when it is focused on
			$( '#available-widgets > .widget-tpl' ).on( 'focus', function () {
				panel.select( this );
			} );

			$( '#available-widgets' ).on( 'keydown', function ( event ) {
				var is_enter = ( event.which === 13 );
				var is_esc = ( event.which === 27 );
				var is_down = ( event.which === 40 );
				var is_up = ( event.which === 38 );
				var selected_widget_tpl = null;
				var first_visible_widget = $( '#available-widgets > .widget-tpl:visible:first' );
				var last_visible_widget = $( '#available-widgets > .widget-tpl:visible:last' );

				if ( is_down || is_up ) {
					if ( is_down ) {
						if ( $( event.target ).is( 'input' ) ) {
							selected_widget_tpl = first_visible_widget;
						} else if ( panel.selected_widget_tpl && panel.selected_widget_tpl.nextAll( '.widget-tpl:visible' ).length !== 0 ) {
							selected_widget_tpl = panel.selected_widget_tpl.nextAll( '.widget-tpl:visible:first' );
						}
					} else if ( is_up ) {
						if ( $( event.target ).is( 'input' ) ) {
							selected_widget_tpl = last_visible_widget;
						} else if ( panel.selected_widget_tpl && panel.selected_widget_tpl.prevAll( '.widget-tpl:visible' ).length !== 0 ) {
							selected_widget_tpl = panel.selected_widget_tpl.prevAll( '.widget-tpl:visible:first' );
						}
					}
					panel.select( selected_widget_tpl );
					if ( selected_widget_tpl ) {
						selected_widget_tpl.focus();
					} else {
						$( '#available-widgets-filter input' ).focus();
					}
					return;
				}

				// If enter pressed but nothing entered, don't do anything
				if ( is_enter && ! $( this ).val() ) {
					return;
				}

				if ( is_enter ) {
					panel.submit();
				} else if ( is_esc ) {
					panel.close( { return_focus: true } );
				}
			} );
		},

		/**
		 * @param widget_tpl
		 */
		select: function ( widget_tpl ) {
			var panel = this;
			panel.selected_widget_tpl = $( widget_tpl );
			panel.selected_widget_tpl.siblings( '.widget-tpl' ).removeClass( 'selected' );
			panel.selected_widget_tpl.addClass( 'selected' );
		},

		submit: function ( widget_tpl ) {
			var panel = this;
			if ( ! widget_tpl ) {
				widget_tpl = panel.selected_widget_tpl;
			}
			if ( ! widget_tpl ) {
				return;
			}
			panel.select( widget_tpl );

			var widget_id = $( panel.selected_widget_tpl ).data( 'widget-id' );
			var widget = self.available_widgets.findWhere({id: widget_id});
			if ( ! widget ) {
				throw new Error( 'Widget unexpectedly not found.' );
			}
			panel.active_sidebar_widgets_control.addWidget( widget.get( 'id_base' ) );
			panel.close();
		},

		/**
		 * @param sidebars_widgets_control
		 */
		open: function ( sidebars_widgets_control ) {
			this.active_sidebar_widgets_control = sidebars_widgets_control;
			$( 'body' ).addClass( 'adding-widget' );
			$( '#available-widgets .widget-tpl' ).removeClass( 'selected' );
			$( '#available-widgets-filter input' ).focus();
		},

		/**
		 * Hide the panel
		 */
		close: function ( options ) {
			options = options || {};
			if ( options.return_focus && this.active_sidebar_widgets_control ) {
				this.active_sidebar_widgets_control.container.find( '.add-new-widget' ).focus();
			}
			this.active_sidebar_widgets_control = null;
			this.selected_widget_tpl = null;
			$( 'body' ).removeClass( 'adding-widget' );
			$( '#available-widgets-filter input' ).val( '' );
		}
	};

	$( function () {
		self.availableWidgetsPanel.setup();
	} );


	/**
	 * @param {String} widget_id
	 * @returns {Object}
	 */
	function parse_widget_id( widget_id ) {
		var parsed = {
			number: null,
			id_base: null
		};
		var matches = widget_id.match( /^(.+)-(\d+)$/ );
		if ( matches ) {
			parsed.id_base = matches[1];
			parsed.number = parseInt( matches[2], 10 );
		} else {
			// likely an old single widget
			parsed.id_base = widget_id;
		}
		return parsed;
	}

	return self;
}( jQuery ));
