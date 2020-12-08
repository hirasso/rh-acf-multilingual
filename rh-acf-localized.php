<?php
/**
 * Plugin Name: RH Multilang
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
require_once(__DIR__ . '/inc/class.acf-localized.php');
require_once(__DIR__ . '/inc/class.acf-controls.php');
require_once(__DIR__ . '/inc/class.admin.php');

/**
 * Initialize classes
 */
AcfLocalized::getInstance();
AcfControls::getInstance();
Admin::getInstance();

/**
 * Make main instance available API calls
 *
 * @return AcfLocalized
 */
function acfl() { 
  return AcfLocalized::getInstance(); 
}