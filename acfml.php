<?php
/**
 * Plugin Name: ACF Multilingual
 * Version: 1.2.9
 * Author: Rasso Hilber
 * Description: A lightweight solution to support multiple languages with WordPress and Advanced Custom Fields
 * Author URI: https://rassohilber.com
 * Text Domain: acfml
 * Domain Path: /lang
**/

if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ACFML\ACFMultilingual;
use ACFML\Config;

define( 'ACFML', true );
define( 'ACFML_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACFML_BASENAME', plugin_basename( __FILE__ ) );
define( 'ACFML_URL', plugins_url('/', __FILE__) );

require_once( ACFML_PATH . 'vendor/autoload.php' );
require_once( ACFML_PATH . 'api.php' );

/**
 * acfml
 *
 * The main function responsible for returning the one true acfml instance to functions everywhere.
 * Use this function like you would a global variable, except without needing to declare the global.
 *
 * Example: <?php $acfml = acfml(); ?>
 *
 * @param	void
 * @return ACFMultilingual
 */
function acfml():ACFMultilingual {
  static $acfml;

  // Instantiate only once.
  if( isset($acfml) ) return $acfml;

  $config = new Config();
  $config->load();

  $acfml = new ACFMultilingual($config);
  $acfml->initialize();

  return $acfml;
}

add_action('plugins_loaded', 'acfml'); // Instantiate
