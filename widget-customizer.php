<?php
/**
 * Plugin Name: Widget Customizer
 * Description: Edit widgets and preview changes in Theme Customizer, with a control for each widget form in sections added for each sidebar rendered in the preview.
 * Version:     0.8
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
	const UPDATE_WIDGET_NONCE_POST_KEY = 'update-sidebar-widgets-nonce';

	static function setup() {
		self::load_textdomain();
		add_action( 'customize_register', array( __CLASS__, 'customize_register' ) );
		add_action( sprintf( 'wp_ajax_%s', self::UPDATE_WIDGET_AJAX_ACTION ), array( __CLASS__, 'wp_ajax_update_widget' ) );
		add_action( 'customize_controls_enqueue_scripts', array( __CLASS__, 'customize_controls_enqueue_deps' ) );
		add_action( 'customize_preview_init', array( __CLASS__, 'customize_preview_init' ) );
		add_action( 'widgets_admin_page', array( __CLASS__, 'widget_customizer_link' ) );

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

	/**
	 * @action customize_register
	 */
	static function customize_register( $wp_customize ) {
		require_once( plugin_dir_path( __FILE__ ) . '/class-widget-form-wp-customize-control.php' );
		require_once( plugin_dir_path( __FILE__ ) . '/class-sidebar-widgets-wp-customize-control.php' );

		add_action( 'update_option_sidebars_widgets', array( __CLASS__, 'refresh_trashed_widgets' ) );

		foreach ( wp_get_sidebars_widgets() as $sidebar_id => $sidebar_widget_ids ) {
			if ( empty( $sidebar_widget_ids ) ) {
				$sidebar_widget_ids = array();
			}
			$is_active_sidebar = ( isset( $GLOBALS['wp_registered_sidebars'][$sidebar_id] ) && 'wp_inactive_widgets' !== $sidebar_id );

			/**
			 * Add section to contain controls
			 */
			$section_id = sprintf( 'sidebar-widgets-%s', $sidebar_id );
			if ( $is_active_sidebar ) {
				$section_args = array(
					'title' => sprintf(
						__( 'Sidebar: %s', 'widget-customizer' ),
						$GLOBALS['wp_registered_sidebars'][$sidebar_id]['name']
					),
					'description' => $GLOBALS['wp_registered_sidebars'][$sidebar_id]['description'],
				);
				$section_args = apply_filters( 'customizer_widgets_section_args', $section_args, $section_id, $sidebar_id );
				$wp_customize->add_section( $section_id, $section_args );

				/**
				 * Add setting for managing the sidebar's widgets
				 */
				$setting_id = sprintf( 'sidebars_widgets[%s]', $sidebar_id );
				$wp_customize->add_setting( $setting_id, self::get_setting_args( $setting_id ) );
				$control = new Sidebar_Widgets_WP_Customize_Control(
					$wp_customize,
					$setting_id,
					array(
						'section' => $section_id,
						'sidebar_id' => $sidebar_id,
						'priority' => 10 - 1,
					)
				);
				$wp_customize->add_control( $control );
			}

			/**
			 * Add setting for each widget, and a control for each active widget (located in a sidebar)
			 */
			foreach ( $sidebar_widget_ids as $i => $widget_id ) {
				assert( isset( $GLOBALS['wp_registered_widgets'][$widget_id] ) );

				preg_match( '/^(.*)-([0-9]+)$/', $widget_id, $matches ); // see private _get_widget_id_base()
				$registered_widget = $GLOBALS['wp_registered_widgets'][$widget_id];

				$setting_id = sprintf( 'widget_%s[%s]', $matches[1], $matches[2] );
				$wp_customize->add_setting( $setting_id, self::get_setting_args( $setting_id ) );

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
							'priority' => 10 + $i,
						)
					);
					$wp_customize->add_control( $control );
				}
			}
		}
	}

	/**
	 *
	 * @action update_option_sidebars_widgets
	 */
	static function refresh_trashed_widgets() {
		global $sidebars_widgets;
		$sidebars_widgets = wp_get_sidebars_widgets(); // Update global for retrieve_widgets()
		retrieve_widgets();
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
			array( 'jquery', 'backbone', 'customize-controls' ),
			self::get_version(),
			true
		);


		// Why not wp_localize_script? Because we're not localizing, and it forces values into strings
		global $wp_scripts;
		$exports = array(
			'update_widget_ajax_action' => self::UPDATE_WIDGET_AJAX_ACTION,
			'update_widget_nonce_value' => wp_create_nonce( self::UPDATE_WIDGET_AJAX_ACTION ),
			'update_widget_nonce_post_key' => self::UPDATE_WIDGET_NONCE_POST_KEY,
			'registered_sidebars' => $GLOBALS['wp_registered_sidebars'],
			'i18n' => array(
				'remove_btn_label' => _x( 'Remove', 'link to move a widget to the inactive widgets sidebar', 'widget-customzier' ),
				'remove_btn_tooltip' => _x( 'Trash widget by moving it to the inactive widgets sidebar', 'tooltip on btn a widget to the inactive widgets sidebar', 'widget-customzier' ),
			),
			'available_widgets' => self::get_available_widgets(),
		);

		$wp_scripts->add_data(
			'widget-customizer',
			'data',
			sprintf( 'var WidgetCustomizer_exports = %s;', json_encode( $exports ) )
		);
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
		);
		$args = array_merge( $args, $overrides );
		$args = apply_filters( 'widget_customizer_setting_args', $args, $id );
		return $args;
	}

	/**
	 * @see wp_list_widgets()
	 * @return array
	 */
	static function get_available_widgets() {
		global $wp_registered_widgets, $wp_registered_widget_controls;
		require_once ABSPATH . '/wp-admin/includes/widgets.php'; // for next_widget_id_number()

		$available_widgets = array();

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

			$is_multi_widget = (
				isset( $wp_registered_widget_controls[$widget['id']]['id_base'] )
				&&
				isset( $widget['params'][0]['number'] )
			);
			$is_hidden = false;
			if ( $is_multi_widget ) {
				$id_base = $wp_registered_widget_controls[$widget['id']]['id_base'];
				$args['_temp_id']   = "$id_base-__i__";
				$args['_multi_num'] = next_widget_id_number( $id_base );
				$args['_add']       = 'multi';
			}
			else {
				$args['_add'] = 'single';
				if ( $sidebar ) {
					$is_hidden = true;
				}
			}

			$list_widget_controls_args = wp_list_widget_controls_dynamic_sidebar( array( 0 => $args, 1 => $widget['params'][0] ) );
			ob_start();
			call_user_func_array( 'wp_widget_control', $list_widget_controls_args );
			$control_tpl = ob_get_clean();

			// The properties here are mapped to the Backbone Widget model
			$available_widget = array_merge(
				$available_widget,
				array(
					'temp_id' => isset( $args['_temp_id'] ) ? $args['_temp_id'] : null,
					'is_multi' => $is_multi_widget,
					'control_tpl' => $control_tpl,
					'multi_number' => ( $args['_add'] === 'multi' ) ? $args['_multi_num'] : false,
					'is_hidden' => $is_hidden,
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
		return $sidebars_widgets;
	}

	/**
	 * @action wp_enqueue_scripts
	 */
	static function customize_preview_enqueue_deps() {
		wp_enqueue_script(
			'widget-customizer-preview',
			self::get_plugin_path_url( 'widget-customizer-preview.js' ),
			array( 'jquery', 'customize-preview' ),
			self::get_version(),
			true
		);
		wp_enqueue_style(
			'widget-customizer-preview',
			self::get_plugin_path_url( 'widget-customizer-preview.css' ),
			array(),
			self::get_version()
		);

		// Why not wp_localize_script? Because we're not localizing, and it forces values into strings
		global $wp_scripts;
		$exports = array(
			'registered_sidebars' => $GLOBALS['wp_registered_sidebars'],
			'i18n' => array(
				'widget_tooltip' => __( 'Edit widget in customizer...', 'widget-customizer' ),
			),
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
		}());
		</script>
		<?php
	}

	static protected $rendered_sidebars = array();

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
			if ( is_array( $widget_ids ) && in_array( $widget['id'], $widget_ids ) ) {
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
		self::$rendered_sidebars[] = $sidebar_id;
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
		self::$rendered_sidebars[] = $sidebar_id;
		// We may need to force this to true, and also force-true the value for temp_is_active_sidebar
		// if we want to ensure that there is an area to drop widgets into, if the sidebar is empty.
		return $has_widgets;
	}

	/**
	 * Most code here copied from wp_ajax_save_widget()
	 * @see wp_ajax_save_widget
	 * @action wp_ajax_update_widget
	 */
	static function wp_ajax_update_widget() {
		global $wp_registered_widget_controls, $wp_registered_widget_updates;

		$generic_error = __( 'An error has occurred. Please reload the page and try again.', 'widget-customizer' );

		try {
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

			$id_base      = $_POST['id_base'];
			$widget_id    = $_POST['widget-id'];
			$multi_number = ! empty( $_POST['multi_number'] ) ? (int) $_POST['multi_number'] : 0;
			$settings     = isset( $_POST['widget-' . $id_base] ) && is_array( $_POST['widget-' . $id_base] ) ? $_POST['widget-' . $id_base] : false;

			// Incoming is a new widget
			if ( $settings && preg_match( '/__i__|%i%/', key( $settings ) ) ) {
				if ( ! $multi_number ) {
					throw new Widget_Customizer_Exception( $generic_error );
				}

				$_POST['widget-' . $id_base] = array( $multi_number => array_shift( $settings ) );
				$widget_id = $id_base . '-' . $multi_number;
			}

			foreach ( (array) $wp_registered_widget_updates as $name => $control ) {

				if ( $name === $id_base ) {

					if ( ! is_callable( $control['callback'] ) ) {
						break;
					}

					$widget_obj    = $control['callback'][0]; // @todo There must be a better way to obtain the widget object
					$all_instances = $widget_obj->get_settings();

					$settings = array();
					if ( isset( $_POST['widget-' . $widget_obj->id_base] ) && is_array( $_POST['widget-' . $widget_obj->id_base] ) ) {
						$settings = $_POST['widget-' . $widget_obj->id_base];
					} elseif ( isset($_POST['id_base']) && $_POST['id_base'] == $widget_obj->id_base ) {
						$num = $_POST['multi_number'] ? (int) $_POST['multi_number'] : (int) $_POST['widget_number'];
						$settings = array( $num => array() );
					}

					foreach ( $settings as $number => $new_instance ) {
						if ( ! empty( $_POST['json_instance_override'] ) ) {
							$new_instance = json_decode( stripslashes( $_POST['json_instance_override'] ), true );
						}
						else {
							$new_instance = stripslashes_deep( $new_instance );
						}
						$widget_obj->_set( $number );

						$old_instance = isset($all_instances[$number]) ? $all_instances[$number] : array();

						$instance = $widget_obj->update( $new_instance, $old_instance );

						// filters the widget's settings before saving, return false to cancel saving (keep the old settings if updating)
						$instance = apply_filters( 'widget_update_callback', $instance, $new_instance, $old_instance, $widget_obj );
						if ( false !== $instance ) {
							$all_instances[$number] = $instance;
						}

						break; // run only once
					}
					break;
				}
			}

			if ( ! empty( $_POST['add_new'] ) ) {
				// @todo Do we need to bail?
			}

			/**
			 * Invoke the form callback with the previewed instance supplied (closures would be nice here!)
			 */
			ob_start();
			if ( $form = $wp_registered_widget_controls[$widget_id] ) {
				self::$_current_widget_instance = $instance;
				$filter = array( __CLASS__, '_widget_form_callback' );;
				add_filter( 'widget_form_callback', $filter, 1 );

				call_user_func_array( $form['callback'], $form['params'] );

				remove_filter( 'widget_form_callback', $filter, 1 );
			}
			$form = ob_get_clean();

			wp_send_json_success( compact( 'form', 'instance' ) );
		}
		catch( Exception $e ) {
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
	 * @filter widget_form_callback
	 * @todo Once PHP 5.3 is the minimum requirement, we can use a delicious closure for this ugliness
	 */
	static function _widget_form_callback( $instance ) {
		$instance = self::$_current_widget_instance;
		return $instance;
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

	static protected $_current_widget_instance;

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
