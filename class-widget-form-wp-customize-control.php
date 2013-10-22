<?php

/**
 * Embed a widget form in a customize control
 */
class Widget_Form_WP_Customize_Control extends WP_Customize_Control {
	public $type = 'widget_form';
	public $widget_id;
	public $sidebar_id;

	public function to_json() {
		parent::to_json();
		$exported_properties = array( 'widget_id', 'sidebar_id' );
		foreach ( $exported_properties as $key ) {
			$this->json[$key] = $this->$key;
		}
	}

	public function render_content() {
		global $wp_registered_widgets, $wp_registered_widget_controls;
		require_once ABSPATH . '/wp-admin/includes/widgets.php';

		$widget = $wp_registered_widgets[$this->widget_id];
		if ( ! isset( $widget['params'][0] ) ) {
			$widget['params'][0] = array();
		}

		$sidebar = is_active_widget( $widget['callback'], $widget['id'], false, false );

		$args = array(
			'widget_id' => $widget['id'],
			'widget_name' => $widget['name'],
		);

		if ( isset($wp_registered_widget_controls[$widget['id']]['id_base']) && isset($widget['params'][0]['number']) ) {
			$id_base = $wp_registered_widget_controls[$widget['id']]['id_base'];
			$args['_temp_id']   = "$id_base-__i__";
			$args['_multi_num'] = next_widget_id_number( $id_base );
		}

		$args = wp_list_widget_controls_dynamic_sidebar( array( 0 => $args, 1 => $widget['params'][0] ) );
		call_user_func_array( 'wp_widget_control', $args );
	}
}
