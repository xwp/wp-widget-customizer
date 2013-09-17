<?php
/**
 * Plugin Name: Widget Customizer
 * Description: Edit sidebar widgets in the Theme Customizer. Adds a section for each sidebar and a control for each widget.
 * Version:     0.4
 * Author:      X-Team, Weston Ruter
 * Author URI:  http://x-team.com/
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
	const AJAX_ACTION = 'update_widget';
	const NONCE_POST_KEY = 'update-sidebar-widgets-nonce';

	static function setup() {
		self::load_textdomain();
		add_action( 'customize_register', array( __CLASS__, 'customize_register' ) );
		add_action( sprintf( 'wp_ajax_%s', self::AJAX_ACTION ), array( __CLASS__, 'wp_ajax_update_widget' ) );
		add_action( 'customize_controls_enqueue_scripts', array( __CLASS__, 'customize_controls_enqueue_deps' ) );
		add_action( 'customize_preview_init', array( __CLASS__, 'customize_preview_init' ) );
		add_action( 'dynamic_sidebar', array( __CLASS__, 'tally_rendered_sidebars' ) );
	}

	static function load_textdomain() {
		$text_domain = self::get_plugin_meta( 'TextDomain' );
		$locale  = apply_filters( 'plugin_locale', get_locale(), $text_domain );
		$mo_file = sprintf( '%s/%s/%s-%s.mo', WP_LANG_DIR, $text_domain, $text_domain, $locale );
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
		foreach ( $GLOBALS['sidebars_widgets'] as $sidebar_id => $widgets ) {
			$skip = (
				'wp_inactive_widgets' === $sidebar_id
				||
				empty( $widgets )
				||
				! isset( $GLOBALS['wp_registered_sidebars'][$sidebar_id] )
			);
			if ( $skip ) {
				continue;
			}
			$sidebar = $GLOBALS['wp_registered_sidebars'][$sidebar_id];
			$section_id = sprintf( 'sidebar-widgets-%s', $sidebar_id );
			$section_args = array(
				'title' => sprintf(
					__( 'Sidebar: %s', 'widget-customizer' ),
					$GLOBALS['wp_registered_sidebars'][$sidebar_id]['name']
				),
				'description' => $sidebar['description'],
			);
			$section_args = apply_filters( 'customizer_widgets_section_args', $section_args, $section_id, $sidebar_id );
			$wp_customize->add_section( $section_id, $section_args );

			foreach ( $widgets as $i => $widget_id ) {
				// Skip widgets persisting in DB which have been deactivated in code
				if ( ! isset( $GLOBALS['wp_registered_widgets'][$widget_id] ) ) {
					continue;
				}

				preg_match( '/^(.*)-([0-9]+)$/', $widget_id, $matches ); // see private _get_widget_id_base()
				$setting_id = sprintf( 'widget_%s[%s]', $matches[1], $matches[2] );
				$registered_widget = $GLOBALS['wp_registered_widgets'][$widget_id];

				$wp_customize->add_setting(
					$setting_id,
					array(
						'type' => 'option',
						'capability' => 'edit_theme_options',
						'transport' => 'refresh',
						// @todo add support for postMessage for some widgets; will need to use Ajax
					)
				);
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

	/**
	 * @action customize_controls_enqueue_scripts
	 */
	static function customize_controls_enqueue_deps() {
		wp_enqueue_style(
			'widget-customizer',
			plugin_dir_url( __FILE__ ) . 'widget-customizer.css',
			array(),
			self::get_version()
		);
		wp_enqueue_script(
			'widget-customizer',
			plugin_dir_url( __FILE__) . 'widget-customizer.js',
			array( 'jquery', 'customize-controls' ),
			self::get_version(),
			true
		);
		wp_localize_script(
			'widget-customizer',
			'WidgetCustomizer_exports',
			array(
				'ajax_action' => self::AJAX_ACTION,
				'nonce_value' => wp_create_nonce( self::AJAX_ACTION ),
				'nonce_post_key' => self::NONCE_POST_KEY,
			)
		);
	}

	static function customize_preview_init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'customize_preview_enqueue_deps' ) );
		add_action( 'wp_footer', array( __CLASS__, 'export_preview_data' ) );
	}

	/**
	 * @access wp_enqueue_scripts
	 */
	static function customize_preview_enqueue_deps() {
		wp_enqueue_script(
			'widget-customizer-preview',
			plugin_dir_url( __FILE__) . 'widget-customizer-preview.js',
			array( 'jquery', 'customize-preview' ),
			self::get_version(),
			true
		);
	}

	static function export_preview_data() {
		wp_print_scripts( array( 'widget-customizer-preview' ) );
		?>
		<script>
		(function () {
		/*global WidgetCustomizerPreview */
		WidgetCustomizerPreview.rendered_sidebars = <?php echo json_encode( self::$rendered_sidebars ) ?>;
		}());
		</script>
		<?php
	}

	static protected $rendered_sidebars = array();

	/**
	 * @action dynamic_sidebar
	 * @todo This is hacky. It is too bad that dynamic_sidebar is not just called once with the $sidebar_id supplied
	 */
	static function tally_rendered_sidebars( $widget ) {
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
	 * Most code here copied from wp_ajax_save_widget()
	 * @see wp_ajax_save_widget
	 * @action wp_ajax_update_widget
	 */
	static function wp_ajax_update_widget() {
		global $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_widget_updates;

		$generic_error = __( 'An error has occurred. Please reload the page and try again.', 'widget-customizer' );

		try {
			if ( ! check_ajax_referer( self::AJAX_ACTION, self::NONCE_POST_KEY, false ) ) {
				throw new Widget_Customizer_Exception( __( 'Nonce check failed. Reload and try again?', 'widget-customizer' ) );
			}
			if ( ! current_user_can('edit_theme_options') ) {
				throw new Widget_Customizer_Exception( __( 'Current user cannot!', 'widget-customizer' ) );
			}
			if ( ! isset( $_POST['id_base'] ) ) {
				throw new Widget_Customizer_Exception( __( 'Incomplete request', 'widget-customizer' ) );
			}

			unset( $_POST[self::NONCE_POST_KEY], $_POST['action'] );

			do_action('load-widgets.php');
			do_action('widgets.php');
			do_action('sidebar_admin_setup');

			$id_base = $_POST['id_base'];
			$widget_id = $_POST['widget-id'];
			$sidebar_id = $_POST['sidebar'];
			$multi_number = !empty($_POST['multi_number']) ? (int) $_POST['multi_number'] : 0;
			$settings = isset($_POST['widget-' . $id_base]) && is_array($_POST['widget-' . $id_base]) ? $_POST['widget-' . $id_base] : false;

			$sidebars = wp_get_sidebars_widgets();
			$sidebar = isset($sidebars[$sidebar_id]) ? $sidebars[$sidebar_id] : array();

			if ( $settings && preg_match( '/__i__|%i%/', key($settings) ) ) {
				if ( !$multi_number ) {
					throw new Widget_Customizer_Exception( $generic_error );
				}

				$_POST['widget-' . $id_base] = array( $multi_number => array_shift($settings) );
				$widget_id = $id_base . '-' . $multi_number;
				$sidebar[] = $widget_id;
			}
			$_POST['widget-id'] = $sidebar;

			foreach ( (array) $wp_registered_widget_updates as $name => $control ) {

				if ( $name === $id_base ) {

					if ( ! is_callable( $control['callback'] ) ) {
						break;
					}

					$widget_obj = $control['callback'][0]; // @todo There must be a better way to obtain the widget object
					$all_instances = $widget_obj->get_settings();
					$sidebars_widgets = wp_get_sidebars_widgets();

					$settings = array();
					if ( isset($_POST['widget-' . $widget_obj->id_base]) && is_array($_POST['widget-' . $widget_obj->id_base]) ) {
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
						$widget_obj->_set($number);

						$old_instance = isset($all_instances[$number]) ? $all_instances[$number] : array();

						$instance = $widget_obj->update($new_instance, $old_instance);

						// filters the widget's settings before saving, return false to cancel saving (keep the old settings if updating)
						$instance = apply_filters('widget_update_callback', $instance, $new_instance, $old_instance, $widget_obj);
						if ( false !== $instance ) {
							$all_instances[$number] = $instance;
						}

						break; // run only once
					}
					break;
				}
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

	static protected $_current_widget_instance;
}

class Widget_Customizer_Exception extends Exception {}

add_action( 'plugins_loaded', array( 'Widget_Customizer', 'setup' ) );
