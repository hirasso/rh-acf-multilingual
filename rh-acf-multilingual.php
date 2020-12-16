<?php
/**
 * Plugin Name: ACF Multilingual
 * Version: 0.0.1
 * Author: Rasso Hilber
 * Description: A lightweight solution to support multiple languages with WordPress and Advanced Custom Fields
 * Author URI: https://rassohilber.com
**/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Require classes
 */
require_once(__DIR__ . '/inc/class.singleton.php');
require_once(__DIR__ . '/inc/class.acfml.php');
require_once(__DIR__ . '/inc/class.translatable-fields.php');
require_once(__DIR__ . '/inc/class.admin.php');
require_once(__DIR__ . '/inc/class.translatable-post-titles.php');
require_once(__DIR__ . '/inc/class.translatable-term-titles.php');


/**
 * Make main instance available API calls
 *
 * @return \ACFML\ACF_Multilingual
 */
function acfml() { 
  return ACFML\ACF_Multilingual::getInstance(); 
}

/**
 * Make Admin accessible
 *
 * @return \ACFML\Admin
 */
function admin() {
  return ACFML\Admin::getInstance();
}

/**
 * Initialize classes
 */

ACFML\ACF_Multilingual::getInstance();
ACFML\Translatable_Fields::getInstance();
ACFML\Admin::getInstance();
ACFML\Translatable_Post_Titles::getInstance();
ACFML\Translatable_Term_Titles::getInstance();

/**
 * If ACF is defined, initialize the plugin.
 * Otherwise, show a notice.
 */
function initialize_plugin() {
  if( defined('ACF') ) {
    init();
  } elseif( current_user_can('manage_plugins') ) {
    admin()->add_admin_notice(
      'acf_missing',
      wp_sprintf('ACF Multilingual is an extension for %s. Without it, it won\'t do anything.',
        '<a href="https://www.advancedcustomfields.com/" target="_blank">Advanced Custom Fields</a>'
      )
    );
  }
}
// add_action('plugins_loded', __NAMESPACE__ . '\\initialize_plugin');