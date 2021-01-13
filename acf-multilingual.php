<?php
/**
 * Plugin Name: ACF Multilingual
 * Version: 0.0.1
 * Author: Rasso Hilber
 * Description: A lightweight solution to support multiple languages with WordPress and Advanced Custom Fields
 * Author URI: https://rassohilber.com
**/

if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('ACF_Multilingual') ) :

class ACF_Multilingual {

  private $prefix = 'acfml';
  private $debug = false;
  private $language = null;

  /**
   * ACFML_Admin instance
   *
   * @var ACFML\ACFML_Admin
   */
  public $acfml_admin; 

  /**
   * ACFML_Fields instance
   *
   * @var ACFML\ACFML_Fields
   */
  public $acfml_fields; 

  /**
   * ACFML_Post_Types instance
   *
   * @var ACFML\ACFML_Post_Types
   */
  public $acfml_post_types;

  /**
   * ACFML_Taxonomies instance
   *
   * @var ACFML\ACFML_Taxonomies
   */
  public $acfml_taxonomies; 

  

  public function  __construct() {}

  public function initialize() {
    
    $this->define( 'ACFML', true );
    $this->define( 'ACFML_PATH', plugin_dir_path( __FILE__ ) );
    $this->define( 'ACFML_BASENAME', plugin_basename( __FILE__ ) );

    // Include classes.
    $this->include('inc/class.acfml-admin.php');
    $this->include('inc/class.acfml-fields.php');
    $this->include('inc/class.acfml-post-types.php');
    $this->include('inc/class.acfml-taxonomies.php');
    // Instanciate classes
    $this->acfml_admin = new ACFML\ACFML_Admin();
    $this->acfml_fields = new ACFML\ACFML_Fields();
    $this->acfml_post_types = new ACFML\ACFML_Post_Types();
    $this->acfml_taxonomies = new ACFML\ACFML_Taxonomies();

    $this->add_hooks();

  }

  /**
   * define
   *
   * Defines a constant if doesnt already exist.
   *
   * @param	string $name The constant name.
   * @param	mixed $value The constant value.
   * @returnvoid
   */
  function define( $name, $value = true ) {
    if( !defined($name) ) {
      define( $name, $value );
    }
  }

  /*
   * include
   *
   * Includes a file within the ACFML plugin.
   *
   * @param	string $filename The specified file.
   * @return	void
   */
  function include( $filename = '' ) {
    $file_path = $this->get_file_path($filename);
    if( file_exists($file_path) ) {
      include_once($file_path);
    }
  }

  /**
   * get_path
   *
   * Returns the plugin path to a specified file.
   *
   * @param	string $filename The specified file.
   * @return	string
   */
  public function get_file_path( $filename = '' ) {
    return ACFML_PATH . ltrim($filename, '/');
  }

  /**
   * Add filter and action hooks
   *
   * @return void
   */
  private function add_hooks() {
    add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('admin_init', [$this, 'admin_init'], 11);
    add_action('plugins_loaded', [$this, 'detect_language']);
    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array'], PHP_INT_MAX-1);
    
    // add_action('init', [$this, 'flush_rewrite_rules'], PHP_INT_MAX);
    add_filter('locale', [$this, 'filter_frontend_locale']);
    add_action('wp_head', [$this, 'wp_head']);

    add_action('init', [$this, 'add_link_filters']);
    add_action('init', [$this, 'detect_preferred_language'], 1);
    // links in the_content
    add_filter('acf/format_value/type=wysiwyg', [$this, 'format_acf_field_wysiwyg'], 11);
  }

  /**
   * Return Plugin Prefix
   *
   * @return void
   */
  public function get_prefix() {
    return $this->prefix;
  }

  /**
   * flush_rewrite_rules
   *
   * @return void
   */
  public function flush_rewrite_rules() {
    flush_rewrite_rules(true);
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
    wp_enqueue_style("$this->prefix-admin", $this->asset_uri("assets/admin.css"), [], null);
    wp_enqueue_script("$this->prefix-admin", $this->asset_uri("assets/admin.js"), ['jquery'], null, true);
    wp_add_inline_script("$this->prefix-admin", $this->get_admin_inline_script(), "before");
  }

  /**
   * Add an inline script
   *
   * @param string
   */
  private function get_admin_inline_script() {
    $settings = [
      'defaultLanguage' => $this->get_default_language(),
      'languages' => $this->get_languages(),
      'isMobile' => wp_is_mobile()
    ];
    ?><script id="acfml-settings"><?php ob_start() ?>
    var acfml = <?= json_encode($settings) ?>;
    <?php $script = ob_get_clean(); ?></script><?php return $script;
  }

  /**
   * Get the home url in the requested or the default language
   *
   * @param string|Null $lang
   * @param string
   */
  public function home_url($path = '', $lang = null) {
    $home_url = home_url();
    if( !$lang ) $lang = $this->get_current_language();
    if( $lang === $this->get_default_language() ) return $home_url . $path;
    return trailingslashit($home_url) . $lang . $path;
  }

  /**
   * Helper function to get versioned asset urls
   *
   * @param string $path
   * @param string
   */
  private function asset_uri( $path ) {
    $uri = plugins_url( $path, __FILE__ );
    $file = $this->get_file_path( $path );
    if( file_exists( $file ) ) {
      $version = filemtime( $file );
      $uri .= "?v=$version";
    }
    return $uri;
  }

  /**
   * Helper function to transform an array to an object
   *
   * @param array $array
   * @return stdClass
   */
  public function to_object( $array ) {
    return json_decode(json_encode($array));
  }

  /**
   * Helper function to detect a development environment
   */
  private function is_dev() {
    if( defined('WP_ENV') && WP_ENV === 'development' ) return true;
    if( wp_get_environment_type() === 'development' ) return true;
    return false;
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
    $path = $this->get_file_path("templates/$template_name.php");
    $path = apply_filters("$this->prefix/template/$template_name", $path);
    if( !file_exists($path) ) return "<p>$template_name: Template doesn't exist</p>";
    ob_start();
    if( $this->is_dev() ) echo "<!-- Template Path: $path -->\n";
    include( $path );
    return ob_get_clean();
  }
  
  /**
   * Get all activated languages
   *
   * @param string $format    'full' or 'slug'
   * @return Array
   */
  public function get_languages( $format = 'full' ) {
    $languages = [
      [
        'slug' => 'en',
        'locale' => 'en_US',
        'name' => 'English'
      ],
      [
        'slug' => 'de',
        'locale' => 'de_DE',
        'name' => 'Deutsch'
      ],
    ];
    $languages = apply_filters("$this->prefix/languages", $languages);
    if( $format === 'slug' ) return array_column($languages, 'slug');
    return $languages;
  }

  /**
   * Generate a language switcher for use in the frontend.
   *
   * @param array|null $args          An array with settings for your language switcher. Look at the wp_parse_args below
   *                                  to see the default settings.
   * 
   *                                  - format:
   *                                      - 'list' (default): Returns HTML like this: <ul><li><a></li><li><a></li>...</ul>
   *                                      - 'list_items': Samle as 'list', but without the wrapping <ul> element
   *                                      - 'dropdown': Returns HTML like this: <select><option><option>...</select>
   *                                      - '$key:$value': Returns an array containing the contents of one column as keys and of another column as values
   *                                      - 'raw': Returns an array
   *                                  - display_names_as: 
   *                                      – 'name' (default): e.g. 'English', 'Deutsch', ...
   *                                      – 'slug': e.g. 'en', 'de', ...
   *                                  – hide_current: 
   *                                      - false (default): show the currenty active language
   *                                      – true : hide the currently active language
   *                                  – url: if specified, show links to translated versions for that URL. 
   *                                      - null (default): show translations for current url
   *                                      – 'https://...': show translations for that url
   *                                  - element_class: overwrite the class of the language switcher html element(s)
   * 
   * @return mixed                    Either a html string or an array
   */
  public function get_language_switcher(?array $args = [], bool $echo = false) {
    static $dropdown_count = 0;
    static $list_count = 0;
    $args = $this->to_object(wp_parse_args($args, [
      'format' => 'list',
      'display_names_as' => 'name',
      'hide_current' => false,
      'url' => null,
      'element_class' => 'acfml-language-switcher',
      // 'hide_if_no_translation' => true, // @TODO
    ]));
    $languages = $this->get_languages();
    foreach( $languages as $key => &$language ) {
      $language['is_default'] = $this->is_default_language($language['slug']);
      $language['is_current'] = $language['slug'] === $this->get_current_language();
      $language['html_classes'] = [];
      if( $language['is_current'] ) $language['html_classes'][] = 'is-current-language';
      if( $this->is_default_language($language['slug']) ) $language['html_classes'][] = 'is-default-language';
      if( $args->hide_current && $language['is_current'] ) unset($languages[$key]);
      $this->debug = true;
      $language['url'] = $args->url ? $this->convert_url($args->url, $language['slug']) : $this->convert_current_url($language['slug']);
      $this->debug = false;
      switch( $args->display_names_as ) {
        case 'name':
          $language['display_name'] = $language['name'];
          break;
        case 'slug':
          $language['display_name'] = $language['slug'];
          break;
      }
      unset($language);
    }
    
    // $languages = array_filter($languages, function($language) {
    //   return !empty($language['url']);
    // });
    
    if( count($languages) < 2 ) return false;

    // return for special $format 'key:value'
    if( strpos($args->format, ':') !== false ) {
      $key_value = explode(':', $args->format);
      return array_combine( 
        array_column($languages, $key_value[0]), 
        array_column($languages, $key_value[1]) 
      );
    }
    // return other formats
    switch( $args->format ) {
      case 'dropdown':
        $dropdown_count ++;
        return $this->get_template('language-switcher-dropdown', [
          'languages' => $languages, 
          'element_class' => $args->element_class,
          'element_id' => "acfml-language-dropdown-$dropdown_count",
          'args' => $args,
        ]);
        break;
      case 'list':
      case 'list_items':
        $list_count ++;
        return $this->get_template('language-switcher-list', [
          'languages' => $languages,
          'element_class' => $args->element_class,
          'element_id' => "acfml-language-list-$list_count",
          'args' => $args,
        ]);
        break;
    }
    // return raw
    return $languages;
  }

  /**
   * Get non-default languages
   * 
   * @param $format
   * @return array
   */
  public function get_non_default_languages(string $format = 'full'): array {
    $languages = $this->get_languages();
    $languages = array_filter($languages, function($language) {
      return $language['is_default'] !== true;
    });
    if( $format === 'slug' ) $languages = array_column($languages, 'slug');
    // cleanup array keys
    return array_merge($languages);
  }

  /**
   * Get information for a language iso key
   *
   * @param string $lang_iso    e.g. 'en' or 'de'
   * @return array|null
   */
  public function get_language_info(string $lang_iso):? array {
    foreach( $this->get_languages() as $language ) {
      if( $language['slug'] === $lang_iso ) return $language;
    }
    return null;
  }

  /**
   * Get default language
   *
   * @param string
   */
  public function get_default_language(): string {
    $lang = apply_filters("$this->prefix/default_language", 'en');
    if( !$this->language_exists($lang) ) {
      throw new Exception(sprintf("ACFML Error: Default Language '%s' doesn't exist", $lang));
    }
    return $lang;
  }

  /**
   * Check if a language exists
   *
   * @param string $lang
   * @return bool
   */
  private function language_exists($lang): bool {
    return in_array($lang, array_column($this->get_languages(), 'slug'));
  }

  /**
   * Check if a language is the default language
   *
   * @param string $language
   * @return boolean
   */
  public function is_default_language( string $language ): bool {
    return $language === $this->get_default_language();
  }

  /**
   * Check if a given language is the current language
   *
   * @param string $language
   * @return boolean
   */
  public function is_current_language( string $language ): bool {
    return $language === $this->get_current_language();
  }

  /**
   * Detect language in different contexts
   *
   */
  public function detect_language() {
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    // ajax requests: check referrer to detect if called from frontend.
    if( wp_doing_ajax() && $referrer && strpos($referrer, admin_url()) !== 0 ) {
      $language = $this->get_language_in_url($referrer);
    } elseif( !is_admin() ) {
      $language = $this->get_language_in_url($this->get_current_url());
    } else {
      $language = $this->get_admin_language();
    }
    if( !$language ) $language = $this->get_default_language();
    $this->language = $language;
    $this->define('ACFML_CURRENT_LANGUAGE', $language);
    return $language;
  }

  /**
   * Switch to a langauge
   *
   * @param string $language    the slug of the language, e.g. 'en' or 'de'
   * @return void
   */
  public function switch_to_language($language) {
    $languages = $this->get_languages('slug');
    if( !in_array($language, $languages) ) return false;
    $this->language = $language;
    return true;
  }

  /**
   * Resets the current language to the defined value
   *
   * @return void
   */
  public function switch_to_current_language() {
    $this->language = defined('ACFML_CURRENT_LANGUAGE') ? ACFML_CURRENT_LANGUAGE : $this->get_default_language();
  }

  /**
   * Get admin language. First checks for a Cookie, falls back to user langguae
   * 
   * @return void
   */
  public function get_admin_language() {
    $language = $_COOKIE["$this->prefix-admin-language"] ?? null;
    if( !$language ) {
      $locale = get_user_locale();
      $language = explode('_', $locale)[0];
    }
    return $language;
  }

  /**
   * Get language
   *
   * @return string
   */
  public function get_current_language(): string {
    return $this->language ?? $this->detect_language();
  }

  /**
   * Check if the current language is the default
   *
   * @return bool
   */
  public function current_language_is_default(): bool {
    return $this->get_current_language() === $this->get_default_language();
  }

  /**
  * Convert an URL for a language
  *
  * @param string $url
  * @param string $language
  * 
  * @return string $url
  */
  public function convert_url( string $url, string $requested_language = null ): string {

    if( !$requested_language ) $requested_language = $this->get_current_language();
    // bail early if this URL points towards the WP content directory
    if( strpos($url, content_url()) === 0 ) return $url;

    if( $url_query = $this->get_query_from_url($url) ) {
      $queried_object = $url_query->get_queried_object();
      // if( $this->debug ) pre_dump( $queried_object );

      if( $queried_object instanceof \WP_Post ) {
        $new_url = $this->acfml_post_types->get_post_link($queried_object, $requested_language);
        return $new_url;
      } elseif( $queried_object instanceof \WP_Post_Type ) {
        $new_url = $this->acfml_post_types->get_post_type_archive_link($queried_object->name, $requested_language);
        return $new_url;
      }
    }    

    // if nothing special was found, only inject the language code
    return $this->simple_convert_url($url, $requested_language);
  }

  /**
   * Simply replaces the language code in an URL, or strips it for the default language
   *
   * @param string $url
   * @param string $requested_language
   * @return string
   */
  public function simple_convert_url( string $url, string $requested_language = null ): string {
    $current_language = $this->get_language_in_url($url) ?: $this->get_default_language();
    $current_home_url = $this->home_url('', $current_language);
    $new_home_url = $this->home_url('', $requested_language);
    $url = str_replace($current_home_url, $new_home_url, $url);
    return $url;
  }

  /**
   * Get all link filters
   *
   * @return Array
   */
  private function get_link_filters():array {
    $filters = [
      "simple" => [
        "author_feed_link" => 10,
        "author_link" => 10,
        "get_comment_author_url_link" => 10,
        "post_comments_feed_link" => 10,
        "day_link" => 10,
        "month_link" => 10,
        "year_link" => 10,
        "category_link" => 10,
        "category_feed_link" => 10,
        "tag_link" => 10,
        "term_link" => 10,
        "feed_link" => 10,
        "tag_feed_link" => 10,
        "get_shortlink" => 10,
        "rest_url" => 10,
      ],
      "complex" => [
        "page_link" => 10,
        "post_link" => 10,
        "post_type_link" => 10,
        "attachment_link" => 10,
        "the_permalink" => 10,
        "post_type_archive_link" => 10,
        "redirect_canonical" => 10,
      ],
    ];

    $filters['simple'] = apply_filters("acfml/simple_link_filters", $filters['simple']);
    $filters['complex'] = apply_filters("acfml/complex_link_filters", $filters['complex']);

    return $filters;
  }

  /**
   * Add Link filters
   *
   * @return void
   */
  public function add_link_filters() {
    $filters = $this->get_link_filters();
    foreach( $filters['simple'] as $filter_name => $priority ) {
      add_filter($filter_name, [$this, 'simple_convert_url'], intval($priority));
    }
    foreach( $filters['complex'] as $filter_name => $priority ) {
      add_filter($filter_name, [$this, 'convert_url'], intval($priority));
    }
  }

  /**
  * Remove Link filters
  *
  * @return void
  */
  public function remove_link_filters() {
    $filters = $this->get_link_filters();
    foreach( $filters['simple'] as $filter_name => $priority ) {
      remove_filter($filter_name, [$this, 'simple_convert_url']);
    }
    foreach( $filters['complex'] as $filter_name => $priority ) {
      remove_filter($filter_name, [$this, 'convert_url']);
    }
  }

  /**
   * Converts the current URL to a requested language
   *
   * @param string $language
   * @param string
   */
  public function convert_current_url($language) {
    $url = $this->convert_url($this->get_current_url(), $language);
    return $url;
  }

  /**
   * Detect language information in URL
   *
   * @param string the detecte language
   */
  public function get_language_in_url($url) {
    $url = untrailingslashit($url);
    $path = str_replace(home_url(), '', $url);
    $regex_languages = implode('|', $this->get_languages('slug'));
    preg_match("%/($regex_languages)(/|$|\?|#)%", $path, $matches);
    $language = $matches[1] ?? null;
    return $language;
  }

  /**
   * Get current URL from $_SERVER
   *
   * @param string $url
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
    if( is_admin() ) return $locale;
    return str_replace('_', '-', $this->get_language_info($this->get_current_language())['locale']);
  }

  /**
   * Prepend language information to all rewrite rules
   *
   * @link https://wordpress.stackexchange.com/a/238369/18713
   * @param Array $rules
   * @return Array
   */
  public function rewrite_rules_array($rules) {
    $new_rules = array();
    $regex_languages = implode('|', $this->get_languages('slug'));
    $new_rules["(?:$regex_languages)?/?$"] = 'index.php';

    foreach ($rules as $key => $val) {
        $key = "(?:$regex_languages)?/?" . ltrim($key, '^');
        $new_rules[$key] = $val;
    }
    return $new_rules;
  }

  /**
   * Hooks into wp_head and prints out hreflang tags
   *
   * @return void
   */
  public function wp_head() {
    $default_language = $this->get_default_language();
    $languages = $this->get_languages();
    foreach( $languages as &$language ) {
      $language['url'] = $this->convert_current_url($language['slug']);
      $language['is_default'] = $this->is_default_language($language['slug']);
    }
    echo $this->get_template('meta-tags', [
      'languages' => $languages,
    ]);
  }

  /**
   * Filter for 'the_content'
   *
   * @param string $value
   * @param string
   */
  public function format_acf_field_wysiwyg($value) {
    return $this->convert_urls_in_string($value);
  }

  /**
   * Strip protocol from URL
   *
   * @param [type] $url
   * @return void
   */
  private function strip_protocol($url) {
    return preg_replace('#^https?:#', '', $url);
  }

  /**
   * Convert URLs in Strings
   *
   * @param string $string
   * @param string
   */
  public function convert_urls_in_string($string) {
    $string = preg_replace_callback('/href=[\'|\"](?<url>http.*?)[\'|\"]/', function($matches) {
      $url = $matches['url'];
      if( strpos($this->strip_protocol($url), $this->strip_protocol(home_url())) !== false ) {
        $url = $this->convert_url($url);
      }
      return "href=\"$url\"";
    }, $string);
    return $string;
  }

  /**
   * Get a field's value, or if there is no value, return the fallback
   *
   * @param string $selector
   * @param [type] $fallback
   * @param boolean $post_id
   * @param boolean $format_value
   * @return Mixed
   */
  public function get_field_or(String $selector, $fallback, $post_id = false, $format_value = true) {
    $value = get_field($selector, $post_id, $format_value);
    return $value ?: $fallback;
  }

  /**
   * Get path from URL
   *
   * – removes home url 
   * – removes query
   * – removes leading and trailing slashes
   * 
   * @param string $url
   * @param string
   */
  private function get_url_path(String $url): string {
    $path = str_replace(home_url(), '', $url);
    $path = explode('?', $path)[0];
    $path = trim($path, '/');
    // prepare the $path
    $path     = rawurlencode( urldecode( $path ) );
    $path     = str_replace( '%2F', '/', $path );
    $path     = str_replace( '%20', ' ', $path );
    
    return $path;
  }

  /**
   * Uses built-in WP functionality to parse and query for a given URL, but with a custom URL and language
   *
   * @param string|null $url
   * @param string|null $language
   * @return mixed one of null, \WP_Post, \WP_Post_Type, \WP_Term
   */
  public function get_query_from_url(?string $url = null): ?\WP_Query {
    global $wp, $wp_the_query;
    $_wp_the_query = $wp_the_query;
    // parse defaults
    $url = $url ?? $this->get_current_url();

    // get the path from the url, return early if none
    $path = $this->get_url_path($url);
    
    if( !$path ) return null;

    // overwrite the language for the time of the request
    $this->switch_to_language($this->get_language_in_url($url));

    // clone the global WP object
    $wp_clone = clone $wp;

    $req_uri = $_SERVER['REQUEST_URI'];
    $_SERVER['REQUEST_URI'] = $path;
    $wp_clone->parse_request();
    $wp_clone->build_query_string();
    // if( !count($wp_clone->query_vars) ) return null;
    $_SERVER['REQUEST_URI'] = $req_uri;
    
    $query = new \WP_Query();
    $wp_the_query = $query;
    $query->query( $wp_clone->query_vars );
    
    $wp_the_query = $_wp_the_query;
    // reset the language
    $this->switch_to_current_language();
    
    return $query;
  }

  public function detect_preferred_language() {
    if( is_admin() || is_robots() ) return;
    
    $languages = $this->get_languages('slug');
    $user_lang = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
    $cookie_lang = $_COOKIE['language'] ?? null;
    
    $lang = $cookie_lang ?? $user_lang;
    if( !in_array($lang, $languages) ) $lang = $this->get_default_language();

    
    if( !$this->is_internal_referrer() && $cookie_lang && !$this->is_current_language($cookie_lang) ) {
      $redirect_url = $this->convert_current_url($user_lang);
      wp_redirect( $redirect_url );
      exit;
    } else {
      setcookie("language", $this->get_current_language(), (DAY_IN_SECONDS * 365), '/');
    }

    // if( $lang !== $this->get_current_language($languages) ) {
    //   $redirect_url = $this->convert_current_url($user_lang);
    //   wp_redirect( $redirect_url );
    //   exit;
    // }
    // setcookie("language", null, 0, '/'); // 86400 = 1 day
    // setcookie("language", 'de', 0, '/'); // 86400 = 1 day
  }

  /**
   * Checks if the current referrer comes from our frontend
   *
   * @return boolean
   */
  private function is_internal_referrer(): bool {
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    if( !$referrer ) return false;
    $home_url_parsed = parse_url(get_option('home'));
    $referrer_parsed = parse_url($referrer);
    return $home_url_parsed['host'] === $referrer_parsed['host'];
  }
  
}

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
  global $acfml;

  // Instantiate only once.
  if( !isset($acfml) ) {
    $acfml = new ACF_Multilingual();
    $acfml->initialize();
  }
  return $acfml;
}

acfml(); // Instantiate

endif; // class_exists check

/**
 * If ACF is defined, initialize the plugin.
 * Otherwise, show a notice.
 */
function acfml_initialize_plugin() {
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