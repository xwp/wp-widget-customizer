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
		global $wp_widget_factory;

		$id    = 'customize-control-' . str_replace( '[', '-', str_replace( ']', '', $this->id ) );
		$class = 'customize-control customize-control-' . $this->type;

		?>
		<li hidden id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>">
			<!-- @todo https://github.com/x-team/wp-widget-customizer/issues/3
			<label>
				<span class="customize-control-title"><?php echo esc_html_e( 'Add widget:', 'widget-customizer' ); ?></span>
				<select>
					<option></option>
					<?php foreach ( $wp_widget_factory->widgets as $class_name => $widget ): ?>
						<option value="<?php echo esc_attr( $class_name ) ?>"><?php echo esc_html( $widget->name ) ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			-->
		</li>

		<?php
	}
}
