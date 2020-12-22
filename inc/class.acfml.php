<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once(__DIR__ . '/class.multilingual-fields.php');
require_once(__DIR__ . '/class.admin.php');
require_once(__DIR__ . '/class.multilingual-post-types.php');
require_once(__DIR__ . '/class.multilingual-taxonomies.php');

class ACF_Multilingual extends Singleton {

  private $prefix = 'acfml';
  private $debug = false;
  private $languages = null;

  public function init() {
    
    Admin::getInstance();
    Multilingual_Fields::getInstance();
    Multilingual_Post_Types::getInstance();
    Multilingual_Taxonomies::getInstance();

    add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('admin_init', [$this, 'admin_init'], 11);
    add_action('plugins_loaded', [$this, 'detect_current_language']);
    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array'], PHP_INT_MAX);
    // add_action('init', [$this, 'flush_rewrite_rules'], PHP_INT_MAX);
    add_filter('locale', [$this, 'filter_frontend_locale']);
    add_action('template_redirect', [$this, 'redirect_default_language']);
    add_action('wp_head', [$this, 'wp_head']);
    add_action('request', [$this, 'prepare_request']);
    
    
    $this->add_link_filters();
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
    foreach( $languages as &$language ) {
      $language['is_default'] = $language['slug'] === $this->get_default_language();
    }
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
   *                                      - 'naked_list': Samle as 'list', but without the wrapping <ul> element
   *                                      - 'dropdown': Returns HTML like this: <select><option><option>...</select>
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
  public function get_language_switcher(?array $args = []) {
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
      $language['is_current'] = $language['slug'] === $this->get_current_language();
      $language['element_classes'] = [];
      if( $language['is_current'] ) $language['element_classes'][] = 'is-current-language';
      if( $language['is_default'] ) $language['element_classes'][] = 'is-default-language';
      if( $args->hide_current && $language['is_current'] ) unset($languages[$key]);
      $language['url'] = $args->url ? $this->convert_url($args->url, $language['slug']) : $this->convert_current_url($language['slug']);
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
    $languages = array_values($languages);
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
      case 'naked_list':
        $list_count ++;
        return $this->get_template('language-switcher-list', [
          'languages' => $languages,
          'element_class' => $args->element_class,
          'element_id' => "acfml-language-list-$list_count",
          'args' => $args,
        ]);
        break;
    }
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
  public function get_default_language() {
    return apply_filters("$this->prefix/default_language", 'en');
  }

  /**
   * Set current language
   *
   * @param string
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
   * @param string
   */
  public function get_current_language() {
    return $this->language ?: $this->get_default_language();
  }

  /**
   * Convert an URL for a language
   *
   * @param string $url
   * @param string $language
   * 
   * @return string $url
   */
  public function convert_url(string $url, string $requested_language = null): string {
    
    if( !$requested_language ) $requested_language = $this->get_current_language();
    // bail early if this URL points towards the WP content directory
    if( strpos($url, content_url()) === 0 ) return $url;
    // get language from requested URL
    $language_in_url = $this->get_language_in_url($url);
    if( !$language_in_url ) $language_in_url = $this->get_default_language();

    // if this is a post type archive page, get the url from the post type
    if( $post_type = $this->get_post_type_archive_by_path($this->get_path($url), $language_in_url) ) {
      return $this->get_post_type_archive_link($post_type, $requested_language);
    }
    // if this is a URL for a post, get the url from this
    if( $post = $this->get_post_by_path($this->get_path($url), $language_in_url) ) {
      return $this->get_post_link($post, $requested_language);
    } 
    // if nothing special was found, return a 'dumb' converted url
    $current_home_url = $this->home_url('', $language_in_url);
    $new_home_url = $this->home_url('', $requested_language);
    $url = str_replace($current_home_url, $new_home_url, $url);
    return $url;
  }

  private function get_link_filters() {
    $filters = [
      "author_feed_link" => 10,
      "author_link" => 10,
      "get_comment_author_url_link" => 10,
      "post_comments_feed_link" => 10,
      "day_link" => 10,
      "month_link" => 10,
      "year_link" => 10,
      "page_link" => 10,
      "post_link" => 10,
      "category_link" => 10,
      "category_feed_link" => 10,
      "tag_link" => 10,
      "term_link" => 10,
      "the_permalink" => 10,
      "feed_link" => 10,
      "tag_feed_link" => 10,
      "get_shortlink" => 10,
      "rest_url" => 10,
      "post_type_link" => 10,
    ];
    /**
    * Filter the Links that should be converted. 
    * Should return an array like this:
    * 
    * array(
    *  "wp_filter_name" => priority,
    *  "another_wp_filter_name" => priority,
    * )
    */
    return apply_filters("acfml/link_filters", $filters);
  }

  /**
   * Add Link filters
   *
   * @return void
   */
  private function add_link_filters() {
    foreach( $this->get_link_filters() as $filter_name => $priority ) {
      add_filter($filter_name, [$this, 'convert_url'], intval($priority));
    }
  }

  /**
  * Remove Link filters
  *
  * @return void
  */
  private function remove_link_filters() {
    foreach( $this->get_link_filters() as $filter_name => $priority ) {
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
    $this->debug = true;
    $url = $this->convert_url($this->get_current_url(), $language);
    $this->debug = false;
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
    if( !$this->is_frontend() ) return $locale;
    return str_replace('_', '-', $this->get_language_info($this->get_current_language())['locale']);
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
    $regex_languages = implode('|', $this->get_languages('slug'));
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
      // $language['url'] = $this->convert_current_url($language['slug']);
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
  public function prepare_request($vars) {
    
    if( is_admin() ) return $vars;
    // do nothing for default language
    if( $this->get_current_language() === $this->get_default_language() ) return $vars;
    $language = $this->get_current_language();
    $path = $this->get_path($this->get_current_url());

    if( $post = $this->get_post_by_path($path, $language) ) {
      $vars['post_type'] = $post->post_type;
      $vars['p'] = $post->ID;
      unset($vars['attachment']);
      unset($vars['name']);
      unset($vars['pagename']);
      unset($vars[$post->post_type]);
      unset($vars['error']);
      
      // @TODO check if there are cases where we have to fix canonical redirects
      // remove_action('template_redirect', 'redirect_canonical');
    }

    return $vars;
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
    $string = preg_replace_callback('/href=[\'|\"](http.*?)[\'|\"]/', function($matches) {
      $url = $matches[1];
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
   * – removes home url and language
   * – removes query
   * – removes leading and trailing slashes
   * 
   * @param string $url
   * @param string
   */
  private function get_path(String $url): string {
    $path = str_replace(home_url(), '', $url);
    $regex_languages = implode('|', $this->get_languages('slug'));
    $path = preg_replace("%/($regex_languages)(/|$|\?|#)%", '', $path);
    $path = explode('?', $path)[0];
    $path = trim($path, '/');
    // prepare the $path
    $path     = rawurlencode( urldecode( $path ) );
    $path     = str_replace( '%2F', '/', $path );
    $path     = str_replace( '%20', ' ', $path );
    return $path;
  }

  /**
   * Get a post by a URL path
   *
   * @param string $path
   * @param string|null $language
   * @return \WP_Post|null
   */
  public function get_post_by_path(string $path, ?string $language = null): ?\WP_Post {
    global $wp_rewrite;

    if( !$path ) return null;

    // setup variables
    if( !$language ) $language = $this->get_current_language();
    $meta_key = "{$this->prefix}_slug_{$language}";
    $post_type = 'post';
    $post = null;
    $post_parent = 0;
    
    // check cache
    $last_changed = wp_cache_get_last_changed( 'posts' );
    $hash         = md5( $path . serialize( $post_type ) );
    $cache_key    = "$this->prefix:get_post_by_path:$hash:$last_changed:$language";
    
    // wp_cache_delete($cache_key, 'posts' );
    $cached       = wp_cache_get( $cache_key, 'posts' );
    if ( false !== $cached ) {
      // Special case: '0' is a bad `$page_path`.
      if ( '0' === $cached || 0 === $cached ) {
        return null;
      } else {
        return get_post( $cached );
      }
    }

    $query_vars = [];
    foreach ( (array) $wp_rewrite->wp_rewrite_rules() as $match => $query ) {
      if( preg_match( "#^$match#", $path, $matches ) ) {
        $matched_rule = $match;
        break;
      }
    }
    if( isset($matched_rule) ) {
      // Trim the query of everything up to the '?'.
      $query = preg_replace( '!^.+\?!', '', $query );
      // Substitute the substring matches into the query.
      $query = addslashes( \WP_MatchesMapRegex::apply( $query, $matches ) );
      // parse the query string
      $query_vars = wp_parse_args($query);
    }
    // pre_dump($query_vars); // @TODO: From here on we have valid query vars!!! PAARTY PAAAAARTY!!!

    $custom_post_types = array_keys(get_post_types([
      'public' => true,
      '_builtin' => false,
    ]));
    foreach( $custom_post_types as $pt ) {
      $pt_object = get_post_type_object($pt);
      $rewrite_slug = $pt_object->rewrite['slug'] ?? $pt;
      if( isset($query_vars[$rewrite_slug]) ) {
        $post_type = $pt;
      }
    }
    
    $segments = [];
    if( isset($query_vars[$post_type]) ) {  // custom post type
      $segments = explode('/', $query_vars[$post_type]);
    } elseif( isset($query_vars['pagename']) ) {  // post type 'page'
      $post_type = 'page';
      $segments = explode('/', $query_vars['pagename']);
    } elseif( isset($query_vars['name']) ) { // post type 'post'
      $segments = [$query_vars['name']];
    } 
    // prepare the path segments
    $segments = array_map( 'sanitize_title_for_query', $segments );

    // Look for posts
    if( count($segments) ) {

      // first, check if only one post is there for the last $segment.
      // e.g. /mum/child/grandchild: Check if only one post with a slug of 'grandchild' exists globally
      $posts = get_posts([
        'post_type' => $post_type,
        'meta_key' => $meta_key,
        'posts_per_page' => 2,
        'meta_value' => $segments[count($segments)-1],
        'post_status' => ['publish', 'future', 'private'] // @TODO check if this won't expose future or private posts
      ]);

      // we have been looking for two $posts with the same slug.
      // if exatly 1 $post has been found, use that one.
      if( count($posts) < 2 ) { 
        $post = array_shift($posts);
      } else {
        // if multiple posts have the same slug, walk the tree.    
        foreach($segments as $segment) {
          $posts = get_posts([
            'post_type' => $post_type,
            'post_parent' => $post_parent,
            'posts_per_page' => 1,
            'meta_key' => $meta_key,
            'meta_value' => $segment,
            'post_status' => ['publish', 'future', 'private'] // @TODO check if this won't expose future or private posts
          ]);
          if( $post = array_shift($posts) ) {
            $post_parent = $post->ID;
          } else {
            break;
          }
        }
      }

    }

    wp_cache_set( $cache_key, $post->ID ?? 0, 'posts' );
    
    return $post;
  }

  /**
   * Get a post type 
   *
   * @param String $path
   * @param String|null $language
   * @return String|null
   */
  public function get_post_type_archive_by_path(String $path, ?String $language = null): ?string {
    // prepare the path segments
    $segments = explode( '/', trim( $path, '/' ) );
    $segments = array_map( 'sanitize_title_for_query', $segments );
    // loop through all post types and 
    foreach( get_post_types() as $post_type ) {
      if( !is_post_type_viewable($post_type) ) continue;
      if( $this->get_post_type_archive_slug($post_type, $language) === $segments[0] ) return $post_type;
    }
    return null;
  }

  /**
   * Get the archive slug for a post type
   *
   * @param string $post_type
   * @param string $language
   * @return string|null
   */
  public function get_post_type_archive_slug( string $post_type, string $language ): ?string {
    $post_type_object = get_post_type_object($post_type);
    if( !$post_type_object || !$post_type_object->has_archive ) return null;
    $default_archive_slug = is_string($post_type_object->has_archive) ? $post_type_object->has_archive : $post_type;
    return $post_type_object->acfml[$language]['archive_slug'] ?? $default_archive_slug;
  }

  /**
   * Get translated permalink for a post
   *
   * @param \WP_Post $post
   * @param string $language
   * @param string
   */
  public function get_post_link( \WP_Post $post, String $language ): string {
    global $wp_rewrite;

    $meta_key = "{$this->prefix}_slug_{$language}";
    $post_type_object = get_post_type_object($post->post_type);
    $ancestors = array_reverse(get_ancestors($post->ID, $post->post_type, 'post_type'));
    $segments = [];
    $url = '';

    // if the post is the front page, return home page in requested language
    if( $post->ID === intval(get_option('page_on_front')) ) return $this->home_url('/', $language);

    $this->remove_link_filters();
    $default_permalink = get_permalink($post);
    $original_post_uri = get_page_uri($post);
    $this->add_link_filters();

    if( $language === $this->get_default_language() ) {
      return $default_permalink;
    }

    // add possible custom post type's rewrite slug and front to segments
    $default_rewrite_slug = $post_type_object->rewrite['slug'] ?? null;
    $acfml_rewrite_slug = ($post_type_object->acfml[$language]['rewrite_slug']) ?? null;
    if( $rewrite_slug = $acfml_rewrite_slug ?: $default_rewrite_slug ) {
      $segments[] = $rewrite_slug;
    }

    // add slugs for all ancestors to segments
    foreach( $ancestors as $ancestor_id ) {
      $ancestor = get_post($ancestor_id);
      $segments[] = $this->get_field_or($meta_key, $ancestor->post_name, $ancestor_id);
    }

    // add slug for requested post to segments
    $segments[] = $this->get_field_or($meta_key, $post->post_name, $post->ID);
    
    $path = user_trailingslashit(implode('/', $segments));
    $url = $this->home_url("/$path", $language);
    return $url;
  }

  /**
   * Get post type archive url for a language
   *
   * @param string $post_type_object
   * @param string $language
   * @return string|null
   */
  private function get_post_type_archive_link( string $post_type, string $language ): ?string {

    remove_filter('post_type_archive_link', [$this, 'convert_url']);
    $link = get_post_type_archive_link($post_type);
    $path = trim(str_replace(home_url(), '', $link), '/');
    add_filter('post_type_archive_link', [$this, 'convert_url']);

    $default_archive_slug = $this->get_post_type_archive_slug($post_type, $this->get_default_language());
    $archive_slug = $this->get_post_type_archive_slug($post_type, $language);
    if( !$archive_slug ) return $link;

    $path = preg_replace("#$default_archive_slug$#", $archive_slug, $path);
    $path = user_trailingslashit($path);
    $link = $this->home_url("/$path", $language);
    $link = apply_filters('acfml/post_type_archive_link', $link, $post_type, $language);
    return $link;
  }
  
}