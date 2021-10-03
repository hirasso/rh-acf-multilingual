<?php
/**
 * Plugin Name: ACF Multilingual
 * Version: 0.0.5
 * Author: Rasso Hilber
 * Description: A lightweight solution to support multiple languages with WordPress and Advanced Custom Fields
 * Author URI: https://rassohilber.com
 * Text Domain: acfml
 * Domain Path: /lang
**/

if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'ACFML', true );
define( 'ACFML_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACFML_BASENAME', plugin_basename( __FILE__ ) );
define( 'ACFML_URL', plugins_url('/', __FILE__) );


require_once(ACFML_PATH . 'vendor/autoload.php');
require_once(ACFML_PATH . 'inc/class.acfml.php');

/**
 * acfml
 *
 * The main function responsible for returning the one true acfml instance to functions everywhere.
 * Use this function like you would a global variable, except without needing to declare the global.
 *
 * Example: <?php $acfml = acfml(); ?>
 *
 * @param	void
 * @return ACF_Multilingual
 */
function acfml():ACF_Multilingual {
  static $acfml;

  // Instantiate only once.
  if( isset($acfml) ) return $acfml;

  $acfml = new ACF_Multilingual();
  $acfml->initialize();

  return $acfml;
}

add_action('plugins_loaded', 'acfml'); // Instantiate