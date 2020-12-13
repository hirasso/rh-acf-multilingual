<?php
/**
 * Plugin Name: RH ACF Localized
 * Version: 0.0.1
 * Author: Rasso Hilber
 * Description: A lightweight solution to support multiple languages with WordPress and Advanced Custom Fields
 * Author URI: https://rassohilber.com
**/

namespace R\ACFL;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Require classes
 */
require_once(__DIR__ . '/inc/class.singleton.php');
require_once(__DIR__ . '/inc/class.acfl.php');
require_once(__DIR__ . '/inc/class.translatable-fields.php');
require_once(__DIR__ . '/inc/class.admin.php');
require_once(__DIR__ . '/inc/class.titles.php');

/**
 * Initialize classes
 */
ACFL::getInstance();
TranslatableFields::getInstance();
Admin::getInstance();
Titles::getInstance();

/**
 * Make main instance available API calls
 *
 * @return ACFL
 */
function acfl() { 
  return ACFL::getInstance(); 
}