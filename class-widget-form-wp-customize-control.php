<?php

/**
 * Embed a widget form in a customize control
 */
class Widget_Form_WP_Customize_Control extends WP_Customize_Control {
	public $type = 'widget_form';
	public $widget_id;
	public $sidebar_id;

	public function render_content() {
		global $wp_widget_factory, $wp_registered_widget_controls, $wp_registered_widgets;
		$instance = $this->value();

		// @todo There must be a better way to determine the class for a given widget
		// @todo do we even need to know the class name if we have $wp_registered_widget_controls?
		$registered_widget = $wp_registered_widgets[$this->widget_id];
		$widget_class = get_class( $registered_widget['callback'][0] );
		$widget_obj = $wp_widget_factory->widgets[$widget_class];

		$control = isset($wp_registered_widget_controls[$this->widget_id]) ? $wp_registered_widget_controls[$this->widget_id] : array();
		$widget_number = isset($control['params'][0]['number']) ? $control['params'][0]['number'] : '';
		$id_base = isset($control['id_base']) ? $control['id_base'] : $this->widget_id;
		$multi_number = isset($sidebar_args['_multi_num']) ? $sidebar_args['_multi_num'] : '';
		$add_new = '';
		?>
		<label>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		</label>
		<div class="customize-control-content">
			<fieldset class="widget-content">
			<?php
			if ( isset( $control['callback'] ) ) {
				$has_form = call_user_func_array( $control['callback'], $control['params'] );
			}
			else {
				echo "\t\t<p>" . __( 'There are no options for this widget.', 'widget-customizer' ) . "</p>\n";
			}
			?>
			</fieldset>
			<input type="hidden" name="widget-id" class="widget-id" value="<?php echo esc_attr( $this->widget_id ); ?>" />
			<input type="hidden" name="id_base" class="id_base" value="<?php echo esc_attr($id_base); ?>" />
			<input type="hidden" name="sidebar" class="sidebar" value="<?php echo esc_attr($this->sidebar_id); ?>" />
			<input type="hidden" name="widget-width" class="widget-width" value="<?php if (isset( $control['width'] )) echo esc_attr($control['width']); ?>" />
			<input type="hidden" name="widget-height" class="widget-height" value="<?php if (isset( $control['height'] )) echo esc_attr($control['height']); ?>" />
			<input type="hidden" name="widget_number" class="widget_number" value="<?php echo esc_attr($widget_number); ?>" />
			<input type="hidden" name="multi_number" class="multi_number" value="<?php echo esc_attr($multi_number); ?>" />
			<input type="hidden" name="add_new" class="add_new" value="<?php echo esc_attr($add_new); ?>" />

			<div class="widget-control-actions">
				<div class="alignright<?php if ( 'noform' === $has_form ) echo ' widget-control-noform'; ?>">
					<input type="button" name="updatewidget" id="updatewidget" class="button button-secondary widget-control-update right" value="<?php esc_attr_e( 'Update', 'widget-customizer' ) ?>">
					<span class="spinner"></span>
				</div>
				<br class="clear" />
			</div>
		</div>
		<?php
	}
}
