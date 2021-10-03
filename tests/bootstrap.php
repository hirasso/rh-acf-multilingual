<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Rh_ACFMultilingual
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugins() {
  // require ACF, which is a dependency of ACFML
  require dirname( dirname( dirname( __FILE__ ) ) ) . '/advanced-custom-fields-pro/acf.php';
  // require the main plugin file
	require dirname( dirname( __FILE__ ) ) . '/acfml.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugins' );

function dump_exit($thing) {
  var_dump($thing);
  exit;
}

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
