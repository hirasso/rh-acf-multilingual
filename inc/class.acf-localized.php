<?php 

namespace R\ACFL;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AcfLocalized extends Singleton {

  private $prefix = 'rhml';

  public function __construct() {
    
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('admin_init', [$this, 'admin_init'], 11);
    add_action('admin_notices', [$this, 'show_admin_notices']);
    add_action('plugins_loaded', [$this, 'set_language']); // maybe a better plpace is 'request' ?
    add_filter('home_url', [$this, 'filter_home_url'], 10, 4);
    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array'], PHP_INT_MAX);
    add_action('init', [$this, 'init']);
    add_filter('locale', [$this, 'filter_frontend_locale']);
    
  }

  /**
   * Init
   *
   * @return void
   */
  public function init() {
    // flush_rewrite_rules(true);
  }

  /**
   * Admin init
   *
   * @return void
   */
  public function admin_init() {
    
  }

  /**
   * Enqueues Admin Assets
   *
   * @return void
   */
  public function enqueue_admin_assets() {
    wp_enqueue_style( "$this->prefix-admin", $this->asset_uri("assets/$this->prefix-admin.css"), [], null );
    wp_enqueue_script( "$this->prefix-admin", $this->asset_uri("assets/$this->prefix-admin.js"), ['jquery'], null, true );
  }

  /**
   * Helper function to get versioned asset urls
   *
   * @param [type] $path
   * @return void
   */
  private function asset_uri( $path ) {
    $uri = plugins_url( $path, __FILE__ );
    $file = $this->get_plugin_path( $path );
    if( file_exists( $file ) ) {
      $version = filemtime( $file );
      $uri .= "?v=$version";
    }
    return $uri;
  }

  /**
   * Helper function to get a file path inside this plugin's folder
   *
   * @return void
   */
  function get_plugin_path( $path ) {
    $path = ltrim( $path, '/' );
    $file = plugin_dir_path( __FILE__ ) . $path;
    return $file;
  }

  /**
   * Helper function to transform an array to an object
   *
   * @param array $array
   * @return stdClass
   */
  private function to_object( $array ) {
    return json_decode(json_encode($array));
  }

  /**
   * Helper function to detect a development environment
   */
  private function is_dev() {
    return defined('WP_ENV') && WP_ENV === 'development';
  }

  /**
   * Get a template
   *
   * @param string $template_name
   * @param mixed $value
   * @return string
   */
  public function get_template($template_name, $value = null) {
    $value = $this->to_object($value);
    $path = $this->get_plugin_path("templates/$template_name.php");
    $path = apply_filters("$this->prefix/template/$template_name", $path);
    if( !file_exists($path) ) return "<p>$template_name: Template doesn't exist</p>";
    ob_start();
    if( $this->is_dev() ) echo "<!-- Template Path: $path -->";
    include( $path );
    return ob_get_clean();
  }

  /**
   * Check if on acf options page
   *
   * @return boolean
   */
  public function is_admin_acf_options_page() {
    if( !function_exists('acf_get_options_page') ) return false;
    if( !$slug = $_GET['page'] ?? null ) return false;
    if( !$options_page = acf_get_options_page($slug) ) return false;
    $prepare_slug = preg_replace( "/[\?|\&]page=$slug/", "", basename( $_SERVER['REQUEST_URI'] ) );
    if( !empty($options_page['parent_slug']) && $options_page['parent_slug'] !== $prepare_slug ) return false;
    return true;
  }

  /**
   * Adds an admin notice
   *
   * @param string $key
   * @param string $message
   * @param string $type
   * @return void
   */
  public function add_admin_notice( $key, $message, $type = 'warning', $is_dismissible = false ) {
    $notices = get_transient("$this->prefix-admin-notices");
    if( !$notices ) $notices = [];
    $notices[$key] = [
      'message' => $message,
      'type' => $type,
      'is_dismissible' => $is_dismissible
    ];
    set_transient("$this->prefix-admin-notices", $notices);
  }
  
  /**
   * Shows admin notices from transient
   *
   * @return void
   */
  public function show_admin_notices() {
    $notices = get_transient("$this->prefix-admin-notices");
    delete_transient("$this->prefix-admin-notices");
    if( !is_array($notices) ) return;
    foreach( $notices as $notice ) {
      ob_start() ?>
      <div class="notice notice-<?= $notice['type'] ?> <?= $notice['is_dismissible'] ? 'is-dismissible' : '' ?>">
        <p><?= $notice['message'] ?></p>
      </div>
      <?php echo ob_get_clean();
    }
  }
  
  /**
   * Get all activated languages
   *
   * @param String $format    'full' or 'iso'
   * @return Array
   */
  public function get_languages( $format = 'full' ) {
    $languages = [
      'en' => [
        'locale' => 'en_US',
        'name' => 'English'
      ],
      'de' => [
        'locale' => 'de_DE',
        'name' => 'Deutsch'
      ]
    ];
    if( $format === 'iso' ) $languages = array_keys($languages);
    return apply_filters('rh/multilang/languages', $languages);
  }

  /**
   * Get default language
   *
   * @return String
   */
  public function get_default_language() {
    return apply_filters('rh/multilang/default_language', 'en');
  }

  /**
   * Set current language
   *
   * @return String
   */
  public function set_language() {
    if( $this->is_frontend() ) {
      $language = $this->get_language_in_url($this->get_current_url());
    } else {
      $locale = get_user_locale();
      $language = explode('_', $locale)[0];
    }
    if( !$language ) $language = acfl()->get_default_language();
    $this->language = $language;
  }

  /**
   * Get language
   *
   * @return String
   */
  public function get_language() {
    return $this->language ?: $this->get_default_language();
  }

  /**
   * Convert an URL for a language
   *
   * @param [type] $url
   * @param [type] $language
   * @return void
   */
  public function convert_url($url, $requested_language) {
    // bail early if this URL points towards the WP content directory
    if( strpos($url, content_url()) === 0 ) return $url;
    // get language from requested URL
    $language_in_url = $this->get_language_in_url($url);
    // bail early if the URL language is already the same as the requested language
    if( $language_in_url === $requested_language ) return $url;
    // get the unfiltered home url
    $raw_home_url = $this->get_raw_home_url();
    $search_home_url = $raw_home_url;
    $new_home_url = $raw_home_url;
    // add the $requested_language to the new URL, if it's not the default
    if( $requested_language !== acfl()->get_default_language() ) {
      $new_home_url = trailingslashit($raw_home_url) . $requested_language;
    }
    // append url language to the search if present
    if( $language_in_url ) $search_home_url .= "/$language_in_url";
    $url = str_replace($search_home_url, $new_home_url, $url);
    return $url;
  }

  /**
   * Converts the current URL to a requested language
   *
   * @param String $language
   * @return String
   */
  public function convert_current_url($language) {
    return $this->convert_url($this->get_current_url(), $language);
  }

  /**
   * Detect language information in URL
   *
   * @return String the detecte language
   */
  public function get_language_in_url($url) {
    $url = untrailingslashit($url);
    $home_url = untrailingslashit($this->get_raw_home_url());
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
  private function get_raw_home_url($path = '') {
    remove_filter('home_url', [$this, 'filter_home_url']);
    $home_url = home_url($path);
    add_filter('home_url', [$this, 'filter_home_url'], 10, 4);
    return $home_url;
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
  public function filter_frontend_locale($locale) {
    if( !$this->is_frontend() ) return $locale;
    $languages = acfl()->get_languages();
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
    $url = $this->convert_url($url, acfl()->get_language());
    return $url;
  }

  /**
   * Detect if on frontend
   *
   * @return Boolean
   */
  public function is_frontend() {
    return !is_admin() && !wp_doing_ajax();
  }

  /**
   * Prepend language information to all rewrite rules
   *
   * @link https://wordpress.stackexchange.com/a/238369/18713
   * @param Array $rules
   * @return Array
   */
  public function rewrite_rules_array($rules) {
    $languages = acfl()->get_languages('iso');
    $new_rules = array();
    $regex_languages = implode('|', $languages);
    $new_rules["(?:$regex_languages)?/?$"] = 'index.php';

    foreach ($rules as $key => $val) {
        $key = "(?:$regex_languages)?/?" . ltrim($key, '^');
        $new_rules[$key] = $val;
    }

    return $new_rules;
  }

}