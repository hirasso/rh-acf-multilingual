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
 * Deletes a directory, using the WordPress Filesystem API
 *
 * @param string $path
 * @return void
 * @author Rasso Hilber <mail@rassohilber.com>
 */
function _cleanup_languages_dir() {
  echo "\nCleaning up languages directory...\n\n";
  require_once ABSPATH . 'wp-admin/includes/file.php';
  global $wp_filesystem;
  WP_Filesystem();
  $wp_filesystem->delete(WP_LANG_DIR, true);
}

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugins() {
  // Clean up the languages dir before running tests, so we can test downloading them
  _cleanup_languages_dir();
  // require ACF, which is a dependency of ACFML
  require_once( dirname( dirname( dirname( __FILE__ ) ) ) . '/advanced-custom-fields-pro/acf.php' );
  // require the main plugin file
	require_once( dirname( dirname( __FILE__ ) ) . '/acfml.php' );
  // don't autamatically load acfml in tests
  remove_action('plugins_loaded', 'acfml');
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugins' );

function var_dump_exit($thing) {
  var_dump($thing);
  exit;
}

function var_export_exit($thing) {
  var_export($thing);
  exit;
}

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
