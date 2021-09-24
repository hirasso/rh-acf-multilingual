<?php
/**
 * Plugin Name: ACF Multilingual
 * Version: 0.0.5
 * Author: Rasso Hilber
 * Description: A lightweight solution to support multiple languages with WordPress and Advanced Custom Fields
 * Author URI: https://rassohilber.com
 * Text Domain: acfml
 * Domain Path: /lang
**/

if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'ACFML', true );
define( 'ACFML_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACFML_BASENAME', plugin_basename( __FILE__ ) );

require_once(ACFML_PATH . 'vendor/autoload.php');

if( ! class_exists('ACF_Multilingual') ) :

class ACF_Multilingual {

  private $prefix = 'acfml';
  private $debug = false;
  private $language = null;
  private $languages = [];

  /**
   * Admin instance
   *
   * @var ACFML\Admin
   */
  public $admin; 

  /**
   * Fields_Controller instance
   *
   * @var ACFML\Fields_Controller
   */
  public $fields_controller; 

  /**
   * Post_Types_Controller instance
   *
   * @var ACFML\Post_Types_Controller
   */
  public $post_types_controller;

  /**
   * Taxonomies_Controller instance
   *
   * @var ACFML\Taxonomies_Controller
   */
  public $taxonomies_controller; 

  /**
   * Empty constructor
   */
  public function  __construct() {}

  /**
   * Intialize function. Instead of the constructor
   *
   * @return void
   */
  public function initialize() {

    // include API
    $this->include('inc/api.php');

    // Include and instanciate admin class
    $this->include('inc/class.admin.php');
    $this->admin = new ACFML\Admin();
    
    // hook into after_setup_theme to initialize
    add_action('after_setup_theme', [$this, 'maybe_fully_initialize'], 11);

  }

  /**
   * Fully initializes if there where languages registered before
   *
   * @return void
   */
  public function maybe_fully_initialize() {
    
    // bail early if ACF is not defined
    if( !defined('ACF') ) return;

    $languages = $this->get_languages();
    // bail early if there are no languages
    if( !count($languages) ) return;
    
    // Include and instanciate classes
    $this->include('inc/class.fields-controller.php');
    $this->include('inc/class.post-types-controller.php');
    $this->include('inc/class.taxonomies-controller.php');
    $this->fields_controller = new ACFML\Fields_Controller();
    $this->post_types_controller = new ACFML\Post_Types_Controller();
    $this->taxonomies_controller = new ACFML\Taxonomies_Controller();

    // run other functions
    $this->detect_language();
    $this->load_textdomain();
    $this->admin->add_hooks();

    // Switch locale manually, since the 'locale' filter is running too late for some functionality
    if( !is_admin() ) \switch_to_locale($this->get_frontend_locale());

    $this->add_hooks();

  }

  /**
   * Add filter and action hooks
   *
   * @return void
   */
  private function add_hooks() {
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_style']);
    add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    add_action('admin_init', [$this, 'download_language_packs'], 11);
    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array'], 999);

    add_filter('locale', [$this, 'filter_frontend_locale']);
    add_action('wp_head', [$this, 'wp_head']);

    $this->add_link_filters();
    add_action('template_redirect', [$this, 'redirect_front_page'], 1);
    add_action('template_redirect', [$this, 'redirect_canonical']);
    add_action('init', [$this, 'save_language_in_cookie']);

    // ACF Field Filters
    add_filter('acf/format_value/type=wysiwyg', [$this, 'format_acf_field_wysiwyg'], 11);
    add_filter('acf/format_value/type=page_link', [$this, 'format_acf_field_page_link'], 11);

    add_action('admin_init', [$this->admin, 'maybe_show_notice_flush_rewrite_rules']);
    add_action('admin_init', [$this->admin, 'maybe_flush_rewrite_rules']);

    // convert links in sitemaps entries
    add_filter('wp_sitemaps_index_entry', [$this, 'sitemaps_index_entry'], 10);
    add_action('init', [$this, 'add_sitemaps_provider']);

    // add current language to admin-ajax.php 
    add_filter('admin_url', [$this, 'convert_admin_ajax_url'], 10, 3);
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
   * load_textdomain
   *
   * Loads the plugin's translated strings similar to load_plugin_textdomain().
   *
   * @param	string $locale The plugin's current locale.
   * @return	void
   */
  public function load_textdomain() {
    
    $domain = 'acfml';
    /**
     * Filters a plugin's locale.
     *
     * @date	8/1/19
     * @since	5.7.10
     *
     * @param 	string $locale The plugin's current locale.
     * @param 	string $domain Text domain. Unique identifier for retrieving translated strings.
     */
    $locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
    $mofile = "$domain-$locale.mo";

    // Try to load from the languages directory first.
    if( load_textdomain( $domain, WP_LANG_DIR . '/plugins/' . $mofile ) ) {
      return true;
    }

    // Load from plugin lang folder.
    return load_textdomain( $domain, $this->get_file_path( 'lang/' . $mofile ) );
  }

  /**
   * Return Plugin Prefix
   *
   * @return void
   */
  public function get_prefix(): string {
    return $this->prefix;
  }

  /**
   * flush_rewrite_rules
   *
   * @return void
   */
  public function flush_rewrite_rules(): void {
    flush_rewrite_rules(true);
  }

  /**
   * Admin init
   *
   * @return void
   */
  public function download_language_packs(): void {
    /** WordPress Translation Installation API */
    require_once ABSPATH . 'wp-admin/includes/translation-install.php';
    foreach( $this->get_languages('full') as $language ) {
      wp_download_language_pack($language['locale']);
    }
  }

  /**
   * Enqueues Admin Scripts
   *
   * @return void
   */
  public function enqueue_admin_scripts() {
    wp_enqueue_script("$this->prefix-admin", $this->asset_uri("assets/admin.js"), ['jquery'], null, true);
    wp_add_inline_script("$this->prefix-admin", $this->get_admin_inline_script(), "before");
  }

  /**
   * Enqueue Admin Style
   *
   * @return void
   */
  public function enqueue_admin_style() {
    wp_enqueue_style("$this->prefix-admin", $this->asset_uri("assets/admin.css"), [], null);
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
      'isMobile' => wp_is_mobile(),
      'cookieHashForCurrentUri' => $this->get_cookie_hash()
    ];
    ?><script id="acfml-settings"><?php ob_start() ?>
    var acfml = <?= json_encode($settings) ?>;
    <?php $script = ob_get_clean(); ?></script><?php return $script;
  }

  /**
   * Get the hashed path for a cookie
   *
   * @return string
   */
  public function get_cookie_hash($uri = null) {
    $uri = $uri ?? $_SERVER['REQUEST_URI'];
    $uri = remove_query_arg('message', $uri);
    return md5($uri);
  }

  /**
   * Get an admin cookie
   *
   * @param string $key
   * @return string|null
   */
  public function get_admin_cookie( string $key ) {
    $cookie_name = $key . "_" . $this->get_cookie_hash();
    $cookie = $_COOKIE[$cookie_name] ?? null;
    return json_decode( stripslashes($cookie) );
  }

  /**
   * Get home url in requested or default language
   *
   * @param string|null $lang
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
  private function asset_uri( $path ): string {
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
   * @return object
   */
  public function to_object( $array ): ?object {
    return json_decode(json_encode($array));
  }

  /**
   * Helper function to detect a development environment
   */
  private function is_dev(): bool {
    if( defined('WP_ENV') && WP_ENV === 'development' ) return true;
    if( wp_get_environment_type() === 'development' ) return true;
    return false;
  }

  /**
   * Get a template
   *
   * @param string $template_name
   * @param mixed $value
   * @param boolean $allow_filter
   * @return string
   */
  public function get_template($template_name, $value = null, $allow_filter = true): string {
    $value = $this->to_object($value);
    $path = $this->get_file_path("templates/$template_name.php");
    if( $allow_filter ) $path = apply_filters("acfml/template/$template_name", $path);
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
   * @return array
   */
  public function get_languages( $format = 'full' ): array {
    $languages = $this->languages;
    if( $format === 'slug' ) return array_column($languages, 'slug');
    return $languages;
  }

  /**
   * Register a language
   *
   * @param string $slug          e.g. 'en' or 'de'
   * @param string|null $locale   e.g. 'en_US' or 'de_DE'
   * @param string|null $name     e.g. 'English' or 'Deutsch'
   * @return array
   */
  public function add_language(string $slug, ?string $locale = null, ?string $name = null): array {
    $language = [
      'slug' => $slug,
      'locale' => $locale ?? $slug,
      'name' => $name ?? $slug
    ];
    $this->languages[$slug] = $language;
    return $language;
  }

  /**
   * Checks if a language is enabled
   *
   * @param string $language
   * @return boolean
   */
  public function is_language_enabled( string $language ): bool {
    return in_array($language, $this->get_languages('slug'));
  }

  /**
   * Get converted URLs for all languages, based on a given URL
   *
   * @param string $url
   * @return void
   */
  public function get_converted_urls( ?string $url = null ): array {
    $languages = $this->get_languages('slug');
    $urls = [];
    foreach( $languages as $lang ) {
      $urls[$lang] = $url ? $this->convert_url($url, $lang) : $this->convert_current_url($lang);
    }
    return $urls;
  }

  /**
   * Generate a language switcher for use in the frontend.
   *
   * @param array|null $args          An array with settings for your language switcher. Look at the wp_parse_args below
   *                                  to see the default settings.
   * 
   *                                  - format:
   *                                      - 'raw' (default): Returns an array
   *                                      - 'list': Returns HTML like this: <ul><li><a></li><li><a></li>...</ul>
   *                                      - 'list_items': Samle as 'list', but without the wrapping <ul> element
   *                                      - 'dropdown': Returns HTML like this: <select><option><option>...</select>
   *                                      - '$key:$value': Returns an array containing the contents of one column as keys and of another column as values
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
  public function get_language_switcher(?array $args = []) {
    static $dropdown_count = 0;
    static $list_count = 0;
    $args = $this->to_object(wp_parse_args($args, [
      'format' => 'raw',
      'display_names_as' => 'name',
      'hide_current' => false,
      'url' => null,
      'element_class' => 'acfml-language-switcher',
      // 'hide_if_no_translation' => true, // @TODO
    ]));
    $languages = $this->get_languages();
    $urls = $this->get_converted_urls($args->url);
    foreach( $languages as $key => &$language ) {
      $language['is_default'] = $this->is_default_language($language['slug']);
      $language['is_current'] = $language['slug'] === $this->get_current_language();
      $language['html_classes'] = [];
      if( $language['is_current'] ) $language['html_classes'][] = 'is-current-language';
      if( $this->is_default_language($language['slug']) ) $language['html_classes'][] = 'is-default-language';
      if( $args->hide_current && $language['is_current'] ) unset($languages[$key]);
      $this->debug = true;
      $language['url'] = $urls[$language['slug']];
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
          'languages_slugs_urls' => array_combine(array_column($languages, 'slug'), array_column($languages, 'url')),
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
   * @param string $key    e.g. 'en' or 'de'
   * @return array|null
   */
  public function get_language_info(string $key):? array {
    return $this->get_languages()[$key] ?? null;
  }

  /**
   * Get default language
   *
   * @param string
   */
  public function get_default_language(): string {
    $lang = $this->get_languages('slug')[0];
    return $lang;
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
   * @return void
   */
  public function detect_language(): void {
    // return early if language was already detected
    if( $this->language ) return;
    
    $language = $this->get_default_language();
    $lang_GET = $_GET['lang'] ?? '';

    if( wp_doing_ajax() && $lang_GET ) { // ajax requests: get language from GET parameter
      $language = $lang_GET;
    } elseif( is_admin() ) { // admin: get language from user setting
      $locale = determine_locale();
      $language = explode('_', $locale)[0];
    } else { // frontend: get language from URL
      $language = $this->get_language_in_url($this->get_current_url());
    }
    // reset to default language if the detected is not enabled
    if( !$this->is_language_enabled($language) ) $language = $this->get_default_language();
    // set the class property
    $this->language = $language;
    // set a constant containing the current language
    if( !defined('ACFML_CURRENT_LANGUAGE') ) define('ACFML_CURRENT_LANGUAGE', $language);
  }

  /**
   * Switch to a langauge
   *
   * @param string $language    the slug of the language, e.g. 'en' or 'de'
   * @return void
   */
  public function switch_to_language($language): bool {
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
  public function reset_language(): void {
    $this->language = defined('ACFML_CURRENT_LANGUAGE') ? ACFML_CURRENT_LANGUAGE : $this->get_default_language();
  }

  /**
   * Get language
   *
   * @return string
   */
  public function get_current_language(): string {
    $language = $this->language ?? $this->get_default_language();
    return $language;
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
  public function convert_url( ?string $url = null, ?string $requested_language = null ): string {
    
    // fill in defaults
    if( !$url ) $url = $this->get_current_url();
    if( !$requested_language ) $requested_language = $this->get_current_language();

    // bail early if this URL points towards the WP content directory
    if( strpos($url, content_url()) === 0 ) return $url;

    // Return a simple query arg language for admin urls
    if( strpos($url, admin_url()) !== false ) {
      return add_query_arg('lang', $requested_language, $url);
    }
    
    $untranslated_object_url = null;
    $translated_object_url = null;
    $wp_query = $this->resolve_url($url);

    if( $wp_query && $wp_object = $wp_query->get_queried_object() ) {

      if( $wp_object instanceof \WP_Post ) {
        /**
         * The url resolved to an object of type 'post'. Retrieve URLs for that
         */
        $untranslated_object_url = $this->post_types_controller->get_post_link($wp_object, $this->get_language_in_url($url));
        $translated_object_url = $this->post_types_controller->get_post_link($wp_object, $requested_language);

      } elseif( $wp_object instanceof \WP_Post_Type ) {
        /**
        * The url resolved to an object of type 'post_type'. Retrieve URLs for that
        */
        $untranslated_object_url = $this->post_types_controller->get_post_type_archive_link($wp_object->name, $this->get_language_in_url($url));
        $translated_object_url = $this->post_types_controller->get_post_type_archive_link($wp_object->name, $requested_language);

      }
    }

    if( $translated_object_url ) {
      /**
       * append any possible stuff from the original URL, like: 
       *    - https://your-site.com/[...]/custom-endpoint/
       *    - https://your-site.com/[...]?json=true&paged=2
       */
      if( $append_to_url = str_replace($untranslated_object_url, '', $url) ) {
        $translated_object_url .= $append_to_url;
      }
      return $translated_object_url;
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
    $current_language = $this->get_language_in_url($url);
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
  private function get_link_filters(): array {
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
        "post_link" => 10,
        "page_link" => 10,
        "post_type_link" => 10,
        "attachment_link" => 10,
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
  public function add_link_filters(): void {
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
  public function remove_link_filters(): void {
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
  public function convert_current_url($language): string {
    $url = $this->convert_url($this->get_current_url(), $language);
    return $url;
  }

  /**
   * Detect language information in URL
   *
   * @param string the detecte language
   */
  public function get_language_in_url($url): string {
    $url = untrailingslashit($url);
    $path = str_replace(home_url(), '', $url);
    $regex_languages = implode('|', $this->get_languages('slug'));
    preg_match("%/($regex_languages)(/|$|\?|#)%", $path, $matches);
    $language = $matches[1] ?? $this->get_default_language();
    return $language;
  }

  /**
   * Get current URL from $_SERVER
   *
   * @param string $url
   */
  private function get_current_url(): string {
    $url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
    return $url;
  }

  /**
   * Filter locale in frontend
   *
   * @param [type] $locale
   * @return string|null
   */
  public function filter_frontend_locale($locale): ?string {
    if( is_admin() ) return $locale;
    return $this->get_frontend_locale();
  }

  /**
   * Gets and converts the frontend locale
   *
   * @return string
   */
  private function get_frontend_locale(): string {
    return str_replace('_', '-', $this->get_language_info($this->get_current_language())['locale']);
  }

  /**
   * Prepend language information to all rewrite rules
   *
   * @link https://wordpress.stackexchange.com/a/238369/18713
   * @param array $rules
   * @return array
   */
  public function rewrite_rules_array($rules): array {
    $new_rules = array();

    // match /{locale} with or without trailing slash
    $regex_languages_home = implode('|', $this->get_languages('slug'));
    $new_rules["(?:$regex_languages_home)?/?$"] = 'index.php';

    // match /{locale}/my-object-slug or /my-object-slug
    // preserves the locale in slugs with form '{locale}myslug' 
    $regex_languages_pages = implode('\\W|', $this->get_languages('slug')) . '\\W';
    foreach ($rules as $key => $val) {
        $key = "(?:$regex_languages_pages)?/?" . ltrim($key, '^');
        $new_rules[$key] = $val;
    }
    return $new_rules;
  }

  /**
   * Hooks into wp_head and prints out hreflang tags
   *
   * @return void
   */
  public function wp_head(): void {
    $urls = $this->get_converted_urls();
    echo $this->get_template('meta-tags', [
      'urls' => $urls,
      'default_language' => $this->get_default_language()
    ]);
  }

  /**
   * Filter for 'the_content'
   *
   * @param string $value
   * @param string
   */
  public function format_acf_field_wysiwyg($value): string {
    return $this->convert_urls_in_string($value);
  }

  /**
   * Format ACF field 'Page Link'
   *
   * @param string|array|null $value
   * @return string|array|null
   */
  public function format_acf_field_page_link($value) {
    if( !$value ) return $value;
    // account for single values
    if( !is_array($value) ) return $this->convert_url($value);
    $value = array_map([$this, 'convert_url'], $value);
    return $value;
  }

  /**
   * Strip protocol from URL
   *
   * @param [type] $url
   * @return void
   */
  private function strip_protocol(string $url): string {
    return preg_replace('#^https?:#', '', $url);
  }

  /**
   * Convert URLs in Strings
   *
   * @param string $string
   * @param string
   */
  public function convert_urls_in_string(string $string, ?string $lang = null): string {
    $string = preg_replace_callback(
      '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', 
      function($matches) use ($lang) {
        $url = $matches[0];
        if( strpos($this->strip_protocol($url), $this->strip_protocol(home_url())) !== false ) {
          $url = $this->convert_url($url, $lang);
        }
        return $url;
      }, 
      $string
    );
    return $string;
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
  private function get_url_path(string $url): string {
    $path = str_replace(home_url(), '', $url);
    $path = explode('?', $path)[0];
    $path = trim($path, '/');
    // prepare the $path
    $path = rawurlencode( urldecode( $path ) );
    $path = str_replace( '%2F', '/', $path );
    $path = str_replace( '%20', ' ', $path );
    
    return $path;
  }

  /**
   * Uses built-in WP functionality to parse and query for any given internal URL
   *
   * @param string|null $url
   * @param string|null $language
   * @return \WP_Query|null
   */
  public function resolve_url(?string $url = null): ?\WP_Query {
    global $wp, $wp_the_query;
    
    // parse defaults
    $url = $url ?? $this->get_current_url();

    // get the path from the url, return early if none
    $path = $this->get_url_path($url);
    
    // bail early if no path found.
    if( !$path ) return null;

    // overwrite the language for the time of the request
    $language = $this->get_language_in_url($url);
    $this->switch_to_language($language);

    // cache $_SERVER
    $__SERVER = $_SERVER;
    // allow $wp->parse_request to do it's magic.
    $_SERVER['REQUEST_URI'] = $path;
    // bypasses checks for /wp-admin in $wp around line 274
    $_SERVER['PHP_SELF'] = 'index.php';

    // prevent rest api issues
    remove_action( 'parse_request', 'rest_api_loaded' );
    // create a new \WP instance
    $new_wp = new WP();

    // copy the (previously filtered) public query vars over from the main $wp object
    $new_wp->public_query_vars = $wp->public_query_vars;
    
    // parse the request, using the overwritten $_SERVER vars
    $new_wp->parse_request();
    $new_wp->build_query_string();

    // Reset $_SERVER
    $_SERVER = $__SERVER;
    
    // cache the query
    $_wp_the_query = $wp_the_query;
    // make a custom query
    $query = new \WP_Query();
    // set the new query to the global, so that it passes `is_main_query()`
    $wp_the_query = $query;
    // execute the custom query
    $query->query($new_wp->query_vars);
    // reset the global $wp_query
    $wp_the_query = $_wp_the_query;

    // reset the language
    $this->reset_language();
    
    return $query;
  }

  /**
   * Redirects the front-page to the preferred language
   *
   * @return void
   */
  public function redirect_front_page(): void {
    // allow deactivation
    if( !apply_filters('acfml/redirect_front_page', true) ) return;
    
    if( !is_front_page() || is_robots() ) return;

    $current_language = $this->get_current_language();
    $user_language = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));

    if( $_COOKIE['acfml-language'] ?? null ) return;
    
    if( !$this->is_language_enabled($user_language) ) $user_language = $this->get_default_language();
    
    if( $current_language === $user_language ) return;

    wp_redirect( user_trailingslashit($this->home_url('', $user_language)) );
    exit;
    
  }

  /**
   * Save the language in a cookie
   *
   * @return void
   */
  public function save_language_in_cookie(): void {
    if( is_admin() ) return;

    // allow deactivation
    if( !apply_filters('acfml/save_language_in_cookie', true) ) return;
    
    setcookie("acfml-language", $this->get_current_language(), time() + YEAR_IN_SECONDS, '/');
  }

  /**
   * Convert sitemap entries urls
   *
   * @param array $entry
   * @return array
   */
  public function sitemaps_index_entry( $entry ): array {
    $entry['loc'] = $this->simple_convert_url($entry['loc']);
    return $entry;
  }

  /**
   * Add a custom sitemaps provider
   *
   * @return void
   */
  public function add_sitemaps_provider(): void {
    if( !$this->current_language_is_default() ) return;
    $this->include('inc/class.sitemaps-provider.php');
    // registers the new provider for the sitemap
    $provider = new ACFML\ACFML_Sitemaps_Provider();
    wp_register_sitemap_provider( 'languages', $provider );
  }

  /**
   * Redirect some urls to the correct one
   *
   * @return void
   */
  public function redirect_canonical(): void {
    $url = $this->get_current_url();
    $converted_url = $this->convert_url($url);
    if( $url !== $converted_url ) {
      wp_redirect($converted_url);
      exit;
    }
  }

  /**
   * Add the current language to admin-ajax.php
   *
   * @param string $url
   * @param string $path
   * @param int $blog_id
   * @return string
   */
  public function convert_admin_ajax_url($url, $path, $blog_id): string {
    if( strpos($path, 'admin-ajax.php') === false ) return $url;
    $url = add_query_arg('lang', $this->get_current_language(), $url);
    return $url;
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
  if( isset($acfml) ) return $acfml;

  $acfml = new ACF_Multilingual();
  $acfml->initialize();

  return $acfml;
}

add_action('plugins_loaded', 'acfml'); // Instantiate

endif; // class_exists check