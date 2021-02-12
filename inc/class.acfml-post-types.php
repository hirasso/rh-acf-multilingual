<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ACFML_Post_Types {
  
  private $prefix;
  private $default_language;

  private $multilingual_post_types = [];

  private $field_group_key;

  private $title_field_name = "acfml_post_title";
  private $title_field_key;

  private $slug_field_name = "acfml_slug";
  private $slug_field_key;

  private $lang_active_field_name = "acfml_lang_active";
  private $lang_active_field_key;

  public function __construct() {

    // variables
    $this->prefix = acfml()->get_prefix();
    $this->default_language = acfml()->get_default_language();
    
    $this->field_group_key    = "group_{$this->title_field_name}";
    $this->title_field_key    = "field_{$this->title_field_name}";
    $this->slug_field_key     = "field_{$this->slug_field_name}";
    $this->lang_active_field_key   = "field_{$this->lang_active_field_name}";
    
    add_filter('rewrite_rules_array', [$this, 'rewrite_rules_array']);

    // query filters
    add_filter('pre_get_posts', [$this, 'pre_get_posts'], 999);
    
    add_filter('query', [$this, 'query__get_page_by_path']);

    // old slugs
    add_action('post_updated', [$this, 'check_for_changed_slugs'], 12, 3 );
    add_filter('query', [$this, 'query__find_post_by_old_slug']);
    add_action('template_redirect', [$this, 'prepare_old_slug_redirect'], 9 );
    
    // hooks
    add_filter('the_title', [$this, 'single_post_title'], 10, 2);
    add_filter('single_post_title', [$this, 'single_post_title'], 10, 2);
    add_filter('admin_body_class', [$this, 'admin_body_class'], 20);
    add_action("acf/load_value/key={$this->title_field_key}_{$this->default_language}", [$this, "load_default_value"], 10, 3);

    add_action('acf/save_post', [$this, 'save_post'], 20);

    add_action('admin_init', [$this, 'check_for_posts_with_empty_slugs']);
    add_action('admin_init', [$this, 'maybe_generate_slugs']);
    
    add_action('init', [$this, 'setup_acf_fields'], 11);
    
  }

  /**
   * Add a post type for translating the title and slugs
   *
   * @param string $post_type
   * @param array|null $args
   * @return void
   */
  public function add_post_type(string $post_type, ?array $args = []) {
    global $wp_post_types;
    // attachments are not supported. They are horrible edge cases :P
    $unsupported_post_types = ["attachment"];
    if( in_array($post_type, $unsupported_post_types) ) return;
    // add the post type and it's arguments to the array
    $this->multilingual_post_types[$post_type] = $args;
    // parse translated post type labels
    $language = acfml()->get_current_language();
    $pt_object = get_post_type_object($post_type);
    if( $labels = $args[$language]['labels'] ?? null ) {
      $pt_object->labels = $labels;
      $wp_post_types[$post_type]->labels = get_post_type_labels($pt_object);
    }
  }

  /**
   * Get multilingual post types
   *
   * @param $format 
   * @return array
   */
  public function get_multilingual_post_types( ?string $format = 'names' ): array {
    $post_types = $this->multilingual_post_types;
    return $format === 'names' ? array_keys($post_types) : $post_types;
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
    
    $settings = $this->multilingual_post_types[$post_type];
    $acfml_archive_slugs = array_column($settings, 'archive_slug') ?? null;
    if( empty($acfml_archive_slugs) ) return $rules;

    $slugs = array_values(array_unique(array_merge([$default_slug], $acfml_archive_slugs)));
    $joined_slugs = implode('|', $slugs);

    $new_rules = [];
    foreach( $rules as $regex => $rule ) {
      if( strpos($regex, $default_slug ) === 0 ) {
        $multilingual_regex = str_replace("$default_slug/?", "(?:$joined_slugs)/?", $regex);
        $new_rules[$multilingual_regex] = $rule;
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

    $settings = $this->multilingual_post_types[$post_type];
    $acfml_rewrite_slugs = array_column($settings, 'rewrite_slug') ?? null;
    if( empty($acfml_rewrite_slugs) ) return $rules;

    $rewrite_slugs = array_values(array_unique(array_merge([$default_slug], $acfml_rewrite_slugs)));
    $joined_rewrite_slugs = implode('|', $rewrite_slugs);

    $new_rules = [];
    foreach( $rules as $regex => $rule ) {
      if( strpos($regex, $default_slug ) === 0 ) {
        $multilingual_regex = str_replace("$default_slug/", "(?:$joined_rewrite_slugs)/", $regex);
        $new_rules[$multilingual_regex] = $rule;
      } else {
        $new_rules[$regex] = $rule;
      }
    }
    return $new_rules;
  }

  /**
   * Adds custom fields for the title, slug, active languages
   *
   * @return void
   */
  public function setup_acf_fields() {
    
    $post_types = $this->get_multilingual_post_types();
    
    // bail early if no post types support `multilingual-title`
    if( !count($post_types) ) return;

    // generate location rules for multilingual titles
    $locations = [];
    foreach( $post_types as $pt ) {
      $location = [
        'param' => 'post_type',
        'operator' => '==',
        'value' => $pt
      ];
      $locations[] = [$location];
    }
    
    // create the title field group
    acf_add_local_field_group([
      'key' => $this->field_group_key,
      'title' => __('Title') . ', ' . __('Settings'),
      'menu_order' => -1000,
      'style' => 'seamless',
      'position' => 'acf_after_title',
      'location' => $locations,
    ]);
    
    // create the title field
    acf_add_local_field(array(
      'key' => $this->title_field_key,
      'label' => 'Title',
      'placeholder' => __('Add title'),
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
        
        if( !$field || empty($post) ) return $field;

        // add the post link base to the $field's 'prepend' option
        if( $post->post_parent ) {
          $parent_post = get_post( $post->post_parent );
          
          $prepend = $this->get_post_link($parent_post, $lang, [
            'check_lang_active' => false,
            'is_sample' => true
          ]);
        } else {
          $prepend = acfml()->home_url('/', $lang);
        }
        
        if( !$field['value'] && $lang === $this->default_language ) $field['placeholder'] = $post->post_name;
        $field['prepend'] = $prepend;
        
        // add the 'View' to the $field's 'append' option
        $post_link = $this->get_post_link($post, $lang);

        if( $field['value'] && $this->is_language_public($lang, $post->ID) && in_array($post->post_status, ['publish'] ) ) {
          $field['append'] .= sprintf("<a class='button' href='$post_link' target='_blank'>%s</a>", __('View'));
        }
        return $field;
      });

      add_filter("acf/prepare_field/key=field_acfml_lang_active_$lang", function($field) use ($lang) {
        global $post;

        if( !$field || empty($post) ) return $field;

        if( $lang === $this->default_language ) {
          $field['instructions'] = __('The default language is always active', 'acfml');
        } else {
          $field['instructions'] = __('Show language in frontend?', 'acfml');
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
      'instructions' => __('Leave empty to generate the link from title', 'acfml'),
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
      'key' => $this->lang_active_field_key,
      'name' => $this->lang_active_field_name,
      'label' => __('Active'),
      'type' => 'true_false',
      'ui' => true,
      'acfml_multilingual' => true,
      'default_value' => 1,
      'acfml_ui_listen_to' => $this->title_field_name,
      'acfml_ui' => false,
      'parent' => $this->field_group_key,
      'wrapper' => [
        'width' => '30',
        'class' => str_replace('_', '-', $this->lang_active_field_name),
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
    return get_field("acfml_lang_active_$lang", $post_id) !== '0';
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
    if( in_array($typenow, $this->get_multilingual_post_types()) ) $class .= " acfml-multilingual-post-type";
    return $class;
  }

  /**
   * Update a post's slugs
   *
   * @param Int $post_id
   * @return Void
   */
  function save_post($post_id):Void {
    global $locale;
    
    // cache WP locale, so that we can temporarily overwrite it
    // during the slug generation
    $cached_locale = get_locale();

    $languages = acfml()->get_languages('slug');
    $post = get_post($post_id);

    // bail early if the post type is not multilingual
    if( !$this->is_multilingual_post_type($post->post_type) ) return;

    // bail early based on the post's status
    if ( in_array( $post->post_status, ['draft', 'pending', 'auto-draft'], true ) ) return;
    
    // This array will contain all post titles for the slugs to be generated from
    $post_titles = [];
    // get the post title of the default language (should always have some)
    $default_post_title = acfml()->get_field_or("{$this->title_field_name}_{$this->default_language}", $post->post_title, $post_id);
    $post_titles[$this->default_language] = $default_post_title;

    // prepare post titles so there is one for every language
    foreach( $languages as $lang ) {
      // do nothing for the default language
      if( $lang === $this->default_language ) continue;
      $post_titles[$lang] = acfml()->get_field_or("{$this->title_field_name}_{$lang}", $default_post_title, $post_id);
    }

    $post_slugs = [];
    // generate slugs for every language
    foreach( $languages as $lang ) {
      // get the slug from the field
      $raw_slug = acfml()->get_field_or("{$this->slug_field_name}_{$lang}", $post_titles[$lang], $post_id);

      // set locale to current $lang, so that sanitize_title can run on full power
      $locale = acfml()->get_language_info($lang)['locale'];
      // sanitize the slug
      $slug = sanitize_title($raw_slug);
      // reset locale
      $locale = $cached_locale;

      // make the slug unique
      $slug = $this->get_unique_post_slug( $slug, get_post($post_id), $lang );
      // save the unique slug to the database
      update_field("{$this->slug_field_name}_{$lang}", $slug, $post_id);
      $post_slugs[$lang] = $slug;
    }
    
    // save slug of the default language to the post_name
    remove_action('acf/save_post', [$this, 'save_post']);
    wp_update_post([
      'ID' => $post_id,
      'post_name' => $post_slugs[$this->default_language],
      'post_title' => $post_titles[$this->default_language]
    ]);
    add_action('acf/save_post', [$this, 'save_post'], 20);
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
      $args = [
        'post_type' => $post->post_type,
        'post_parent' => $post->post_parent,
        'posts_per_page' => 1,
        'post__not_in' => [$post->ID],
        'meta_key' => $meta_key,
        'meta_value' => $slug,
        'post_status' => ['publish', 'future', 'private', 'draft']
      ];
      if( $post->post_type === 'attachment' ) {
        $args['post_type'] = 'page';
        unset($args['post_parent']);
      }
      $posts = get_posts($args);
      if( count($posts) ) {
        $count += $count === 0 ? 2 : 1;
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
        [
          'key' => "acfml_slug_$language",
          'value' => $query->get('name')
        ],
      ];
      // for posts of post type 'post'
      unset($query->query_vars['name']);
      // for custom post types
      if( $post_type_object ) unset($query->query_vars[$post_type_object->query_var]);
    }
    
    
    // Allow posts to be set to non-public
    $meta_query['acfml_lang_active'] = [
      'relation' => 'OR',
      [
        'key' => "acfml_lang_active_$language",
        'value' => 1,
        'type' => 'NUMERIC'
      ],
      [
        'key' => "acfml_lang_active_$language",
        'compare' => 'NOT EXISTS',
      ]
    ];
    
    // add acfml_post_title so that we can adjust the order accordingliy
    $meta_query['acfml_post_title'] = [
      'key' => "acfml_post_title_$language"
    ];

    

    // adjust orderby
    $orderby = $query->get('orderby');
    $order = $query->get('order');
    
    if( $orderby === 'title' ) {
      // accounts for simple 'title'
      $orderby = ['acfml_post_title' => $order];
    } elseif( is_array($orderby) && array_key_exists('title', $orderby) ) {
      // accounts for something like ['menu_order' => 'asc', 'title' => 'DESC' ]
      $orderby['acfml_post_title'] = $orderby['title'];
      unset( $orderby['title'] );
    } elseif( in_array('title', explode(' ', $orderby) ) ) {
      // accounts for crazy strings like 'menu_order title'
      $orderby_fields = explode(' ', $orderby);
      $orderby = [];
      foreach( $orderby_fields as $field ) {
        if( $field === 'title' ) {
          $orderby['acfml_post_title'] = $order;
        } else {
          $orderby[$field] = $order;
        }
      }
    }

    $query->set('meta_query', $meta_query);
    $query->set('orderby', $orderby);

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
    if( acfml()->is_default_language($language) ) return $query;
    // detect correct query and find $in_string and $post_type_in_string
    preg_match('/SELECT ID, post_name, post_parent, post_type.+post_name IN \((?<slugs_in_string>.*?)\).+ post_type IN \((?<post_type_in_string>.*?)\)/ms', $query, $matches);
    // return the query if it doesn't match
    if( !count($matches) ) return $query;
    // prepare post types
    $slug_in_string = $matches['slugs_in_string'];
    $post_types = $this->in_string_to_array($matches['post_type_in_string']);

    $queries = [];
    foreach( $post_types as $post_type ) {
      if( $this->is_multilingual_post_type($post_type) ) {
        $queries[] = "(
          SELECT ID, acfml_mt1.meta_value AS post_name, post_parent, post_type FROM $wpdb->posts
          LEFT JOIN $wpdb->postmeta AS acfml_mt1 ON ( $wpdb->posts.ID = acfml_mt1.post_id )
          WHERE 
          (
            acfml_mt1.meta_key = 'acfml_slug_$language'
            AND
            acfml_mt1.meta_value IN ($slug_in_string)
          )
          AND post_type = '$post_type'
          AND post_status NOT IN ('trash')
        )";
      } else {
        $queries[] = "(
          SELECT ID, post_name, post_parent, post_type
          FROM prg_posts
          WHERE post_name IN ($slug_in_string)
          AND post_type = '$post_type'
          AND post_status NOT IN ('trash')
        )";
      }
    }

    $query = implode(" UNION ", $queries);

    return $query;
  }

  /**
   * Convert an sql in_string to an array
   *
   * @param string $in_string     This expects a string like "'something', 'something_else'"
   * @return array
   */
  private function in_string_to_array( string $in_string ): array {
    return explode("','", trim($in_string, "'"));
  }

  /**
   * Get translated permalink for a post
   *
   * @param \WP_Post $post
   * @param string $language
   * @param array $args
   * @param string
   */
  public function get_post_link( \WP_Post $post, String $language, array $args = [] ): string {
    
    $args = acfml()->to_object(wp_parse_args($args, [
      'check_lang_active' => true,
      'is_sample' => false
    ]));

    if( $args->is_sample ) $post->post_status = 'publish';

    $fallback_url = apply_filters('acfml/post_link_fallback', acfml()->home_url('/', $language));

    $post_type_object = get_post_type_object($post->post_type);
    $ancestors = array_reverse(get_ancestors($post->ID, $post->post_type, 'post_type'));
    $segments = [];
    $postname_rewrite_tag = "";

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

    // convert the permalink's base to the requested $language
    $link_template = acfml()->simple_convert_url($link_template, $language);

    // if the post is the front page, return home page in requested language
    if( $this->post_is_front_page($post->ID) ) return acfml()->home_url('/', $language);

    // check if the language for the requested post is public
    $acfml_lang_active = get_field("acfml_lang_active_$language", $post->ID);
    
    if( 
      !acfml()->is_default_language($language) 
      && $args->check_lang_active 
      && !is_null($acfml_lang_active)
      && intval($acfml_lang_active) === 0 ) {
        return $fallback_url;
      }

    // determine post's rewrite tag for %postname% 
    switch( $post->post_type ) {
      case 'post':
      case 'attachment':
      $postname_rewrite_tag = "%postname%";
      break;
      case 'page':
      $postname_rewrite_tag = "%pagename%";
      break;
      default:
      $postname_rewrite_tag = "%$post->post_type%"; // custom post types
      break;
    }

    // add possible custom post type's rewrite slug and front to segments
    $default_rewrite_slug = $post_type_object->rewrite['slug'] ?? null;
    
    $acfml_rewrite_slug = $this->multilingual_post_types[$post->post_type][$language]['rewrite_slug'] ?? null;
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

    // remove empty segments
    $segments = array_filter($segments, function($segment) {
      return !empty($segment);
    });

    $postname = implode('/', $segments);
    
    if( !$postname ) return $fallback_url;

    $link = str_replace($postname_rewrite_tag, $postname, $link_template);

    return $link;
  }

  /**
   * Get the slug for a post
   *
   * @param \WP_Post $post
   * @param string $language
   * @return string|null
   */
  private function get_post_slug( \WP_Post $post, string $language ): ?string { 
    if( !$this->is_multilingual_post_type($post->post_type) ) return $post->post_name;
    $slug = get_field("{$this->slug_field_name}_{$language}", $post->ID);
    if( !$slug && acfml()->is_default_language($language) ) return $post->post_name;
    return $slug;
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
    return $this->multilingual_post_types[$post_type][$language]['archive_slug'] ?? $default_archive_slug;
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

  /**
   * Multilingual find_post_by_old_slug
   *
   * @param string $query
   * @return string
   */
  public function query__find_post_by_old_slug($query) {
    $language = acfml()->get_current_language();
    if( acfml()->is_default_language($language) ) return $query;
    if( strpos($query, '_wp_old_slug') === false ) return $query;
    $query = str_replace('_wp_old_slug', "_wp_old_slug_$language", $query);
    return $query;
  }


  public function check_for_changed_slugs( int $post_id, \WP_Post $post, \WP_Post $post_before ): void {

    // bail early if the post type is not multilingual
    if( !$this->is_multilingual_post_type($post->post_type) ) return;

    // We're only concerned with published, non-hierarchical objects.
    if ( ! ( 'publish' === $post->post_status || ( 'attachment' === get_post_type( $post ) && 'inherit' === $post->post_status ) ) || is_post_type_hierarchical( $post->post_type ) ) {
      return;
    }

    foreach( acfml()->get_languages('slug') as $lang ) {
      // get old slugs
      $old_slug_meta_key = "_wp_old_slug_$lang";
      $old_slugs = (array) get_post_meta( $post_id, $old_slug_meta_key );

      // overwrite post slugs
      $post->post_name = get_field("acfml_slug_$lang", $post_id);
      $post_before->post_name = get_field("acfml_slug_$lang", $post_before->ID);

      // If we haven't added this old slug before, add it now.
      if ( ! empty( $post_before->post_name ) && ! in_array( $post_before->post_name, $old_slugs, true ) ) {
        add_post_meta( $post_id, $old_slug_meta_key, $post_before->post_name );
      }

      // If the new slug was used previously, delete it from the list.
      if ( in_array( $post->post_name, $old_slugs, true ) ) {
        delete_post_meta( $post_id, $old_slug_meta_key, $post->post_name );
      }

    }

  }

  /**
   * Re-injects the query var 'name', if a 404 was detected. 
   *
   * @return void
   */
  public function prepare_old_slug_redirect() {
    global $wp_query;
    if( acfml()->current_language_is_default() ) return;
    if( !is_404() ) return;
    $wp_query->query_vars['name'] = $wp_query->query['name'] ?? '';
  }

  /**
   * Find posts with empty slug in a language
   *
   * @param string $language
   * @param integer $posts_per_page
   * @return array
   */
  private function find_posts_with_empty_slug(string $language, $posts_per_page = 0): array {
    $posts = get_posts([
      'post_type' => $this->get_multilingual_post_types(),
      'meta_query' => [
        'relation' => 'OR',
        [
          'key' => "{$this->slug_field_name}_{$language}",
          'value' => ''
        ],
        [
          'key' => "{$this->slug_field_name}_{$language}",
          'compare' => 'NOT EXISTS'
        ]
      ],
      'posts_per_page' => $posts_per_page,
      'fields' => 'ids',
    ]);
    return $posts;
  }

  /**
   * Checks for posts that have empty slugs for certain languages
   *
   * @return void
   */
  public function check_for_posts_with_empty_slugs() {
    foreach( acfml()->get_languages('slug') as $lang ) {
      $posts = $this->find_posts_with_empty_slug($lang, 1);
      if( count($posts) ) {
        acfml()->admin->add_notice(
          'empty_slugs_notice',
          acfml()->get_template('notice-empty-slugs-detected', null, false),
        );
        break;
      }
    }
  }

  /**
   * Generate slugs for prevously monolingual posts
   *
   * @return void
   */
  public function maybe_generate_slugs() {
    // check nonce
    if( !acfml()->admin->verify_nonce('acfml_generate_slugs') ) return;
    // find posts with empty slugs for each language
    $post_ids = [];
    foreach( acfml()->get_languages('slug') as $lang ) {
      $posts = $this->find_posts_with_empty_slug($lang, -1);
      $post_ids = array_unique(array_merge($post_ids, $posts));
    }
    $count = count($post_ids);
    // bail early if no posts were found
    if( !$count ) return;
    // trigger save_post for each post with empty slugs
    foreach( $post_ids as $post_id ) {
      $this->save_post($post_id);
    }
    // add success message
    acfml()->admin->add_notice(
      'empty_slugs_notice',
      wp_sprintf( 
        __('ACF Multilingual successfully processed %s %s.', 'acfml'), 
        number_format_i18n($count),
        _n( 'post', 'posts', $count, 'acfml' )
      ),
      [
        'type' => 'success',
        'is_dismissible' => true
      ]
    );
  }

}
