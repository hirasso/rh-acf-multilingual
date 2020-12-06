<?php 

namespace R\MultiLang;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Frontend extends Singleton {

  public function __construct() {
    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array'], PHP_INT_MAX);
    add_action('init', [$this, 'flush']);
    add_action('plugins_loaded', [$this, 'detect_language']);
    add_filter('locale', [$this, 'locale']);
  }

  /**
   * Flush rewrite rules on every init (for development)
   *
   * @return void
   */
  public function flush() {
    flush_rewrite_rules(true);
  }

  /**
   * Prepend language information to all rewrite rules
   *
   * @link https://wordpress.stackexchange.com/a/238369/18713
   * @param Array $rules
   * @return Array
   */
  public function rewrite_rules_array($rules) {
    $languages = ml()->get_enabled_languages('keys');
    $new_rules = array();
    $regex_languages = implode('|', $languages);
    $new_rules["(?:$regex_languages)?/?$"] = 'index.php';

    foreach ($rules as $key => $val) {
        $key = "(?:$regex_languages)?/?" . ltrim($key, '^');
        $new_rules[$key] = $val;
    }

    return $new_rules;
  }


  /**
   * Detect language information in URL
   *
   * @return String the detecte language
   */
  public function detect_language($locale) {
    if( is_admin() && !wp_doing_ajax() ) return $locale;
    $default_language = ml()->get_default_language();
    preg_match('/\/(de|en)(\/|$|\?|#)/', $_SERVER['REQUEST_URI'], $matches);
    $this->language = $matches[1] ?? $default_language;
  }

  /**
   * Filter locale in frontend
   *
   * @param [type] $locale
   * @return void
   */
  public function locale($locale) {
    if( is_admin() ) return $locale;
    $languages = ml()->get_enabled_languages();
    return str_replace('_', '-', $languages[$this->language]['locale']);
  } 
}