<?php
/**
 * Plugin Name: ACF Multilingual
 * Version: 0.0.1
 * Author: Rasso Hilber
 * Description: A lightweight solution to support multiple languages with WordPress and Advanced Custom Fields
 * Author URI: https://rassohilber.com
**/

namespace R\ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Require classes
 */
require_once(__DIR__ . '/inc/class.singleton.php');
require_once(__DIR__ . '/inc/class.acfml.php');
require_once(__DIR__ . '/inc/class.translatable-fields.php');
require_once(__DIR__ . '/inc/class.admin.php');
require_once(__DIR__ . '/inc/class.titles.php');

/**
 * Initialize classes
 */
ACFML::getInstance();
TranslatableFields::getInstance();
Admin::getInstance();
Titles::getInstance();

/**
 * Make main instance available API calls
 *
 * @return ACFML
 */
function acfml() { 
  return ACFML::getInstance(); 
}