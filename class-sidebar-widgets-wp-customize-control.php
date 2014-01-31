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
		<span class="reorder-toggle" tabindex="0">
			<span class="reorder"><?php esc_html_e( 'Reorder', 'widget-customizer' ) ?></span>
			<span class="reorder-done"><?php esc_html_e( 'Done', 'widget-customizer' ) ?></span>
		</span>
		<span class="add-new-widget" tabindex="0">
			<b><?php esc_html_e( 'Add a Widget', 'widget-customizer' ) ?></b>
		</span>
		<?php
	}
}
