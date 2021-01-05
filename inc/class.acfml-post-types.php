<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ACFML_Post_Types {
  
  private $prefix;
  private $default_language;

  private $field_group_key;

  private $title_field_name = "acfml_post_title";
  private $title_field_key;

  private $slug_field_name = "acfml_slug";
  private $slug_field_key;

  private $public_field_name = "acfml_lang_public";
  private $public_field_key;

  public function __construct() {

    // variables
    $this->prefix = acfml()->get_prefix();
    $this->default_language = acfml()->get_default_language();
    
    $this->field_group_key    = "group_{$this->title_field_name}";
    $this->title_field_key    = "field_{$this->title_field_name}";
    $this->slug_field_key     = "field_{$this->slug_field_name}";
    $this->public_field_key   = "field_{$this->public_field_name}";

    add_action('registered_post_type', [$this, 'registered_post_type'], 10, 2);

    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array']);
    add_action('init', [$this, 'flush_rewrite_rules'], 11);

    // query filters
    add_filter('pre_get_posts', [$this, 'pre_get_posts'], 999);
    add_filter('query', [$this, 'query__get_page_by_path']);

    // hooks
    add_filter('the_title', [$this, 'single_post_title'], 10, 2);
    add_filter('single_post_title', [$this, 'single_post_title'], 10, 2);
    add_filter('admin_body_class', [$this, 'admin_body_class'], 20);
    // add_action("acf/render_field/key={$this->title_field_key}", [$this, 'render_field']);
    add_action("acf/load_value/key={$this->title_field_key}_{$this->default_language}", [$this, "load_default_value"], 10, 3);
    add_action('wp_insert_post_data', [$this, 'wp_insert_post_data'], 10, 2);

    add_action('acf/save_post', [$this, 'save_post'], 11);

    add_action('acf/init', [$this, 'setup_acf_fields']);
  }

  /**
   * Get multilingual post types
   *
   * @return Array
   */
  public function get_multilingual_post_types() {
    $post_types = array_unique( apply_filters("acfml/multilingual_post_types", []) );
    // attachments are not supported. They are horrible edge cases :P
    unset($post_types['attachment']);
    return $post_types;
  }

  /**
   * Check if a given post type is multilingual
   *
   * @param string $post_type
   * @return boolean
   */
  public function is_multilingual_post_type( $post_type ):bool {
    return in_array($post_type, $this->get_multilingual_post_types());
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
  public function flush_rewrite_rules() {
    flush_rewrite_rules();
  }

  /**
   * Makes post type archives multilingual. Looks for the custom property acfml->lang->archive_slug in the 
   * post type object and adds possibly found slug translations to the regex
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
    $translated_slugs = array_column($pt_object->acfml, 'archive_slug') ?? null;
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
  * Makes post type rewrite slugs multilingual. 
  * Looks for the custom property acfml->lang->rewrite_slug in the post type object
  * and adds possibly found slug translations to the regex
  *
  * @param Array $rules
  * @param String $post_type
  * @return Array
  */
  private function multilingual_rewrite_slugs(Array $rules, String $post_type): array {
    $pt_object = get_post_type_object( $post_type );
    $default_slug = $pt_object->rewrite['slug'] ?? $post_type;
    $translated_rewrite_slugs = array_column($pt_object->acfml, 'rewrite_slug') ?? null;
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
  public function acfml_multilingual_post_type(String $post_type):Bool {
    return in_array($post_type, $this->get_multilingual_post_types());
  }

  /**
   * Adds a custom field group for the  title
   * for each post_type that supports `acfml-title`
   *
   * @return void
   */
  public function setup_acf_fields() {
    
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
      'title' => __("Title") . ', ' . __("Settings"),
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
      'acfml_multilingual' => true,
      'required' => true,
      'parent' => $this->field_group_key,
      'wrapper' => [
        'class' => str_replace('_', '-', $this->title_field_name),
      ]
    ));

    // prepare slug fields for each language
    foreach( acfml()->get_languages('slug') as $lang ) {
      
      add_filter("acf/prepare_field/key=field_acfml_slug_$lang", function($field) use ($lang) {
        global $post;
        if( !$field ) return $field;
        // add the post link base to the $field's 'prepend' option
        $post_link = $this->get_post_link($post, $lang, false);
        $slug = $field['value'] ?: $post->post_name;
        $prepend = preg_replace("#$slug/?$#", '', $post_link);
        if( !$field['value'] ) $field['placeholder'] = $post->post_name;
        $field['prepend'] = $prepend;
        
        // add the 'View' to the $field's 'append' option
        if( $this->is_language_public($lang, $post->ID) && in_array($post->post_status, ['publish'] ) ) {
          $field['append'] .= sprintf("<a class='button' href='$post_link' target='_blank'>%s</a>", __('View'));
        }
        return $field;
      });
    }
    
    // create the slug field
    acf_add_local_field(array(
      'key' => $this->slug_field_key,
      'name' => $this->slug_field_name,
      'label' => __('Permalink'),
      'type' => 'text',
      'acfml_multilingual' => true,
      'acfml_ui_listen_to' => $this->title_field_name,
      'acfml_ui' => false,
      // 'readonly' => true,
      'parent' => $this->field_group_key,
      'prepend' => '/',
      'wrapper' => [
        'width' => '70',
        'class' => str_replace('_', '-', $this->slug_field_name),
      ]
    ));

    // create the field for making translations public
    acf_add_local_field(array(
      'key' => $this->public_field_key,
      'name' => $this->public_field_name,
      'label' => __('Status'),
      'type' => 'true_false',
      'ui' => true,
      'ui_on_text' => __('Public'),
      'ui_off_text' => __('Draft'),
      'acfml_multilingual' => true,
      'default_value' => 1,
      'acfml_ui_listen_to' => $this->title_field_name,
      'acfml_ui' => false,
      'parent' => $this->field_group_key,
      'wrapper' => [
        'width' => '30',
        'class' => str_replace('_', '-', $this->public_field_name),
      ]
    ));

  }

  /**
   * Check if a language for a post is set to public
   *
   * @param string $lang
   * @param integer $post_id
   * @return boolean
   */
  public function is_language_public(string $lang, int $post_id):bool {
    if( acfml()->is_default_language($lang) ) return true;
    return (bool) get_field("acfml_lang_public_$lang", $post_id);
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
   * Filter title of post
   *
   * @param string $title
   * @param Int $post_id
   * @param string
   */
  public function single_post_title($title, $post) {
    if( !$post ) return $title;
    $post = get_post($post);
    $acfml_title = get_field($this->title_field_name, $post->ID);
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
  function save_post($post_id):Void {
    
    // cache WP locale, so that we can temporarily overwrite it
    // during the slug generation
    $cached_locale = get_locale();

    $languages = acfml()->get_languages('slug');
    $post = get_post($post_id);

    // bail early if the post type is not multilingual
    if( !$this->acfml_multilingual_post_type($post->post_type) ) return;

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
      remove_action('acf/save_post', [$this, 'save_post']);
      wp_update_post([
        'ID' => $post_id,
        'post_name' => $post_name
      ]);
      add_action('acf/save_post', [$this, 'save_post']);
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
    $reserved_root_slugs = $wp_rewrite->feeds ?? [];
    $reserved_root_slugs = array_merge($reserved_root_slugs, ['embed']);
    $reserved_root_slugs = array_merge($reserved_root_slugs, acfml()->get_languages('slug'));
    $count = 0;

    // allow for filtering bad post slugs
    $is_bad_post_slug = apply_filters('acfml/is_bad_post_slug', false, $slug, $post, $lang);

    // @see wp-includes/post.php -> wp_unique_post_slug()
    if( !$post->post_parent && in_array($slug, $reserved_root_slugs, true) 
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
    
    $slug =  $count ? "$original_slug-$count" : $original_slug;
    return $slug;
  }

  /**
   * Add rewrite rules for multilingual post types
   *
   * @param Array $rules
   * @return Array
   */
  public function rewrite_rules_array($rules) {
    foreach( $this->get_multilingual_custom_post_types() as $post_type ) {

      $rules = $this->multilingual_rewrite_slugs($rules, $post_type);
      $rules = $this->multilingual_archive_slugs($rules, $post_type);

    }
    return $rules;
  }

  /**
   * Runs after a post type was registered
   *
   * @param string $pt
   * @param \WP_Post_Type $pt_object
   * @return void
   */
  public function registered_post_type( string $pt, \WP_Post_Type $pt_object ) {
    global $wp_post_types;
    $language = acfml()->get_current_language();
    $acfml = $pt_object->acfml[$language] ?? null;
    if( !$acfml ) return;
    if( $labels = $acfml['labels'] ?? null ) {
      $pt_object->labels = $labels;
      $wp_post_types[$pt]->labels = get_post_type_labels($pt_object);
    }
  }

  /**
   * pre_get_posts
   *
   * modifies WP_Query to be language-aware
   * 
   * @param \WP_Query $query
   * @return void
   */
  public function pre_get_posts( $query ) {
    if( !$query->is_main_query() ) return;

    $language = acfml()->get_current_language();
    if( acfml()->is_default_language($language) ) return;

    $post_type = $query->queried_object->post_type ?? $query->get('post_type') ?: false;
    $post_type_object = get_post_type_object($post_type);
    
    // bootstrap meta query
    $meta_query = $query->get('meta_query') ?: [];
    // prepare for single query of type 'post'
    if( !is_post_type_hierarchical($post_type) && $query->is_single() ) {
      $meta_query['acfml_slug'] = [
        'relation' => 'OR',
        [
          'key' => "acfml_slug_$language",
          'value' => $query->get('name')
        ], 
        [
          'key' => "acfml_slug_$language",
          'compare' => 'NOT EXISTS'
        ]
      ];
      // for posts of post type 'post'
      unset($query->query_vars['name']);
      // for custom post types
      if( $post_type_object ) unset($query->query_vars[$post_type_object->query_var]);
    }
    
    // Allow posts to be set to non-public
    $meta_query['acfml_lang_public'] = [
      'relation' => 'OR',
      [
        'key' => "acfml_lang_public_$language",
        'value' => 1,
        'type' => 'NUMERIC'
      ],
      [
        'key' => "acfml_lang_public_$language",
        'compare' => 'NOT EXISTS',
      ]
    ];
    
    $query->set('meta_query', $meta_query);

  }


  /**
   * Detect and overwrite the query for get_page_by_path
   *
   * @param [type] $query
   * @return void
   */
  public function query__get_page_by_path($query) {
    global $wpdb;
    
    $language = acfml()->get_current_language();
    if( acfml()->current_language_is_default() ) return $query;
    // detect correct query and find $in_string and $post_type_in_string
    preg_match('/SELECT ID, post_name, post_parent, post_type.+post_name IN \((?<slugs_in_string>.*?)\).+ post_type IN \((?<post_type_in_string>.*?)\)/ms', $query, $matches);
    // return the query if it doesn't match
    if( !count($matches) ) return $query;
    // prepare post types
    $slug_in_string = $matches['slugs_in_string'];
    $post_type_in_string = $matches['post_type_in_string'];
    // $post_types = array_map(function($item) {
    //   return trim($item, "'");
    // }, explode(',', $post_type_in_string) );
    // pre_dump( $post_types );
    // if( in_array('page', $post_types) ) {
    //   $post_types = array_merge(['post'], $post_types);
    // }
    // $post_type_in_string = "'" . implode("','", $post_types) ."'";
    // build the query for custom slugs
    $slug_query = "
    SELECT ID, acfml_mt1.meta_value AS post_name, post_parent, post_type FROM $wpdb->posts
        LEFT JOIN $wpdb->postmeta AS acfml_mt1 ON ( $wpdb->posts.ID = acfml_mt1.post_id )
          WHERE 
          (
            acfml_mt1.meta_key = 'acfml_slug_$language'
            AND
            acfml_mt1.meta_value IN ({$slug_in_string})
          )
          AND post_type IN ({$post_type_in_string})
          AND post_status NOT IN ('trash')";
    // combine both queries. The first for non-translated slugs, the second for translated ones.
    $query = "($query) UNION ($slug_query)";
    return $query;
  }

  /**
   * Get translated permalink for a post
   *
   * @param \WP_Post $post
   * @param string $language
   * @param string
   */
  public function get_post_link( \WP_Post $post, String $language, bool $check_lang_public = true ): string {

    $fallback_url = apply_filters('acfml/post_link_fallback', acfml()->home_url('/', $language));

    $post_type_object = get_post_type_object($post->post_type);
    $ancestors = array_reverse(get_ancestors($post->ID, $post->post_type, 'post_type'));
    $segments = [];
    $postname_tag = "";

    acfml()->remove_link_filters();
    // get the unfiltered permalink
    $permalink_native = get_permalink($post);
    // get the permalink for the post, leaving the %postname% tag untouched
    $link_template = get_permalink($post, true);
    acfml()->add_link_filters();

    // return the default permalink if the language is the default one
    if( acfml()->is_default_language($language) ) return $permalink_native;

    // remove possible parent page uri from attachment urls
    if( $post->post_type === 'attachment' && $post->post_parent ) {
      $parent_uri = get_page_uri($post->post_parent);
      $link_template = str_replace("/$parent_uri", '', $link_template);
    }

    // convert the permalink's base to the requestes $language
    $link_template = acfml()->simple_convert_url($link_template, $language);

    // if the post is the front page, return home page in requested language
    if( $this->post_is_front_page($post->ID) ) return acfml()->home_url('/', $language);

    // check if the language for the requested post is public
    $acfml_lang_public = get_field("acfml_lang_public_$language", $post->ID);
    if( 
      !acfml()->is_default_language($language) 
      && $check_lang_public 
      && !is_null($acfml_lang_public)
      && intval($acfml_lang_public) === 0 ) {
        return $fallback_url;
      }

    // determine post's rewrite tag for %postname% 
    switch( $post->post_type ) {
      case 'post':
      case 'attachment':
      $postname_tag = "%postname%";
      break;
      case 'page':
      $postname_tag = "%pagename%";
      break;
      default:
      $postname_tag = "%$post->post_type%"; // custom post types
      break;
    }

    // add possible custom post type's rewrite slug and front to segments
    $default_rewrite_slug = $post_type_object->rewrite['slug'] ?? null;
    $acfml_rewrite_slug = ($post_type_object->acfml[$language]['rewrite_slug']) ?? null;
    if( $rewrite_slug = $acfml_rewrite_slug ?: $default_rewrite_slug ) {
      $link_template = str_replace("/$default_rewrite_slug/", "/$rewrite_slug/", $link_template);
    }

    // add slugs for all ancestors to segments
    foreach( $ancestors as $ancestor_id ) {
      $ancestor = get_post($ancestor_id);
      $segments[] = $this->get_post_slug($ancestor, $language);
    }

    // add slug for requested post to segments
    $segments[] = $this->get_post_slug($post, $language);

    $postname = implode('/', $segments);

    $link = str_replace($postname_tag, $postname, $link_template);

    return $link;
  }

  /**
   * Get the slug for a post
   *
   * @param \WP_Post $post
   * @param string $language
   * @return string
   */
  private function get_post_slug( \WP_Post $post, string $language ): string { 
    if( !$this->is_multilingual_post_type($post->post_type) ) return $post->post_name;
    return acfml()->get_field_or("{$this->slug_field_name}_{$language}", $post->post_name, $post->ID);
  }

  /**
   * Check if a post is used as the front page
   *
   * @param Int $post_id
   * @return bool
   */
  private function post_is_front_page($post_id): bool {
    return get_option("show_on_front") === "page" && $post_id === intval(get_option('page_on_front'));
  }

  /**
   * Get the archive slug for a post type
   *
   * @param string $post_type
   * @param string $language
   * @return string|null
   */
  private function get_post_type_archive_slug( string $post_type, string $language ): ?string {
    $post_type_object = get_post_type_object($post_type);
    if( !$post_type_object || !$post_type_object->has_archive ) return null;
    $default_archive_slug = is_string($post_type_object->has_archive) ? $post_type_object->has_archive : $post_type;
    return $post_type_object->acfml[$language]['archive_slug'] ?? $default_archive_slug;
  }

  /**
   * Get post type archive url for a language
   *
   * @param string $post_type_object
   * @param string $language
   * @return string|null
   */
  public function get_post_type_archive_link( string $post_type, string $language ): ?string {

    acfml()->remove_link_filters();
    $link = get_post_type_archive_link($post_type);
    acfml()->add_link_filters();

    $path = trim(str_replace(home_url(), '', $link), '/');

    $default_archive_slug = $this->get_post_type_archive_slug($post_type, acfml()->get_default_language());
    $archive_slug = $this->get_post_type_archive_slug($post_type, $language);
    if( !$archive_slug ) return $link;

    $path = preg_replace("#$default_archive_slug$#", $archive_slug, $path);
    $path = user_trailingslashit($path);
    $link = acfml()->home_url("/$path", $language);
    $link = apply_filters('acfml/post_type_archive_link', $link, $post_type, $language);
    return $link;
  }

}
