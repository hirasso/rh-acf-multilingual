<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ACFML extends Singleton {

  private $prefix = 'acfml';
  private $debug = false;

  public function __construct() {
    
    add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('admin_init', [$this, 'admin_init'], 11);
    add_action('admin_notices', [$this, 'show_admin_notices']);
    add_action('plugins_loaded', [$this, 'detect_current_language']); // maybe a better plpace is 'request' ?
    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array'], PHP_INT_MAX);
    // add_action('init', [$this, 'flush_rewrite_rules'], PHP_INT_MAX);
    add_filter('locale', [$this, 'filter_frontend_locale']);
    add_action('template_redirect', [$this, 'redirect_default_language']);
    add_action('wp_head', [$this, 'wp_head']);
    add_action('admin_head', [$this, 'admin_head']);
    // add_filter('pre_get_posts', [$this, 'prepare_query']);
    // add_action('request', [$this, 'filter_request'], 5);
    
    add_filter('page_link', [$this, 'page_link'], 10, 3);
    add_filter('post_link', [$this, 'post_link'], 10, 3);
    add_filter('post_type_link', [$this, 'post_type_link'], 10, 3);
    add_filter('term_link', [$this, 'term_link'], 10, 3);


    // add_filter('posts_join', function($join, $query) {
    //   if( $query->is_single() ) {
    //     pre_dump(['join' => $join]);
    //   }
    //   return $join;
    // }, 10, 2);

    // add_filter('posts_where', function($where, $query) {
    //   if( is_admin() ) return $where;
    //   if( $query->is_single() ) {
    //     pre_dump(['where' => $where]);
    //   }
    //   return $where;
    // }, 10, 2);
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
   * @return String
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
   * @param String|Null $lang
   * @return String
   */
  public function home_url($lang = null) {
    $home_url = home_url();
    if( !$lang ) $lang = $this->get_current_language();
    if( $lang === $this->get_default_language() ) return $home_url;
    return "$home_url/$lang";
  }

  /**
   * Helper function to get versioned asset urls
   *
   * @param String $path
   * @return String
   */
  private function asset_uri( $path ) {
    $uri = plugins_url( $path, __DIR__ );
    $file = $this->get_plugin_path( $path );
    if( file_exists( $file ) ) {
      $version = filemtime( $file );
      $uri .= "?v=$version";
    }
    return $uri;
  }

  /**
   * Helper function to get a file path inside this plugin's root folder
   *
   * @return void
   */
  function get_plugin_path( $path ) {
    $path = ltrim( $path, '/' );
    $file = dirname(__DIR__) . "/$path";
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
    $path = $this->get_plugin_path("templates/$template_name.php");
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
   * @param String $format    'full' or 'iso'
   * @return Array
   */
  public function get_languages( $format = 'full' ) {
    $languages = [
      [
        'iso' => 'en',
        'locale' => 'en_US',
        'name' => 'English'
      ],
      [
        'iso' => 'de',
        'locale' => 'de_DE',
        'name' => 'Deutsch'
      ],
      [
        'iso' => 'fi',
        'locale' => 'fi',
        'name' => 'Finski'
      ],
    ];
    $default_language = $this->get_default_language();
    foreach( $languages as &$language ) {
      $language['is_default'] = $language['iso'] === $default_language;
    }
    if( $format === 'iso' ) $languages = array_column($languages, 'iso');
    return apply_filters("$this->prefix/languages", $languages);
  }

  /**
   * Get information for a language iso key
   *
   * @param String $lang_iso    e.g. 'en' or 'de'
   * @return Mixed
   */
  private function get_language_information($lang_iso) {
    foreach( $this->get_languages() as $language ) {
      if( $language['iso'] === $lang_iso ) return $language;
    }
    return null;
  }

  /**
   * Get default language
   *
   * @return String
   */
  public function get_default_language() {
    return apply_filters("$this->prefix/default_language", 'en');
  }

  /**
   * Set current language
   *
   * @return String
   */
  public function detect_current_language() {
    if( $this->is_frontend() ) {
      $language = $this->get_language_in_url($this->get_current_url());
      if( !$language ) $language = $this->get_default_language();
    } else {
      $language = $this->get_admin_language();
    }
    $this->language = $language;
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
   * @return String
   */
  public function get_current_language() {
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
    if( !$language_in_url ) $language_in_url = $this->get_default_language();
    $current_home_url = $this->home_url($language_in_url);
    $new_home_url = $this->home_url($requested_language);
    $url = str_replace($current_home_url, $new_home_url, $url);
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
    $regex_languages = implode('|', $this->get_languages('iso'));
    preg_match("%/($regex_languages)(/|$|\?|#)%", $path, $matches);
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
    return str_replace('_', '-', $this->get_language_information($this->get_current_language())['locale']);
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
    global $pagenow;
    return $url;
    // don't filter the $url if probably saving to .htaccess
    // if ( function_exists( 'save_mod_rewrite_rules' ) ) return $url;
    // dont't filter on some admin pages at all
    if( in_array($pagenow, ['options-permalink.php']) ) return $url;
    $url = $this->convert_url($url, $this->get_current_language());
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
    $new_rules = array();
    $regex_languages = implode('|', $this->get_languages('iso'));
    $new_rules["(?:$regex_languages)?/?$"] = 'index.php';

    foreach ($rules as $key => $val) {
        $key = "(?:$regex_languages)?/?" . ltrim($key, '^');
        $new_rules[$key] = $val;
    }

    return $new_rules;
  }


  /**
   * Redirects URLs for default language to language-agnostic URLs
   * e.g. https://my-site.com/en/my-post/ to https://my-site.com/my-post/
   *
   * @return void
   */
  public function redirect_default_language() {
    $language_in_url = $this->get_language_in_url($this->get_current_url()); 
    if( $language_in_url && $language_in_url === $this->get_default_language() ) {
      $new_url = $this->convert_url($this->get_current_url(), $this->get_default_language());
      wp_redirect($new_url);
      exit;
    }
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
      $language['url'] = $this->convert_current_url($language['iso']);
    }
    echo $this->get_template('meta-tags', [
      'languages' => $languages,
    ]);
  }

  /**
   * Filter the request (inspired by qtranslate-slug)
   *
   * @param Array $query
   * @return void
   */
  public function filter_request($query) {
    global $wp;
    
    if( is_admin() ) return $query;
    pre_dump($query);
    $save_query = $query;
    if( isset($wp->matched_query) || empty($query) ) {
      $query = wp_parse_args($wp->matched_query, $save_query);
    }

    $custom_post_type = null;

    // Adds custom post types to the query if present
    foreach (get_post_types() as $post_type) {
      if ( array_key_exists($post_type, $query) && !in_array($post_type, array('post', 'page') ) ) {
        $custom_post_type = $post_type;
      }
    }

    $language = $this->get_current_language();
    // return early if default language
    if( $language === $this->get_default_language() ) return $query;

    // alter query for custom post types
    if ( $custom_post_type && $post = $this->get_post_by_slug($custom_post_type, $language, $query[$custom_post_type]) ) {
      $query[$custom_post_type] = $post->post_name;
      $query['name'] = $post->post_name;
    } elseif ( isset($query['name']) ) { // Built-in posts
      if( $post = $this->get_post_by_slug(['page', 'post'], $language, $query['name']) ) {
        $query['name'] = $post->post_name;
      }
    }

    return $query;
  }

  /**
   * Get a post by it's custom slug.
   *
   * @param String $post_type
   * @param String $language
   * @param String $slug
   * @return WP_Post|Null
   */
  private function get_post_by_slug($post_type, $language, $slug ) {
    $args = [
      "post_type" => $post_type,
      "meta_query" => [
        [
          "key" => "slug_$language",
          "value" => $slug
        ],
      ],
      'posts_per_page' => 1
    ];
    $posts = get_posts($args);
    return count($posts) ? array_shift($posts) : null;
  }

  /**
   * Filters a page link (for built-in post type 'page')
   *
   * @param String $link
   * @param Int $post_id
   * @param Boolean $sample
   * @return String
   */
  public function page_link($link, $post_id, $sample) {
    return $this->convert_url($link, $this->get_current_language());
  }

  /**
   * Filters a post link (for built-in posts)
   *
   * @param String $link
   * @param Int $post_id
   * @param Boolean $sample
   * @return String
   */
  public function post_link($link, $post_id, $sample) {
    
    return $this->convert_url($link, $this->get_current_language());
  }

  /**
   * filters a post type link
   *
   * @param String $link
   * @param WP_Post $post
   * @param Boolean $leavename
   * @return String
   */
  public function post_type_link($link, $post, $leavename) {
    return $this->convert_url($link, $this->get_current_language());
  }

  /**
   * filters a term link
   *
   * @param String $link
   * @param WP_Term $term
   * @param String $taxonomy
   * @return String
   */
  public function term_link($link, $term, $taxonomy) {
    return $this->convert_url($link, $this->get_current_language());
  }
  
}