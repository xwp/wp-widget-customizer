<?php
/**
 * Tests bootstrapper.
 *
 * @author XWP
 * @author Akeda Bagus <akeda@xwp.co>
 */

require_once getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

function _manually_load_plugin() {
	$current_plugin_path = dirname( dirname( __FILE__ ) );

	require $current_plugin_path . '/widget-customizer.php';
	do_action( 'plugins_loaded' );
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';
