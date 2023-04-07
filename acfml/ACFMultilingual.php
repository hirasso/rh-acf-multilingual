<?php

namespace ACFML;

class ACFMultilingual {

  private $prefix = 'acfml';
  public $debug = false;
  private $language = null;
  private $languages = [];

  /**
   * Admin instance
   *
   * @var Admin
   */
  public $admin;

  /**
   * FieldsController instance
   *
   * @var FieldsController
   */
  public $fields_controller;

  /**
   * PostTypesController instance
   *
   * @var PostTypesController
   */
  public $post_types_controller;

  /**
   * TaxonomiesController instance
   *
   * @var TaxonomiesController
   */
  public $taxonomies_controller;

  /**
   * Config Instance
   *
   * @var Config
   */
  public $config;

  /**
   * Empty constructor
   */
  public function __construct(Config $config) {
    $this->config = $config;
  }

  /**
   * Intialize function. Instead of the constructor
   *
   * @return ACFMultilingual|null
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function initialize(): ?ACFMultilingual {

    // bail early if in WP CLI
    if( $this->is_wp_cli() ) return null;

    // Instanciate admin class
    $this->admin = new Admin($this);

    // Show notice if config is not loaded
    if( !$this->config->is_loaded() ) {
      $this->admin->add_notice_config_missing();
      return null;
    }

    // adds the languages
    $this->add_languages($this->config->languages);

    // bail early if there are no languages set
    if( empty($this->get_languages()) ) return null;

    // bail early if ACF is not defined
    if( !defined('ACF') ) return null;

    $this->download_language_packs();
    $this->detect_language();
    $this->load_textdomain();

    add_filter('locale', [$this, 'filter_frontend_locale']);

    // hook into after_setup_theme to fully initialize
    add_action('after_setup_theme', [$this, 'fully_initialize'], 10);
    add_filter('gettext', [$this, 'gettext_pick_language'], 10, 3);

    return $this;
  }

  /**
   * Fully initializes the plugin after the theme has been set up
   *
   * @return ACFMultilingual
   */
  public function fully_initialize(): ACFMultilingual {

    // Instanciate classes
    $this->fields_controller = new FieldsController($this);
    $this->post_types_controller = new PostTypesController($this);
    $this->taxonomies_controller = new TaxonomiesController($this);

    // run other functions
    $this->add_text_directions_to_languages();
    $this->admin->add_hooks();
    $this->add_hooks();

    return $this;
  }

  /**
   * Add multilingual object types
   *
   * @return void
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function add_multilingual_object_types() {
    if( $this->config->post_types ) $this->post_types_controller->add_post_types($this->config->post_types);
    if( $this->config->taxonomies ) $this->taxonomies_controller->add_taxonomies($this->config->taxonomies);
  }

  /**
   * Add filter and action hooks
   *
   * @return void
   */
  private function add_hooks() {
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_style']);
    add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array'], 999);

    add_action('wp_head', [$this, 'wp_head']);

    $this->add_link_filters();
    add_action('template_redirect', [$this, 'redirect_front_page'], 1);
    add_action('template_redirect', [$this, 'redirect_default_language_urls']);
    add_action('init', [$this, 'save_language_in_cookie']);
    add_action('init', [$this, 'add_multilingual_object_types'], 11);
    add_filter('language_attributes', [$this, 'language_attributes'], 10, 2);

    // ACF Field Filters
    add_filter('acf/format_value/type=wysiwyg', [$this, 'format_acf_field_wysiwyg'], 11);
    add_filter('acf/format_value/type=page_link', [$this, 'format_acf_field_page_link'], 11);
    add_filter('acf/format_value/type=link', [$this, 'format_acf_field_link'], 11);

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
   * Admin init
   *
   * @return array
   */
  public function download_language_packs(): array {
    $packs = [];
    /** WordPress Translation Installation API */
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/translation-install.php';
    foreach( $this->get_languages('full') as $language ) {
      $packs[] = wp_download_language_pack($language['locale']);
    }

    /**
     * After downloading new language packs, WP_Locale_Switcher needs
     * to be re-initialized, so that it can correctly store the
     * new result of get_available_languages()
     */
    global $wp_locale_switcher;
    $wp_locale_switcher = new \WP_Locale_Switcher();

    return $packs;
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
      'cookieHashForCurrentUri' => $this->get_cookie_hash_for_current_uri()
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
  public function get_cookie_hash_for_current_uri() {
    $uri = $_SERVER['REQUEST_URI'];
    $uri = remove_query_arg('message', $uri);
    return hash("sha256", $uri);
  }

  /**
   * Get an admin cookie
   *
   * @param string $key
   * @return object|null
   */
  public function get_admin_cookie( string $key ): ?object {
    $cookie_name = $key . "_" . $this->get_cookie_hash_for_current_uri();
    $cookie = $_COOKIE[$cookie_name] ?? null;
    return json_decode( stripslashes($cookie) );
  }

  /**
   * Get home url in requested or default language
   *
   * @param string $path
   * @param string|null $lang
   * @return string
   */
  public function home_url(string $path = '', ?string $lang = null): string {
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
    $uri = ACFML_URL . $path;
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
   * Add languages
   *
   * @param object|null $languages
   * @return void
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  private function add_languages(?object $languages): void {
    if( empty($languages) ) return;
    foreach( $languages as $key => $language ) {
      $this->add_language($key, $language->locale ?? $key, $language->name ?? null);
    }
  }

  /**
   * Adds text directions to languages
   *
   * @return void
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  private function add_text_directions_to_languages(): void {
    $this->languages = array_map(function($language){
      $language['dir'] = $this->get_text_direction($language['locale']);
      return $language;
    }, $this->get_languages());
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

    if( !$locale ) $locale = $slug;
    if( !$name ) $name = $slug;

    $language = [
      'slug' => $slug,
      'locale' => $locale,
      'name' => $name,
      'dir' => null
    ];

    $this->languages[$slug] = $language;
    return $language;
  }

  /**
   * Get the direction for a locale.
   *
   * Switching the locale seems to be an expensive operation,
   * so this is being stored in the language information when
   * being added
   *
   * @param string $locale
   * @return string
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function get_text_direction( string $locale ): string {
    $direction = 'ltr';

    $switched = switch_to_locale($locale);
    if( is_rtl() ) $direction = 'rtl';
    restore_previous_locale();

    return $direction;
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

    // Return false if there are less then two languages
    if( count($languages) < 2 ) return false;

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
   * @param string|null
   */
  public function get_default_language(): ?string {
    $lang = $this->get_languages('slug')[0] ?? null;
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
   * @return string|null
   */
  public function switch_to_language($language): ?string {
    $languages = $this->get_languages('slug');
    if( !in_array($language, $languages) ) return null;
    $this->language = $language;
    return $this->language;
  }

  /**
   * Resets the current language to the defined value
   *
   * @return string
   */
  public function reset_language(): string {
    $this->language = defined('ACFML_CURRENT_LANGUAGE') ? ACFML_CURRENT_LANGUAGE : $this->get_default_language();
    return $this->language;
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

    $url = $this->remove_default_language_from_url($url);

    // fill in defaults
    if( !$url ) $url = $this->get_current_url();
    if( !$requested_language ) $requested_language = $this->get_current_language();


    // bail early if the URL is not internal
    if( !$this->is_internal_url($url) ) return $url;

    // bail early if this URL points towards the WP content directory
    if( $this->url_starts_with($url, content_url()) ) return $url;

    // Return a simple query arg language for admin urls
    if( $this->url_starts_with($url, admin_url()) ) {
      return add_query_arg('lang', $requested_language, $url);
    }

    // bail early if the URL already is in the requested language
    if( $this->get_language_in_url($url) === $requested_language ) return $url;

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
      if(
        strpos($url, $untranslated_object_url) === 0 &&
        ( $append_to_url = str_replace($untranslated_object_url, '', $url) )
        ) {
        $translated_object_url .= $append_to_url;
      }
      return $translated_object_url;
    }

    // if nothing special was found, only inject the language code
    return $this->simple_convert_url($url, $requested_language);
  }

  /**
   * Converts e.g. https://site.com/{default_language_slug}/my-slug/ to https://site.com/my-slug/
   * (the same URL without the default language slug, if present)
   *
   * @param string $url
   * @return string
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  private function remove_default_language_from_url(string $url): string {
    if( !$this->is_default_language($this->get_language_in_url($url)) ) return $url;
    $default_language = $this->get_default_language();
    $preg_home_url = preg_quote($this->strip_protocol(home_url("/$default_language")), '@');
    $pattern = "@https?:$preg_home_url(?:\/|$)@";
    $url = preg_replace($pattern, \home_url("/"), $url);
    return $url;
  }

  /**
   * Simply replaces the language code in an URL, or strips it for the default language
   *
   * @param string $url
   * @param string $requested_language
   * @return string
   */
  public function simple_convert_url( string $url, string $requested_language = null ): string {
    $url = $this->remove_default_language_from_url($url);
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
        "redirect_canonical" => 10, // only fires if WordPress has detected a canonical conflict, e.g. ?p=225 with pretty permalinks active will redirect to the pretty URL
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
   * @param string|null the detected language
   */
  public function get_language_in_url($url): ?string {
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
   * Filter ACF value for field type 'link'
   *
   * @param string|array $value
   * @return string|array
   */
  public function format_acf_field_link($value) {
    if( empty($value) ) return;
    // handle return type 'array'
    if( !empty($value['url']) ) {
      $value['url'] = $this->convert_url($value['url']);
      return $value;
    }
    // handle return type 'url'
    if( is_string($value) ) return $this->convert_url($value);

    return $value;
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
        return $this->convert_url($url, $lang);
      },
      $string
    );
    return $string;
  }

  /**
   * Checks if an URL is internal
   *
   * @param string $url
   * @return boolean
   */
  private function is_internal_url( string $url ): bool {

    if( !$this->url_starts_with($url, home_url()) ) return false;

    if( $this->url_points_to_physical_location($url) ) return false;

    return true;
  }

  /**
   * Checks if an URL starts with another URL
   *
   * Protocol agnostic. e.g.:
   * "http://my-site.com/my-path/ > "https://my-site.com/" resolves to true
   *
   * @param string $haystack_url
   * @param string $needle_url
   * @return boolean
   */
  private function url_starts_with(string $haystack_url, string $needle_url): bool {
    return $this->string_starts_with(set_url_scheme($haystack_url, 'http'), set_url_scheme($needle_url, 'http'));
  }

  /**
   * Tests if a string starts with a sub-string
   *
   * @param string $string
   * @param string $sub_string
   * @return boolean
   */
  private function string_starts_with(string $string, string $sub_string): bool {
    return stripos($string, $sub_string) === 0;
  }

  /**
   * Tests if an URL points to a dir or file on the server
   *
   * @param string $url
   * @return boolean
   */
  private function url_points_to_physical_location(string $url): bool {

    $path_from_home = $this->get_path_from_home($url);
    // bail early if the path is empty
    if( empty($path_from_home) ) return false;

    // return true if a file exists on the absolute path location
    $document_root = dirname($_SERVER['SCRIPT_FILENAME']);
    return file_exists("$document_root/$path_from_home");
  }

  /**
   * Get path relative to home_url
   *
   * – removes home url
   * – removes query
   * – removes leading and trailing slashes
   *
   * @param string $url
   * @param string
   */
  private function get_path_from_home(string $url): string {
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
  private function resolve_url(?string $url = null): ?\WP_Query {
    global $wp, $wp_the_query;

    // parse defaults
    $url = $url ?? $this->get_current_url();

    // get the path from the url, return early if none
    $path = $this->get_path_from_home($url);

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
    $new_wp = new \WP();

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

    $user_language = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? strtolower(substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2)) : null;
    if (!$user_language) return;

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
    // registers the new provider for the sitemap
    $provider = new SitemapsProvider($this);
    wp_register_sitemap_provider( 'languages', $provider );
  }

  /**
   * Redirect some urls to the correct one
   *
   * @return void
   */
  public function redirect_default_language_urls(): void {

    $url = $this->get_current_url();

    // bail early for URLs that are not in the default language
    if( !$this->is_default_language($this->get_language_in_url($url)) ) return;

    // get the clean URL (without e.g. [...]/{default_language_slug}/)
    $redirect_url = $this->remove_default_language_from_url($url);

    // redirects URLs like https://my-site.com/{default_language_slug}/my-post/ to https://my-site.com/my-post/
    if( $url !== $redirect_url ) {
      wp_redirect($redirect_url);
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


  /**
   * Get hashed settings
   *
   * @return string
   */
  public function get_hashed_settings(): string {
    $settings = [
      'languages' => $this->get_languages('slug'),
      'post_types' => $this->post_types_controller->get_multilingual_post_types('full', false)
    ];
    $hash = hash( "sha512", json_encode($settings) );
    return $hash;
  }

  /**
   * Detect if the settings of ACFML have changed
   *
   * @param string $postfix
   * @return boolean
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function settings_have_changed(string $postfix): bool {
    $hashed_settings = $this->get_hashed_settings();
    // uncomment to debug
    // delete_option("acfml_hashed_settings_$postfix");
    return !hash_equals($hashed_settings, (string) get_option("acfml_hashed_settings_$postfix"));
  }

  /**
   * Saves a hash of the current settings in the database
   *
   * @param string $postfix
   * @return void
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function save_hashed_settings(string $postfix): void {
    // update settings hash
    update_option("acfml_hashed_settings_$postfix", $this->get_hashed_settings());
  }

  /**
   * Converts an object to an array
   *
   * @param [object] $object
   * @return array
   */
  public function to_array( $object ) {
    if( !$object || $object === true ) return [];
    return json_decode( json_encode( $object ), true );
  }

  /**
   * Detect if running WP-CLI
   *
   * @return boolean
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  private function is_wp_cli(): bool {
    return defined( 'WP_CLI' ) && WP_CLI;
  }

  /**
   * Logs custom messages to debug.log
   *
   * @param [type] $log
   * @param string $log_file
   * @return void
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function log( $log, $log_file = 'debug.log' ) {
    if( is_array($log) || is_object($log) ) {
      $log = print_r($log, true);
    }
    $dir = WP_CONTENT_DIR;
    if (!is_dir($dir)) mkdir($dir);
    $path = "$dir/$log_file";
    file_put_contents($path, "\n$log", FILE_APPEND);
  }

  /**
   * Filter the language attributes to possibly add 'dir="ltr"'
   *
   * Enables full support for postcss-logical and postcss-dir-pseudo-class
   *
   * @param string $output
   * @param string $doctype
   * @return string
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function language_attributes(string $output, string $doctype): string {
    if( $doctype !== 'html' ) return $output;
    if( function_exists( 'is_rtl' ) && is_rtl() ) return $output;
    if( strpos($output, 'dir=') !== false ) return $output;
    return "dir=\"ltr\" $output";
  }

  /**
   * Convert qtranslate-like strings to the current language:
   *
   *  - [:de]Website Durchsuchen[:en]Search Website[:]
   *
   * @param string $translation
   * @param string $text
   * @param string $domain
   * @return string
   */
  public function gettext_pick_language(string $translation, string $text, string $domain): string {
    $current_language = $this->get_current_language();
    preg_match("/\[:$current_language](?P<translation>.+?)(?=(?:\[:|$))/", $translation, $matches);
    return $matches['translation'] ?? $translation;
  }

}
