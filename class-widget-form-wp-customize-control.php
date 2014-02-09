<?php

/**
 * Embed a widget form in a customize control
 */
class Widget_Form_WP_Customize_Control extends WP_Customize_Control {
	public $type = 'widget_form';
	public $widget_id;
	public $widget_id_base;
	public $sidebar_id;
	public $is_new = false;
	public $width;
	public $height;
	public $is_wide = false;
	public $is_live_previewable = false;

	public function to_json() {
		parent::to_json();
		$exported_properties = array( 'widget_id', 'widget_id_base', 'sidebar_id', 'width', 'height', 'is_wide', 'is_live_previewable' );
		foreach ( $exported_properties as $key ) {
			$this->json[$key] = $this->$key;
		}
	}

	public function render_content() {
		global $wp_registered_widgets;
		require_once ABSPATH . '/wp-admin/includes/widgets.php';

		$widget = $wp_registered_widgets[$this->widget_id];
		if ( ! isset( $widget['params'][0] ) ) {
			$widget['params'][0] = array();
		}

		$args = array(
			'widget_id' => $widget['id'],
			'widget_name' => $widget['name'],
		);
		$args = wp_list_widget_controls_dynamic_sidebar( array( 0 => $args, 1 => $widget['params'][0] ) );
		$control = Widget_Customizer::get_widget_control( $args );
		echo $control; // xss ok
	}
}
