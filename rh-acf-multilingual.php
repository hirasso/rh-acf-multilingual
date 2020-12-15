<?php
/**
 * Plugin Name: ACF Multilingual
 * Version: 0.0.1
 * Author: Rasso Hilber
 * Description: A lightweight solution to support multiple languages with WordPress and Advanced Custom Fields
 * Author URI: https://rassohilber.com
**/

namespace ACFML;

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
 * @return ACFML
 */
function acfml() { 
  return ACFML::getInstance(); 
}

/**
 * Make Admin accessible
 *
 * @return Admin
 */
function admin() {
  return Admin::getInstance();
}

/**
 * Initialize classes
 */
function init() {
  ACFML::getInstance();
  Translatable_Fields::getInstance();
  Admin::getInstance();
  Translatable_Post_Titles::getInstance();
  Translatable_Term_Titles::getInstance();
  acfml()->init();
}

init();

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