<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Multilingual_Post_Types extends Singleton {
  
  private $prefix;
  private $default_language;
  private $field_postfix = "post_title";
  private $title_field_name;
  private $field_field_key;

  private $slug_field_name;
  private $slug_field_key;

  public function __construct() {
    add_action('acf/init', [$this, 'init']);
  }

  /**
   * Init hook
   *
   * @return void
   */
  public function init() {
    // variables
    $this->prefix = acfml()->get_prefix();
    $this->default_language = acfml()->get_default_language();
    $this->title_field_name = "{$this->prefix}_{$this->field_postfix}";
    $this->title_field_key = "field_{$this->title_field_name}";
    $this->field_group_key = "group_{$this->title_field_name}";

    $this->slug_field_name = "{$this->prefix}_slug";
    $this->slug_field_key = "field_$this->slug_field_name";

    // hooks
    add_filter('the_title', [$this, 'filter_post_title'], 10, 2);
    add_filter('admin_body_class', [$this, 'admin_body_class'], 20);
    // add_action("acf/render_field/key={$this->title_field_key}", [$this, 'render_field']);
    add_action("acf/load_value/key={$this->title_field_key}_{$this->default_language}", [$this, "load_default_value"], 10, 3);
    add_action('wp_insert_post_data', [$this, 'wp_insert_post_data'], 10, 2);

    // add_filter('acf/update_value/key=field_acfml_slug_en', [$this, 'update_post_slug'], 10, 3);
    // add_filter('acf/update_value/key=field_acfml_slug_de', [$this, 'update_post_slug'], 10, 3);
    add_action('acf/save_post', [$this, 'save_post_slugs'], 11);


    // methods
    $this->setup_acf_fields();

    add_action('init', [$this, 'add_rewrite_rules'], 11);
    
  }

  /**
   * Get multilingual post types
   *
   * @return Array
   */
  private function get_multilingual_post_types() {
    return array_unique( apply_filters("acfml/multilingual_post_types", []) );
  }
  
  /**
   * Get multilingual CUSTOM (not _builtin) post types
   *
   * @return array
   */
  private function get_multilingual_custom_post_types():array {
    $builtin = get_post_types([
      'public' => true,
      '_builtin' => true
    ]);
    $post_types = $this->get_multilingual_post_types();
    foreach( $post_types as $key => $type ) {
      if( in_array($type, $builtin) ) unset($post_types[$key]);
    }
    return $post_types;
  }

  /**
   * Make custom post types rewrite rules multilingual
   *
   * @return void
   */
  public function add_rewrite_rules() {
    
    add_filter('rewrite_rules_array', function($rules) {
      
      foreach( $this->get_multilingual_custom_post_types() as $post_type ) {

        $rules = $this->multilingual_rewrite_slugs($rules, $post_type);
        $rules = $this->multilingual_archive_slugs($rules, $post_type);

      }
      return $rules;
    });
    
  }

  /**
   * Makes post type archives multilingual. Looks for the custom property acfml>archive_slug in the post type object
   * and adds possibly found slug translations to the regex
   *
   * @param Array $rules
   * @param String $post_type
   * @return Array
   */
  private function multilingual_archive_slugs(Array $rules, String $post_type): array{
    $pt_object = get_post_type_object( $post_type );
    $has_archive = $pt_object->has_archive ?? null;
    if( !$has_archive ) return $rules;

    $default_slug = is_string($has_archive) ? $has_archive : $post_type;
    $translated_slugs = $pt_object->acfml['archive_slug'] ?? null;
    if( !$translated_slugs ) return $rules;

    $slugs = array_values(array_unique(array_merge([$default_slug], $translated_slugs)));

    $new_rules = [];
    foreach( $rules as $regex => $rule ) {
      if( strpos($regex, $default_slug ) === 0 ) {
        $translated_regex = str_replace("$default_slug/?", "(?:".implode('|', $slugs).")/?", $regex);
        $new_rules[$translated_regex] = $rule;
      } else {
        $new_rules[$regex] = $rule;
      }
    }

    return $new_rules;
    
  }

  /**
  * Makes post type rewrite slugs multilingual. Looks for the custom property acfml>rewrite_slug in the post type object
  * and adds possibly found slug translations to the regex
  *
  * @param Array $rules
  * @param String $post_type
  * @return Array
  */
  private function multilingual_rewrite_slugs(Array $rules, String $post_type): array {
    $pt_object = get_post_type_object( $post_type );
    $default_slug = $pt_object->rewrite['slug'] ?? $post_type;
    $translated_rewrite_slugs = $pt_object->acfml['rewrite_slug'] ?? null;
    if( !$translated_rewrite_slugs ) return $rules;
    $rewrite_slugs = array_values(array_unique(array_merge([$default_slug], $translated_rewrite_slugs)));

    $new_rules = [];
    foreach( $rules as $regex => $rule ) {
      if( strpos($regex, $default_slug ) === 0 ) {
        $translated_regex = str_replace("$default_slug/", "(?:".implode('|', $rewrite_slugs).")/", $regex);
        $new_rules[$translated_regex] = $rule;
      } else {
        $new_rules[$regex] = $rule;
      }
    }
    return $new_rules;
  }

  /**
   * Check if a post type is multilingual
   *
   * @param string $post_type
   * @return Bool
   */
  private function is_multilingual_post_type(String $post_type):Bool {
    return in_array($post_type, $this->get_multilingual_post_types());
  }

  /**
   * Adds a custom field group for the  title
   * for each post_type that supports `acfml-title`
   *
   * @return void
   */
  private function setup_acf_fields() {
    
    $post_types = $this->get_multilingual_post_types();
    
    // bail early if no post types support `multilingual-title`
    if( !count($post_types) ) return;

    // generate location rules for multilingual titles
    $location = [];
    foreach( $post_types as $pt ) {
      $location[] = [
        [
          'param' => 'post_type',
          'operator' => '==',
          'value' => $pt
        ]
      ];
    }
    
    // create the title field group
    acf_add_local_field_group([
      'key' => $this->field_group_key,
      'title' => "Title",
      'menu_order' => -1000,
      'style' => 'seamless',
      'position' => 'acf_after_title',
      'location' => $location,
    ]);
    
    // create the title field
    acf_add_local_field(array(
      'key' => $this->title_field_key,
      'label' => 'Title',
      'placeholder' => __( 'Add title' ),
      'name' => $this->title_field_name,
      'type' => 'text',
      'is_multilingual' => true,
      'required' => true,
      'parent' => $this->field_group_key,
      'wrapper' => [
        'class' => str_replace('_', '-', $this->title_field_name),
        // 'id' => 'titlediv',
      ]
    ));

    // create the slug field group
    acf_add_local_field_group([
      'key' => "group_{$this->slug_field_key}",
      'title' => __('Slug'),
      'menu_order' => -999,
      'style' => 'default',
      'position' => 'acf_after_title',
      'location' => $location,
    ]);
    // create the field for slugs
    acf_add_local_field(array(
      'key' => $this->slug_field_key,
      'name' => $this->slug_field_name,
      'type' => 'text',
      'is_multilingual' => true,
      'parent' => "group_{$this->slug_field_key}",
      'wrapper' => [
        'class' => str_replace('_', '-', $this->slug_field_name),
      ]
    ));

  }

  /**
   * Render the field
   *
   * @param Array $field
   * @return void
   */
  public function render_field($field) {
    // $this->render_slug_box($field);
  }

  /**
   * Renders the slug box. 
   * Inspired by code found in /wp-admin/edit-form-advanced.php
   *
   * @param Array $field
   * @return void
   */
  private function render_slug_box($field) {
    return;
    global $pagenow, $post_type, $post_type_object, $post;
    if( !in_array($pagenow, ['post.php', 'post-new.php']) ) return;
    if( !is_post_type_viewable( $post_type_object ) ) return;
    if( !current_user_can( $post_type_object->cap->publish_posts ) ) return;
    $sample_permalink_html = $post_type_object->public ? get_sample_permalink_html( $post->ID ) : ''; 
    ?>
    <div id="edit-slug-box" class="hide-if-no-js">
    <?php if( $sample_permalink_html && 'auto-draft' !== get_post_status($post) ) echo $sample_permalink_html; ?>
    </div>
    <?php echo wp_nonce_field( 'samplepermalink', 'samplepermalinknonce', false, false );
  }

  /**
   * Filter title
   *
   * @param string $title
   * @param Int $post_id
   * @param string
   */
  public function filter_post_title($title, $post_id) {
    $acfml_title = get_field($this->title_field_name, $post_id);
    return $acfml_title ? $acfml_title : $title;
  }

  /**
   * Load Default Value
   *
   * @param Mixed $value
   * @param Int $post_id
   * @param Array $field
   * @return Mixed
   */
  public function load_default_value( $value, $post_id, $field ) {
    if($value) return $value;
    $post_status = get_post_status($post_id);
    if( !in_array($post_status, ['auto-draft']) ) {
      $value = get_the_title($post_id);
    }
    return $value;
  }

  /**
   * Filter Admin Body Class
   *
   * @param string $class
   * @param string
   */
  public function admin_body_class($class) {
    global $pagenow, $typenow;
    if( !in_array($pagenow, ['post.php', 'post-new.php']) ) return $class;
    if( in_array($typenow, $this->get_multilingual_post_types()) ) $class .= " $this->prefix-supports-post-title";
    return $class;
  }

  /**
   * Save post title of default language
   *
   * @param Array $data
   * @param Array $_post
   * @return Array
   */
  public function wp_insert_post_data(Array $data , Array $_post):Array {
    $default_language_post_title = $_post["acf"][$this->title_field_key]["{$this->title_field_key}_{$this->default_language}"] ?? null;
    if( $default_language_post_title ) $data['post_title'] = $default_language_post_title;
    return $data;
  }

  /**
   * Update a post's slugs
   *
   * @param Int $post_id
   * @return Void
   */
  function save_post_slugs($post_id):Void {
    // cache WP locale, so that we can temporarily overwrite it
    // during the slug generation
    $cached_locale = get_locale();

    $languages = acfml()->get_languages('iso');
    $post = get_post($post_id);

    // bail early if the post type is not multilingual
    if( !$this->is_multilingual_post_type($post->post_type) ) return;

    // bail early based on the post's status
    if ( in_array( $post->post_status, ['draft', 'pending', 'auto-draft'], true ) ) return;
    
    // This array will contain all post titles for the slugs to be generated from
    $post_titles = [];
    // get the post title of the default language (should always have some)
    $default_post_title = get_field("{$this->title_field_name}_{$this->default_language}", $post_id);
    $post_titles[$this->default_language] = $default_post_title;
    
    // prepare post titles so there is one for every language
    foreach( $languages as $lang ) {
      // do nothing for the default language
      if( $lang === $this->default_language ) continue;
      $post_titles[$lang] = acfml()->get_field_or("{$this->title_field_name}_{$lang}", $default_post_title, $post_id);
    }

    // generate slugs for every language
    foreach( $languages as $lang ) {
      
      // set locale to current $lang, so that sanitize_title can run on full power
      $locale = acfml()->get_language_info($lang)['locale'];
      $sanitized_title = sanitize_title($post_titles[$lang]);
      // reset locale
      $locale = $cached_locale;

      // get the slug from the field
      $slug = acfml()->get_field_or("{$this->slug_field_name}_{$lang}", $sanitized_title, $post_id);
      // make the slug unique
      $slug = $this->get_unique_post_slug( $slug, get_post($post_id), $lang );
      // save the unique slug to the database
      update_field("{$this->slug_field_name}_{$lang}", $slug, $post_id);
      if( $lang === $this->default_language ) $post_name = $slug;
    }
    // save slug of the default language to the post_name
    if( isset($post_name) ) {
      remove_action('acf/save_post', [$this, 'save_post_slugs']);
      wp_update_post([
        'ID' => $post_id,
        'post_name' => $post_name
      ]);
      add_action('acf/save_post', [$this, 'save_post_slugs']);
    }
  }

  /**
   * Undocumented function
   *
   * @param string $slug
   * @param WP_Post $post_id
   * @param string $lang
   * @param string The (hopefully) unique post slug
   */
  function get_unique_post_slug(String $slug, \WP_Post $post, String $lang): string {
    global $wp_rewrite;
    $meta_key = "{$this->slug_field_name}_{$lang}";
    $reserved_slugs = $wp_rewrite->feeds ?? [];
    $reserved_slugs = array_merge($reserved_slugs, ['embed']);
    $count = 0;

    // allow for filtering bad post slugs
    $is_bad_post_slug = apply_filters('acfml/is_bad_post_slug', false, $slug, $post, $lang);

    // @see wp-includes/post.php -> wp_unique_post_slug()
    if( $post->post_parent && in_array($slug, $reserved_slugs, true) 
      || preg_match( "@^($wp_rewrite->pagination_base)?\d+$@", $slug )
      || $is_bad_post_slug
      ) {
        $count = 2;
    }

    $original_slug = $slug;
    $check_post_name = true;
    
    while( $check_post_name ) {
      $posts = get_posts([
        'post_type' => $post->post_type,
        'post_parent' => $post->post_parent,
        'posts_per_page' => 1,
        'post__not_in' => [$post->ID],
        'meta_key' => $meta_key,
        'meta_value' => $slug,
        'post_status' => ['publish', 'future', 'private']
      ]);
      if( count($posts) ) {
        $count ++;
        $check_post_name = true;
        $slug = "$original_slug-$count";
      } else {
        $check_post_name = false;
      }
    }
    
    return $count ? "$original_slug-$count" : $original_slug;
  }
}