<?php 

namespace R\MultiLang;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Frontend extends Singleton {

  public function __construct() {
    add_filter('home_url', [$this, 'filter_home_url'], 10, 4);
    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array'], PHP_INT_MAX);
    add_action('init', [$this, 'flush']);
    add_action('plugins_loaded', [$this, 'set_language']); // maybe a better plpace is 'request' ?
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
    $languages = ml()->get_enabled_languages('iso');
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
  public function get_language_in_url($url) {
    $url = untrailingslashit($url);
    $home_url = untrailingslashit($this->get_unfiltered_home_url());
    $path = trailingslashit(str_replace($home_url, '', $url));
    preg_match("%/(de|en)(/|$|\?|#)%", $path, $matches);
    $language = $matches[1] ?? null;
    return $language;
  }

  /**
   * Get unfiltered home url
   *
   * @return String
   */
  private function get_unfiltered_home_url($path = '') {
    remove_filter('home_url', [$this, 'filter_home_url']);
    $home_url = home_url($path);
    add_filter('home_url', [$this, 'filter_home_url'], 10, 4);
    return $home_url;
  }

  /**
   * Set current language
   *
   * @return String
   */
  public function set_language() {
    if( is_admin() && !wp_doing_ajax() ) return null;
    $language = $this->get_language_in_url($this->get_current_url());
    if( !$language ) $language = ml()->get_default_language();
    $this->language = $language;
  }

  /**
   * Get current URL from $_SERVER
   *
   * @return String $url
   */
  private function get_current_url() {
    $url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
    return $url;
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

  /**
   * Filter the home url
   *
   * @param String $url
   * @param String $path
   * @param String $orig_scheme
   * @param Int $blog_id
   * @return String
   */
  public function filter_home_url($url, $path, $orig_scheme, $blog_id) {
    // $url = $this->convert_url($url, $this->language);
    return $url;
  }

  /**
   * Convert an URL for a language
   *
   * @param [type] $url
   * @param [type] $language
   * @return void
   */
  public function convert_url($url = null, $language = null) {
    if( !$url ) $url = $this->get_current_url();
    if( !$language ) $language = $this->language;
    // bail early if this URL points towards the WP content directory
    if( strpos($url, content_url()) === 0 ) return $url;
    // get language from requested URL
    $language_in_url = $this->get_language_in_url($url);
    // bail early if the URL language is already the same as the requested language
    if( $language_in_url === $language ) return $url;
    // get the unfiltered home url
    $clean_home_url = $this->get_unfiltered_home_url();
    $search_home_url = $clean_home_url;
    $new_home_url = $clean_home_url;
    // add the $language to the new URL, if it's not the default
    if( $language !== ml()->get_default_language() ) {
      $new_home_url = trailingslashit($clean_home_url) . $language;
    }
    // append url language to the search if present
    if( $language_in_url ) $search_home_url .= "/$language_in_url";
    $url = str_replace($search_home_url, $new_home_url, $url);
    return $url;
  }
}