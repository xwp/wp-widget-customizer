<?php

/**
 * Represent the widgets added to the sidebar and their order
 */
class Sidebar_Widgets_WP_Customize_Control extends WP_Customize_Control {
	public $type = 'sidebar_widgets';
	public $sidebar_id;

	public function to_json() {
		parent::to_json();
		$exported_properties = array( 'sidebar_id' );
		foreach ( $exported_properties as $key ) {
			$this->json[$key] = $this->$key;
		}
	}

	public function render_content() {
		?>
		<label>
			<span class="customize-control-title"><?php esc_html_e( 'Add widget:', 'widget-customizer' ); ?></span>
		</label>
		<select class="widefat available-widgets">
			<option disabled><?php esc_html_e( 'Add widget...', 'widget-customizer' ) ?></option>
		</select>
		<?php
	}
}
