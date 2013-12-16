<?php
/**
 * Plugin Name: Widget Customizer
 * Description: Edit widgets and preview changes in Theme Customizer, with a control for each widget form in sections added for each sidebar rendered in the preview.
 * Version:     0.10.1
 * Author:      X-Team
 * Author URI:  http://x-team.com/wordpress/
 * License:     GPLv2+
 * Text Domain: widget-customizer
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2013 X-Team (http://x-team.com/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class Widget_Customizer {
	const UPDATE_WIDGET_AJAX_ACTION    = 'update_widget';
	const RENDER_WIDGET_AJAX_ACTION    = 'render_widget';
	const UPDATE_WIDGET_NONCE_POST_KEY = 'update-sidebar-widgets-nonce';
	const RENDER_WIDGET_NONCE_POST_KEY = 'render-sidebar-widgets-nonce';
	const RENDER_WIDGET_QUERY_VAR      = 'widget_customizer_render_widget';

	protected static $core_widget_base_ids = array(
		'archives',
		'calendar',
		'categories',
		'links',
		'meta',
		'nav_menu',
		'pages',
		'recent-comments',
		'recent-posts',
		'rss',
		'search',
		'tag_cloud',
		'text',
	);

	protected static $builtin_supported_themes_with_scripts = array(
		'tewntyten' => false,
		'tewntyeleven' => false,
		'tewntytwelve' => false,
		'twentythirteen' => true,
		'twentyfourteen' => false,
	);

	static function setup() {
		self::load_textdomain();
		add_action( 'after_setup_theme', array( __CLASS__, 'add_builtin_theme_support' ) );
		add_action( 'after_setup_theme', array( __CLASS__, 'setup_widget_addition_previews' ) );
		add_action( 'customize_controls_init', array( __CLASS__, 'customize_controls_init' ) );
		add_action( 'customize_register', array( __CLASS__, 'schedule_customize_register' ), 1 );
		add_action( sprintf( 'wp_ajax_%s', self::UPDATE_WIDGET_AJAX_ACTION ), array( __CLASS__, 'wp_ajax_update_widget' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_render_widget_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render_widget' ) );
		add_action( 'customize_controls_enqueue_scripts', array( __CLASS__, 'customize_controls_enqueue_deps' ) );
		add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'output_widget_control_templates' ) );
		add_action( 'customize_preview_init', array( __CLASS__, 'customize_preview_init' ) );
		add_action( 'widgets_admin_page', array( __CLASS__, 'widget_customizer_link' ) );

		add_action( 'dynamic_sidebar', array( __CLASS__, 'tally_rendered_widgets' ) );
		add_action( 'dynamic_sidebar', array( __CLASS__, 'tally_sidebars_via_dynamic_sidebar_actions' ) );
		add_filter( 'temp_is_active_sidebar', array( __CLASS__, 'tally_sidebars_via_is_active_sidebar_calls' ), 10, 2 );
		add_filter( 'temp_dynamic_sidebar_has_widgets', array( __CLASS__, 'tally_sidebars_via_dynamic_sidebar_calls' ), 10, 2 );
	}

	static function load_textdomain() {
		$text_domain = self::get_plugin_meta( 'TextDomain' );
		$locale      = apply_filters( 'plugin_locale', get_locale(), $text_domain );
		$mo_file     = sprintf( '%s/%s/%s-%s.mo', WP_LANG_DIR, $text_domain, $text_domain, $locale );
		load_textdomain( $text_domain, $mo_file );
		$plugin_rel_path = dirname( plugin_basename( __FILE__ ) ) . trailingslashit( self::get_plugin_meta( 'DomainPath' ) );
		load_plugin_textdomain( $text_domain, false, $plugin_rel_path );
	}

	/**
	 * @param null|string meta key, if omitted all meta are returned
	 * @return array|mixed meta value(s)
	 */
	static function get_plugin_meta( $key = null ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$data = get_plugin_data( __FILE__ );
		if ( ! is_null( $key ) ) {
			return $data[$key];
		} else {
			return $data;
		}
	}

	/**
	 * @return string the plugin version
	 */
	static function get_version() {
		return self::get_plugin_meta( 'Version' );
	}

	protected static $_customized;
	protected static $_prepreview_added_filters = array();

	/**
	 * Do add_theme_support() for any built-in supported theme; other themes need to do this themselves
	 * @action after_setup_theme
	 */
	static function add_builtin_theme_support() {
		$is_builtin_supported = (
			isset( self::$builtin_supported_themes_with_scripts[ get_stylesheet() ] )
			||
			isset( self::$builtin_supported_themes_with_scripts[ get_template() ] )
		);
		if ( $is_builtin_supported ) {
			add_theme_support( 'widget-customizer' );
		}
	}

	/**
	 * Since the widgets get registered (widgets_init) before the customizer settings are set up (customize_register),
	 * we have to filter the options similarly to how the setting previewer will filter the options later.
	 * @action after_setup_theme
	 */
	static function setup_widget_addition_previews() {
		global $wp_customize;
		$is_customize_preview = (
			( ! empty( $wp_customize ) )
			&&
			( ! is_admin() )
			&&
			( 'on' === filter_input( INPUT_POST, 'wp_customize' ) )
			&&
			check_ajax_referer( 'preview-customize_' . $wp_customize->get_stylesheet(), 'nonce', false )
		);

		$is_ajax_widget_update = (
			( defined( 'DOING_AJAX' ) && DOING_AJAX )
			&&
			filter_input( INPUT_POST, 'action' ) === self::UPDATE_WIDGET_AJAX_ACTION
			&&
			check_ajax_referer( self::UPDATE_WIDGET_AJAX_ACTION, self::UPDATE_WIDGET_NONCE_POST_KEY, false )
		);

		$is_widget_render = (
			isset( $_POST[self::RENDER_WIDGET_QUERY_VAR] )
			&&
			filter_input( INPUT_POST, 'action' ) === self::RENDER_WIDGET_AJAX_ACTION
			&&
			check_ajax_referer( self::RENDER_WIDGET_AJAX_ACTION, self::RENDER_WIDGET_NONCE_POST_KEY, false )
		);

		$is_ajax_customize_save = (
			( defined( 'DOING_AJAX' ) && DOING_AJAX )
			&&
			filter_input( INPUT_POST, 'action' ) === 'customize_save'
			&&
			check_ajax_referer( 'save-customize_' . $wp_customize->get_stylesheet(), 'nonce' )
		);

		$is_valid_request = ( $is_ajax_widget_update || $is_widget_render || $is_customize_preview || $is_ajax_customize_save );
		if ( ! $is_valid_request ) {
			return;
		}

		// Input from customizer preview
		if ( isset( $_POST['customized'] ) ) {
			$customized = json_decode( wp_unslash( $_POST['customized'] ), true );
		}
		// Input from ajax widget update request
		else {
			$customized    = array();
			$id_base       = filter_input( INPUT_POST, 'id_base' );
			$widget_number = filter_input( INPUT_POST, 'widget_number', FILTER_VALIDATE_INT );
			$option_name   = 'widget_' . $id_base;
			$customized[$option_name] = array();
			if ( false !== $widget_number ) {
				$option_name .= '[' . $widget_number . ']';
				$customized[$option_name][$widget_number] = array();
			}
		}

		$function = array( __CLASS__, 'prepreview_added_sidebars_widgets' );

		$hook = 'option_sidebars_widgets';
		add_filter( $hook, $function );
		self::$_prepreview_added_filters[] = compact( 'hook', 'function' );

		$hook = 'default_option_sidebars_widgets';
		add_filter( $hook, $function );
		self::$_prepreview_added_filters[] = compact( 'hook', 'function' );

		foreach ( $customized as $setting_id => $value ) {
			if ( preg_match( '/^(widget_.+?)(\[(\d+)\])?$/', $setting_id, $matches ) ) {
				$body     = sprintf( 'return %s::prepreview_added_widget_instance( $value, %s );', __CLASS__, var_export( $setting_id, true ) );
				$function = create_function( '$value', $body );
				$option   = $matches[1];

				$hook = sprintf( 'option_%s', $option );
				add_filter( $hook, $function );
				self::$_prepreview_added_filters[] = compact( 'hook', 'function' );

				$hook = sprintf( 'default_option_%s', $option );
				add_filter( $hook, $function );
				self::$_prepreview_added_filters[] = compact( 'hook', 'function' );

				/**
				 * Make sure the option is registered so that the update_option won't fail due to
				 * the filters providing a default value, which causes the update_option() to get confused.
				 */
				add_option( $option, array() );
			}
		}

		self::$_customized = $customized;
	}

	/**
	 * Ensure that newly-added widgets will appear in the widgets_sidebars.
	 * This is necessary because the customizer's setting preview filters are added after the widgets_init action,
	 * which is too late for the widgets to be set up properly.
	 * @param array $sidebars_widgets
	 * @return array
	 */
	static function prepreview_added_sidebars_widgets( $sidebars_widgets ) {
		foreach ( self::$_customized as $setting_id => $value ) {
			if ( preg_match( '/^sidebars_widgets\[(.+?)\]$/', $setting_id, $matches ) ) {
				$sidebar_id = $matches[1];
				if ( ! isset( $sidebars_widgets[$sidebar_id] ) ) {
					$sidebars_widgets[$sidebar_id] = array();
				}
				$sidebars_widgets[$sidebar_id] = array_unique( array_merge( $value, $sidebars_widgets[$sidebar_id] ) );
			}
		}
		return $sidebars_widgets;
	}

	/**
	 * Ensure that newly-added widgets will have empty instances so that they will be recognized.
	 * This is necessary because the customizer's setting preview filters are added after the widgets_init action,
	 * which is too late for the widgets to be set up properly.
	 * @param array $instance
	 * @param string $setting_id
	 * @return array
	 */
	static function prepreview_added_widget_instance( $instance, $setting_id ) {
		if ( isset( self::$_customized[$setting_id] ) ) {
			assert( preg_match( '/^(widget_(.+?))(?:\[(\d+)\])?$/', $setting_id, $matches ) );
			$widget_number = isset( $matches[3] ) ? intval( $matches[3] ) : false;

			// Single widget
			if ( false === $widget_number ) {
				if ( false === $instance && empty( $value ) ) {
					$instance = array();
				}
			}
			// Multi widget
			else if ( false === $instance || ! isset( $instance[$widget_number] ) ) {
				if ( empty( $instance ) ) {
					$instance = array( '_multiwidget' => 1 );
				}
				if ( ! isset( $instance[$widget_number] ) ) {
					$instance[$widget_number] = array();
				}
			}
		}
		return $instance;
	}

	/**
	 * Remove filters added in setup_widget_addition_previews() which ensure that
	 * widgets are populating the options during widgets_init
	 * @action wp_loaded
	 */
	static function remove_prepreview_filters() {
		foreach ( self::$_prepreview_added_filters as $prepreview_added_filter ) {
			remove_filter( $prepreview_added_filter['hook'], $prepreview_added_filter['function'] );
		}
		self::$_prepreview_added_filters = array();
	}

	/**
	 * Make sure that all widgets get loaded into customizer; these actions are also done in the wp_ajax_save_widget()
	 * @see wp_ajax_save_widget()
	 * @action customize_controls_init
	 */
	static function customize_controls_init() {
		do_action( 'load-widgets.php' );
		do_action( 'widgets.php' );
		do_action( 'sidebar_admin_setup' );
	}

	/**
	 * @filter query_vars
	 */
	static function add_render_widget_query_var( $query_vars ) {
		if ( ! is_admin() ) {
			$query_vars[] = self::RENDER_WIDGET_QUERY_VAR;
		}
		return $query_vars;
	}

	/**
	 * When in preview, invoke customize_register for settings after WordPress is
	 * loaded so that all filters have been initialized (e.g. Widget Visibility)
	 */
	static function schedule_customize_register( $wp_customize ) {
		if ( is_admin() ) { // @todo for some reason, $wp_customize->is_preview() is true here?
			self::customize_register( $wp_customize );
		}
		else {
			add_action( 'wp', array( __CLASS__, 'customize_register' ) );
		}
	}

	static $sidebars_eligible_for_post_message = array();
	static $widgets_eligible_for_post_message  = array();

	/**
	 * @action customize_register
	 */
	static function customize_register( $wp_customize = null ) {
		global $wp_registered_widgets;
		if ( ! ( $wp_customize instanceof WP_Customize_Manager ) ) {
			$wp_customize = $GLOBALS['wp_customize'];
		}

		require_once( plugin_dir_path( __FILE__ ) . '/class-widget-form-wp-customize-control.php' );
		require_once( plugin_dir_path( __FILE__ ) . '/class-sidebar-widgets-wp-customize-control.php' );

		$sidebars_widgets = array_merge(
			array( 'wp_inactive_widgets' => array() ),
			array_fill_keys( array_keys( $GLOBALS['wp_registered_sidebars'] ), array() ),
			wp_get_sidebars_widgets()
		);

		$new_setting_ids = array();

		foreach ( $sidebars_widgets as $sidebar_id => $sidebar_widget_ids ) {
			if ( empty( $sidebar_widget_ids ) ) {
				$sidebar_widget_ids = array();
			}
			$is_registered_sidebar = isset( $GLOBALS['wp_registered_sidebars'][$sidebar_id] );
			$is_inactive_widgets   = ( 'wp_inactive_widgets' === $sidebar_id );
			$is_active_sidebar     = ( $is_registered_sidebar  && ! $is_inactive_widgets );

			/**
			 * Add setting for managing the sidebar's widgets
			 */
			if ( $is_registered_sidebar || $is_inactive_widgets ) {
				$setting_id   = sprintf( 'sidebars_widgets[%s]', $sidebar_id );
				$setting_args = self::get_setting_args( $setting_id );
				if ( $is_inactive_widgets ) {
					$setting_args['transport'] = 'postMessage'; // prevent refresh since not rendered anyway
				}
				else {
					self::$sidebars_eligible_for_post_message[$sidebar_id] = ( 'postMessage' === self::get_sidebar_widgets_setting_transport( $sidebar_id ) );
				}
				$wp_customize->add_setting( $setting_id, $setting_args );
				$new_setting_ids[] = $setting_id;

				/**
				 * Add section to contain controls
				 */
				$section_id = sprintf( 'sidebar-widgets-%s', $sidebar_id );
				if ( $is_active_sidebar ) {
					$section_args = array(
						'title' => sprintf( __( 'Sidebar: %s', 'widget-customizer' ), $GLOBALS['wp_registered_sidebars'][$sidebar_id]['name'] ),
						'description' => $GLOBALS['wp_registered_sidebars'][$sidebar_id]['description'],
					);
					$section_args = apply_filters( 'customizer_widgets_section_args', $section_args, $section_id, $sidebar_id );
					$wp_customize->add_section( $section_id, $section_args );

					$control = new Sidebar_Widgets_WP_Customize_Control(
						$wp_customize,
						$setting_id,
						array(
							'section' => $section_id,
							'sidebar_id' => $sidebar_id,
							'priority' => 10 - 1,
						)
					);
					$new_setting_ids[] = $setting_id;
					$wp_customize->add_control( $control );
				}
			}

			/**
			 * Add setting for each widget, and a control for each active widget (located in a sidebar)
			 */
			foreach ( $sidebar_widget_ids as $i => $widget_id ) {
				// Skip widgets that may have gone away due to a plugin being deactivated
				if ( ! isset( $GLOBALS['wp_registered_widgets'][$widget_id] ) ) {
					continue;
				}
				$registered_widget = $GLOBALS['wp_registered_widgets'][$widget_id];
				$setting_id = self::get_setting_id( $widget_id );
				$setting_args = self::get_setting_args( $setting_id );
				$id_base = $GLOBALS['wp_registered_widget_controls'][$widget_id]['id_base'];
				$wp_customize->add_setting( $setting_id, $setting_args );
				$new_setting_ids[] = $setting_id;

				/**
				 * Add control for widget if it is active
				 */
				if ( $is_active_sidebar ) {
					assert( false !== is_active_widget( $registered_widget['callback'], $registered_widget['id'], false, false ) );
					$control = new Widget_Form_WP_Customize_Control(
						$wp_customize,
						$setting_id,
						array(
							'label' => $registered_widget['name'],
							'section' => $section_id,
							'sidebar_id' => $sidebar_id,
							'widget_id' => $widget_id,
							'widget_id_base' => $id_base,
							'priority' => 10 + $i,
						)
					);
					$wp_customize->add_control( $control );
				}
			}
		}

		/**
		 * We have to register these settings later than customize_preview_init so that other
		 * filters have had a chance to run.
		 * @see self::schedule_customize_register()
		 */
		if ( did_action( 'customize_preview_init' ) ) {
			foreach ( $new_setting_ids as $new_setting_id ) {
				$wp_customize->get_setting( $new_setting_id )->preview();
			}
		}

		self::remove_prepreview_filters();
	}

	/**
	 * @param string $widget_id
	 * @return string
	 */
	static function get_setting_id( $widget_id ) {
		preg_match( '/^(.*?)(?:-([0-9]+))?$/', $widget_id, $matches ); // see private _get_widget_id_base()
		$setting_id = sprintf( 'widget_%s', $matches[1] );
		if ( isset( $matches[2] ) ) {
			$setting_id .= sprintf( '[%d]', $matches[2] );
		}
		return $setting_id;
	}

	/**
	 * @action customize_controls_enqueue_scripts
	 */
	static function customize_controls_enqueue_deps() {
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'jquery-ui-droppable' );
		wp_enqueue_style(
			'widget-customizer',
			self::get_plugin_path_url( 'widget-customizer.css' ),
			array(),
			self::get_version()
		);
		wp_enqueue_script(
			'widget-customizer',
			self::get_plugin_path_url( 'widget-customizer.js' ),
			array( 'jquery', 'backbone', 'wp-util', 'customize-controls' ),
			self::get_version(),
			true
		);

		// Export available widgets with control_tpl removed from model since plugins
		// (e.g. Jetpack Widget Visibility) need templates to be in the DOM
		$available_widgets = array();
		foreach ( self::get_available_widgets() as $available_widget ) {
			unset( $available_widget['control_tpl'] );
			$available_widgets[] = $available_widget;
			self::$widgets_eligible_for_post_message[$available_widget['id_base']] = ( 'postMessage' === self::get_widget_setting_transport( $available_widget['id_base'] ) );
		}

		// Why not wp_localize_script? Because we're not localizing, and it forces values into strings
		global $wp_scripts;
		$exports = array(
			'update_widget_ajax_action' => self::UPDATE_WIDGET_AJAX_ACTION,
			'update_widget_nonce_value' => wp_create_nonce( self::UPDATE_WIDGET_AJAX_ACTION ),
			'update_widget_nonce_post_key' => self::UPDATE_WIDGET_NONCE_POST_KEY,
			'registered_sidebars' => $GLOBALS['wp_registered_sidebars'],
			'i18n' => array(
				'save_btn_label' => _x( 'Update', 'button to save changes to a widget', 'widget-customizer' ),
				'save_btn_tooltip' => _x( 'Save and preview changes before publishing them.', 'tooltip on the widget save button', 'widget-customizer' ),
				'remove_btn_label' => _x( 'Remove', 'link to move a widget to the inactive widgets sidebar', 'widget-customizer' ),
				'remove_btn_tooltip' => _x( 'Trash widget by moving it to the inactive widgets sidebar.', 'tooltip on btn a widget to move it to the inactive widgets sidebar', 'widget-customizer' ),
			),
			'available_widgets' => $available_widgets,
			'sidebars_eligible_for_post_message' => self::$sidebars_eligible_for_post_message,
			'widgets_eligible_for_post_message' => self::$widgets_eligible_for_post_message,
			'current_theme_supports' => current_theme_supports( 'widget-customizer' ),
		);

		$wp_scripts->add_data(
			'widget-customizer',
			'data',
			sprintf( 'var WidgetCustomizer_exports = %s;', json_encode( $exports ) )
		);
	}

	/**
	 * Render the widget form control templates into the DOM so that plugin scripts can manipulate them
	 * @action customize_controls_print_footer_scripts
	 */
	static function output_widget_control_templates() {
		?>
		<div id="widget-customizer-control-templates" hidden>
			<?php foreach ( self::get_available_widgets() as $available_widget ): ?>
				<div id="widget-tpl-<?php echo esc_attr( $available_widget['id'] ) ?>" class="widget-tpl <?php echo esc_attr( $available_widget['id'] ) ?>">
					<?php echo $available_widget['control_tpl']; // xss ok ?>
				</div>
			<?php endforeach; ?>
		</div>
		<canvas id="focus-overlay" height="0" width="0"></canvas>
		<?php
	}

	/**
	 * @param string $id
	 * @param array  [$overrides]
	 * @return array
	 */
	static function get_setting_args( $id, $overrides = array() ) {
		$args = array(
			'type' => 'option',
			'capability' => 'edit_theme_options',
			'transport' => 'refresh',
			'default' => array(),
		);
		$args = array_merge( $args, $overrides );
		$args = apply_filters( 'widget_customizer_setting_args', $args, $id );
		return $args;
	}

	/**
	 * @param string $id_base
	 * @return string
	 */
	static function get_widget_setting_transport( $id_base ) {
		$live_previewable = false;
		if ( current_theme_supports( 'widget-customizer' ) && in_array( $id_base, self::$core_widget_base_ids ) ) {
			$live_previewable = true;
		}
		// Allow widgets to opt-in for postMessage
		$live_previewable = apply_filters( 'customizer_widget_live_previewable', $live_previewable, $id_base );
		$live_previewable = apply_filters( "customizer_widget_live_previewable_{$id_base}", $live_previewable );
		return $live_previewable ? 'postMessage' : 'refresh';
	}

	/**
	 * @param string $sidebar_id
	 * @return string
	 */
	static function get_sidebar_widgets_setting_transport( $sidebar_id ) {
		$live_previewable = false;
		if ( current_theme_supports( 'widget-customizer' ) ) {
			$live_previewable = true;
		}
		$live_previewable = apply_filters( 'customizer_sidebar_widgets_live_previewable', $live_previewable, $sidebar_id );
		$live_previewable = apply_filters( "customizer_sidebar_widgets_live_previewable_{$sidebar_id}", $live_previewable );
		return $live_previewable ? 'postMessage' : 'refresh';
	}

	/**
	 * @see wp_list_widgets()
	 * @return array
	 */
	static function get_available_widgets() {
		static $available_widgets = array();
		if ( ! empty( $available_widgets ) ) {
			return $available_widgets;
		}

		global $wp_registered_widgets, $wp_registered_widget_controls;
		require_once ABSPATH . '/wp-admin/includes/widgets.php'; // for next_widget_id_number()

		$sort = $wp_registered_widgets;
		usort( $sort, array( __CLASS__, '_sort_name_callback' ) );
		$done = array();

		foreach ( $sort as $widget ) {
			if ( in_array( $widget['callback'], $done, true ) ) { // We already showed this multi-widget
				continue;
			}

			$sidebar = is_active_widget( $widget['callback'], $widget['id'], false, false );
			$done[]  = $widget['callback'];

			if ( ! isset( $widget['params'][0] ) ) {
				$widget['params'][0] = array();
			}

			$available_widget = $widget;
			unset( $available_widget['callback'] ); // not serializable to JSON

			$args = array(
				'widget_id' => $widget['id'],
				'widget_name' => $widget['name'],
				'_display' => 'template',
			);

			$is_disabled     = false;
			$is_multi_widget = (
				isset( $wp_registered_widget_controls[$widget['id']]['id_base'] )
				&&
				isset( $widget['params'][0]['number'] )
			);
			if ( $is_multi_widget ) {
				$id_base = $wp_registered_widget_controls[$widget['id']]['id_base'];
				$args['_temp_id']   = "$id_base-__i__";
				$args['_multi_num'] = next_widget_id_number( $id_base );
				$args['_add']       = 'multi';
			}
			else {
				$args['_add'] = 'single';
				if ( $sidebar && 'wp_inactive_widgets' !== $sidebar ) {
					$is_disabled = true;
				}
				$id_base = $widget['id'];
			}

			$list_widget_controls_args = wp_list_widget_controls_dynamic_sidebar( array( 0 => $args, 1 => $widget['params'][0] ) );
			$control_tpl = self::get_widget_control( $list_widget_controls_args );

			// The properties here are mapped to the Backbone Widget model
			$available_widget = array_merge(
				$available_widget,
				array(
					'temp_id' => isset( $args['_temp_id'] ) ? $args['_temp_id'] : null,
					'is_multi' => $is_multi_widget,
					'control_tpl' => $control_tpl,
					'multi_number' => ( $args['_add'] === 'multi' ) ? $args['_multi_num'] : false,
					'is_disabled' => $is_disabled,
					'id_base' => $id_base,
					'transport' => self::get_widget_setting_transport( $id_base ),
				)
			);
			$available_widgets[] = $available_widget;
		}
		return $available_widgets;
	}

	/**
	 * Replace with inline closure once on PHP 5.3:
	 * sort( $array, function ( $a, $b ) { return strnatcasecmp( $a['name'], $b['name'] ); } );
	 * @access private
	 */
	static function _sort_name_callback( $a, $b ) {
		return strnatcasecmp( $a['name'], $b['name'] );
	}

	/**
	 * Invoke wp_widget_control() but capture the output buffer and transform the markup
	 * so that it can be used in the customizer.
	 *
	 * @see wp_widget_control()
	 * @param array $args
	 * @return string
	 */
	static function get_widget_control( $args ) {
		ob_start();
		call_user_func_array( 'wp_widget_control', $args );
		$replacements = array(
			'<form action="" method="post">' => '<div class="form">',
			'</form>' => '</div><!-- .form -->',
		);
		$control_tpl = ob_get_clean();
		$control_tpl = str_replace( array_keys( $replacements ), array_values( $replacements ), $control_tpl );
		return $control_tpl;
	}

	/**
	 * @action customize_preview_init
	 */
	static function customize_preview_init() {
		add_filter( 'sidebars_widgets', array( __CLASS__, 'preview_sidebars_widgets' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'customize_preview_enqueue_deps' ) );
		add_action( 'wp_footer', array( __CLASS__, 'export_preview_data' ), 9999 );
	}

	/**
	 * When previewing, make sure the proper previewing widgets are used. Because wp_get_sidebars_widgets()
	 * gets called early at init (via wp_convert_widget_settings()) and can set global variable
	 * $_wp_sidebars_widgets to the value of get_option( 'sidebars_widgets' ) before the customizer
	 * preview filter is added, we have to reset it after the filter has been added.
	 * @filter sidebars_widgets
	 */
	static function preview_sidebars_widgets( $sidebars_widgets ) {
		$sidebars_widgets = get_option( 'sidebars_widgets' );
		unset( $sidebars_widgets['array_version'] );
		return $sidebars_widgets;
	}

	/**
	 * @action wp_enqueue_scripts
	 */
	static function customize_preview_enqueue_deps() {
		global $wp_registered_widgets, $wp_registered_widget_controls;

		wp_enqueue_script(
			'widget-customizer-preview',
			self::get_plugin_path_url( 'widget-customizer-preview.js' ),
			array( 'jquery', 'wp-util', 'customize-preview' ),
			self::get_version(),
			true
		);
		wp_enqueue_style(
			'widget-customizer-preview',
			self::get_plugin_path_url( 'widget-customizer-preview.css' ),
			array(),
			self::get_version()
		);

		// Enqueue any scripts provided to add live preview support for buultin themes (e.g. twentythirteen)
		$applied_themes = array( get_template() );
		if ( get_stylesheet() !== get_template() ) {
			$applied_themes[] = get_stylesheet();
		}
		foreach ( $applied_themes as $applied_theme ) {
			if ( ! empty( self::$builtin_supported_themes_with_scripts[ $applied_theme ] ) ) {
				wp_enqueue_script(
					"widget-customizer-$applied_theme",
					self::get_plugin_path_url( "theme-support/$applied_theme.js" ),
					array( 'customize-preview' ),
					self::get_version(),
					true
				);
			}
		}

		$all_id_bases = array();
		foreach ( $wp_registered_widgets as $widget ) {
			if ( isset( $wp_registered_widget_controls[$widget['id']]['id_base'] ) ) {
				$all_id_bases[] = $wp_registered_widget_controls[$widget['id']]['id_base'];
			}
			else {
				$all_id_bases[] = $widget['id'];
			}
		}
		$all_id_bases = array_unique( $all_id_bases );
		foreach ( $all_id_bases as $id_base ) {
			self::$widgets_eligible_for_post_message[$id_base] = ( 'postMessage' === self::get_widget_setting_transport( $id_base ) );
		}

		// Why not wp_localize_script? Because we're not localizing, and it forces values into strings
		global $wp_scripts;
		$exports = array(
			'registered_sidebars' => $GLOBALS['wp_registered_sidebars'],
			'i18n' => array(
				'widget_tooltip' => __( 'Press shift and then click to edit widget in customizer...', 'widget-customizer' ),
			),
			'render_widget_ajax_action' => self::RENDER_WIDGET_AJAX_ACTION,
			'render_widget_nonce_value' => wp_create_nonce( self::RENDER_WIDGET_AJAX_ACTION ),
			'render_widget_nonce_post_key' => self::RENDER_WIDGET_NONCE_POST_KEY,
			'request_uri' => wp_unslash( $_SERVER['REQUEST_URI'] ),
			'sidebars_eligible_for_post_message' => self::$sidebars_eligible_for_post_message,
			'widgets_eligible_for_post_message' => self::$widgets_eligible_for_post_message,
			'current_theme_supports' => current_theme_supports( 'widget-customizer' ),
		);
		$wp_scripts->add_data(
			'widget-customizer-preview',
			'data',
			sprintf( 'var WidgetCustomizerPreview_exports = %s;', json_encode( $exports ) )
		);
	}

	/**
	 * At the very end of the page, at the very end of the wp_footer, communicate the sidebars that appeared on the page
	 */
	static function export_preview_data() {
		wp_print_scripts( array( 'widget-customizer-preview' ) );
		?>
		<script>
		(function () {
			/*global WidgetCustomizerPreview */
			WidgetCustomizerPreview.rendered_sidebars = <?php echo json_encode( array_unique( self::$rendered_sidebars ) ) ?>;
			WidgetCustomizerPreview.rendered_widgets = <?php echo json_encode( self::$rendered_widgets ); ?>;
		}());
		</script>
		<?php
	}

	static protected $rendered_sidebars = array();
	static protected $rendered_widgets  = array();

	/**
	 * Keep track of the widgets that were rendered
	 * @todo With this in place, do we even need to register the settings while in the customizer preview?
	 * @action dynamic_sidebar
	 */
	static function tally_rendered_widgets( $widget ) {
		$instance = array();
		if ( preg_match( '/^(.+?)-(\d+)$/', $widget['id'], $matches ) ) {
			$option_value = get_option( 'widget_' . $matches[1] );
			if ( ! empty( $option_value[$matches[2]] ) ) {
				$instance = $option_value[$matches[2]];
			}
		}
		else {
			$instance = get_option( 'widget_' . $widget['id'], array() );
		}
		self::$rendered_widgets[$widget['id']] = $instance;
	}

	/**
	 * This is hacky. It is too bad that dynamic_sidebar is not just called once with the $sidebar_id supplied
	 * This does not get called for a sidebar which lacks widgets.
	 * See core patch which addresses the problem:
	 * @link http://core.trac.wordpress.org/ticket/25368
	 * @action dynamic_sidebar
	 */
	static function tally_sidebars_via_dynamic_sidebar_actions( $widget ) {
		global $sidebars_widgets;
		foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {
			if ( in_array( $sidebar_id, self::$rendered_sidebars ) ) {
				continue;
			}
			if ( isset( $GLOBALS['wp_registered_sidebars'][$sidebar_id] ) && is_array( $widget_ids ) && in_array( $widget['id'], $widget_ids ) ) {
				self::$rendered_sidebars[] = $sidebar_id;
			}
		}
	}

	/**
	 * Keep track of the times that is_active_sidebar() is called in the template, and assume that this
	 * means that the sidebar would be rendered on the template if there were widgets populating it.
	 * @see http://core.trac.wordpress.org/ticket/25368
	 * @filter temp_is_active_sidebar
	 */
	static function tally_sidebars_via_is_active_sidebar_calls( $is_active, $sidebar_id ) {
		if ( isset( $GLOBALS['wp_registered_sidebars'][$sidebar_id] ) ) {
			self::$rendered_sidebars[] = $sidebar_id;
		}
		// We may need to force this to true, and also force-true the value for temp_dynamic_sidebar_has_widgets
		// if we want to ensure that there is an area to drop widgets into, if the sidebar is empty.
		return $is_active;
	}

	/**
	 * Keep track of the times that dynamic_sidebar() is called in the template, and assume that this
	 * means that the sidebar would be rendered on the template if there were widgets populating it.
	 * @see http://core.trac.wordpress.org/ticket/25368
	 * @filter temp_dynamic_sidebar_has_widgets
	 */
	static function tally_sidebars_via_dynamic_sidebar_calls( $has_widgets, $sidebar_id ) {
		if ( isset( $GLOBALS['wp_registered_sidebars'][$sidebar_id] ) ) {
			self::$rendered_sidebars[] = $sidebar_id;
		}
		// We may need to force this to true, and also force-true the value for temp_is_active_sidebar
		// if we want to ensure that there is an area to drop widgets into, if the sidebar is empty.
		return $has_widgets;
	}

	/**
	 * @see dynamic_sidebar()
	 * @action template_redirect
	 */
	static function render_widget() {
		if ( ! get_query_var( self::RENDER_WIDGET_QUERY_VAR ) ) {
			return;
		}

		global $wp_registered_widgets, $wp_registered_sidebars;
		require_once plugin_dir_path( __FILE__ ) . '/class-options-transaction.php';

		$generic_error = __( 'An error has occurred. Please reload the page and try again.', 'widget-customizer' );
		try {
			do_action( 'load-widgets.php' );
			do_action( 'widgets.php' );

			$options_transaction = new Options_Transaction();
			$options_transaction->start();
			if ( empty( $_POST['widget_id'] ) ) {
				throw new Widget_Customizer_Exception( __( 'Missing widget_id param', 'widget-customizer' ) );
			}
			if ( empty( $_POST['setting_id'] ) ) {
				throw new Widget_Customizer_Exception( __( 'Missing setting_id param', 'widget-customizer' ) );
			}
			if ( empty( $_POST[self::RENDER_WIDGET_NONCE_POST_KEY] ) ) {
				throw new Widget_Customizer_Exception( __( 'Missing nonce param', 'widget-customizer' ) );
			}
			if ( ! check_ajax_referer( self::RENDER_WIDGET_AJAX_ACTION, self::RENDER_WIDGET_NONCE_POST_KEY, false ) ) {
				throw new Widget_Customizer_Exception( __( 'Nonce check failed. Reload and try again?', 'widget-customizer' ) );
			}
			if ( ! current_user_can( 'edit_theme_options' ) ) {
				throw new Widget_Customizer_Exception( __( 'Current user cannot!', 'widget-customizer' ) );
			}
			$widget_id = wp_unslash( $_POST['widget_id'] );
			if ( ! isset( $wp_registered_widgets[$widget_id] ) ) {
				throw new Widget_Customizer_Exception( __( 'Unable to find registered widget', 'widget-customizer' ) );
			}
			$widget = $wp_registered_widgets[$widget_id];

			if ( empty( $_POST['instance'] ) ) {
				throw new Widget_Customizer_Exception( __( 'Missing instance', 'widget-customizer' ) );
			}
			$instance = json_decode( wp_unslash( $_POST['instance'] ), true );
			if ( is_null( $instance ) ) {
				throw new Widget_Customizer_Exception( __( 'JSON parse error', 'widget-customizer' ) );
			}

			$setting_id = wp_unslash( $_POST['setting_id'] );
			if ( ! preg_match( '/^(.+?)(?:\[(\d+)])?$/', $setting_id, $matches ) ) {
				throw new Widget_Customizer_Exception( __( 'Malformed setting', 'widget-customizer' ) );
			}
			$option_name   = $matches[1];
			$widget_number = ! empty( $matches[2] ) ? intval( $matches[2] ) : null;
			$option_value  = get_option( $option_name );
			if ( is_null( $widget_number ) ) {
				$option_value = $instance;
			}
			else {
				if ( ! is_array( $option_value ) ) {
					$option_value = array();
				}
				$option_value[$widget_number] = $instance;
			}
			update_option( $option_name, $option_value );

			$rendered_widget = null;
			$sidebar_id = is_active_widget( $widget['callback'], $widget['id'], false, false );

			if ( $sidebar_id ) {
				$sidebar = $wp_registered_sidebars[$sidebar_id];
				$params  = array_merge(
					array(
						array_merge(
							$sidebar,
							array(
								'widget_id' => $widget_id,
								'widget_name' => $widget['name'],
							)
						),
					),
					(array) $widget['params']
				);

				$callback = $widget['callback'];

				// @todo If the widget is not assigned to a sidebar (e.g. via Widget Visibility, we need to return nothing!

				// Substitute HTML id and class attributes into before_widget
				$classname_ = '';
				foreach ( (array) $widget['classname'] as $cn ) {
					if ( is_string( $cn ) ) {
						$classname_ .= '_' . $cn;
					}
					else if ( is_object( $cn ) ) {
						$classname_ .= '_' . get_class( $cn );
					}
				}
				$classname_ = ltrim( $classname_, '_' );

				$params[0]['before_widget'] = sprintf( $params[0]['before_widget'], $widget_id, $classname_ );
				$params = apply_filters( 'dynamic_sidebar_params', $params );

				// Render the widget
				ob_start();
				do_action( 'dynamic_sidebar', $widget );
				if ( is_callable( $callback ) ) {
					call_user_func_array( $callback, $params );
				}
				$rendered_widget = ob_get_clean();
			}
			$options_transaction->rollback();
			wp_send_json_success( compact( 'rendered_widget', 'sidebar_id' ) );
		}
		catch ( Exception $e ) {
			$options_transaction->rollback();
			if ( $e instanceof Widget_Customizer_Exception ) {
				$message = $e->getMessage();
			}
			else {
				error_log( sprintf( '%s in %s: %s', get_class( $e ), __FUNCTION__, $e->getMessage() ) );
				$message = $generic_error;
			}
			wp_send_json_error( compact( 'message' ) );
		}
	}

	/**
	 * Most code here copied from wp_ajax_save_widget()
	 * @see wp_ajax_save_widget
	 * @todo Reuse wp_ajax_save_widget now that we have option transactions?
	 * @action wp_ajax_update_widget
	 */
	static function wp_ajax_update_widget() {
		global $wp_registered_widget_controls, $wp_registered_widget_updates;
		require_once plugin_dir_path( __FILE__ ) . '/class-options-transaction.php';

		$generic_error = __( 'An error has occurred. Please reload the page and try again.', 'widget-customizer' );

		try {
			$options_transaction = new Options_Transaction();
			$options_transaction->start();
			if ( ! check_ajax_referer( self::UPDATE_WIDGET_AJAX_ACTION, self::UPDATE_WIDGET_NONCE_POST_KEY, false ) ) {
				throw new Widget_Customizer_Exception( __( 'Nonce check failed. Reload and try again?', 'widget-customizer' ) );
			}
			if ( ! current_user_can( 'edit_theme_options' ) ) {
				throw new Widget_Customizer_Exception( __( 'Current user cannot!', 'widget-customizer' ) );
			}
			if ( ! isset( $_POST['id_base'] ) ) {
				throw new Widget_Customizer_Exception( __( 'Incomplete request', 'widget-customizer' ) );
			}

			unset( $_POST[self::UPDATE_WIDGET_NONCE_POST_KEY], $_POST['action'] );

			do_action( 'load-widgets.php' );
			do_action( 'widgets.php' );
			do_action( 'sidebar_admin_setup' );

			$id_base       = filter_input( INPUT_POST, 'id_base' );
			$widget_id     = filter_input( INPUT_POST, 'widget-id' );
			$widget_number = filter_input( INPUT_POST, 'widget_number', FILTER_VALIDATE_INT );
			$multi_number  = filter_input( INPUT_POST, 'multi_number', FILTER_VALIDATE_INT );
			$option_name   = 'widget_' . $id_base;

			if ( isset( $_POST['widget-' . $id_base] ) && is_array( $_POST['widget-' . $id_base] ) && preg_match( '/__i__|%i%/', key( $_POST['widget-' . $id_base] ) ) ) {
				throw new Widget_Customizer_Exception( 'Cannot pass widget templates to create new instances; apply template vars in JS' );
			}

			/**
			 * Perform the widget update
			 */
			if ( isset( $_POST['json_instance_override'] ) ) {
				$instance_override = json_decode( filter_input( INPUT_POST, 'json_instance_override' ), true );
				$option = get_option( $option_name );
				if ( ! empty( $widget_number ) ) {
					$option[$widget_number] = $instance_override;
				}
				else {
					$option = $instance_override;
				}
				update_option( $option_name, $option );

				// Delete other $_POST fields to prevent old single widgets from obeying override
				$preserved_keys = array(
					'widget-id',
					'id_base',
					'widget-width',
					'widget-height',
					'widget_number',
					'multi_number',
					'add_new',
					'action',
				);
				foreach ( array_diff( array_keys( $_POST ), $preserved_keys ) as $deleted_key ) {
					unset( $_POST[$deleted_key] );
				}
			}
			else {
				foreach ( (array) $wp_registered_widget_updates as $name => $control ) {
					if ( $name === $id_base ) {
						if ( ! is_callable( $control['callback'] ) ) {
							continue;
						}
						ob_start();
						call_user_func_array( $control['callback'], $control['params'] );
						ob_end_clean();
						break;
					}
				}
			}

			/**
			 * Make sure the expected option was updated
			 */
			if ( 0 !== $options_transaction->count() ) {
				if ( count( $options_transaction->options ) > 1 ) {
					throw new Widget_Customizer_Exception( 'Widget unexpectedly updated more than one option.' );
				}
				if ( key( $options_transaction->options ) !== $option_name ) {
					throw new Widget_Customizer_Exception( 'Widget updated unexpected option.' );
				}
			}

			/**
			 * Obtain the widget control
			 */
			ob_start();
			$form = $wp_registered_widget_controls[$widget_id];
			if ( $form ) {
				call_user_func_array( $form['callback'], $form['params'] );
			}
			$form = ob_get_clean();

			/**
			 * Obtain the widget instance
			 */
			$option = get_option( $option_name );
			if ( $widget_number ) {
				$instance = $option[$widget_number];
			}
			else {
				$instance = $option;
			}

			$options_transaction->rollback();
			wp_send_json_success( compact( 'form', 'instance' ) );
		}
		catch( Exception $e ) {
			$options_transaction->rollback();
			if ( $e instanceof Widget_Customizer_Exception ) {
				$message = $e->getMessage();
			}
			else {
				error_log( sprintf( '%s in %s: %s', get_class( $e ), __FUNCTION__, $e->getMessage() ) );
				$message = $generic_error;
			}
			wp_send_json_error( compact( 'message' ) );
		}
	}

	/**
	 * Gets Plugin URL from a path
	 * Not using plugin_dir_url because it is not symlink-friendly
	 */
	static function get_plugin_path_url( $path = null ) {
		$plugin_dirname = basename( dirname( __FILE__ ) );
		$base_dir = trailingslashit( plugin_dir_url( '' ) ) . $plugin_dirname;
		if ( $path ) {
			return trailingslashit( $base_dir ) . ltrim( $path, '/' );
		} else {
			return $base_dir;
		}
	}

	/**
	 * Adds Message to Widgets Admin Page to guide user to Widget Customizer
	 * @action widgets_admin_page
	 */
	static function widget_customizer_link() {
		?>
		<div class="updated">
			<p>
				<?php
				echo sprintf(
					__( 'The Widget Customizer plugin is activated. You can now edit and preview changes to widgets in the %1$s.', 'widget-customizer' ),
					sprintf(
						'<a href="%1$s">%2$s</a>',
						admin_url( 'customize.php' ),
						esc_html__( 'Customizer', 'widget-customizer' )
					)
				); // xss ok
				?>
			</p>
		</div>
		<?php
	}

}

class Widget_Customizer_Exception extends Exception {}

add_action( 'plugins_loaded', array( 'Widget_Customizer', 'setup' ) );
