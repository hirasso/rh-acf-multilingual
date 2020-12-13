<?php 

namespace R\ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Titles extends Singleton {
  
  private $prefix;
  private $default_language;

  public function __construct() {
    $this->prefix = acfml()->get_prefix();
    $this->default_language = acfml()->get_default_language();
    add_action('init', [$this, 'init'], PHP_INT_MAX);
    add_filter('the_title', [$this, 'filter_post_title'], 10, 2);
    add_filter('admin_body_class', [$this, 'admin_body_class'], 20);
    add_action("acf/render_field/key=field_{$this->prefix}_title_$this->default_language", [$this, 'render_slug_input']);
    add_action('wp_insert_post_data', [$this, 'wp_insert_post_data'], 10, 2);
  }

  public function init() {
    $this->add_title_field_group();
    
  }

  /**
   * Adds a custom field group for the  title
   * for each post_type that supports `acfml-title`
   *
   * @return void
   */
  private function add_title_field_group() {
    $field_group_key = "{$this->prefix}_title_group";
    // find all post types that support `translatable-title`
    $post_types = array_filter(get_post_types(), function($pt) {
      return post_type_supports( $pt, "$this->prefix-title" );
    });
    // bail early if no post types support `translatable-title`
    if( !count($post_types) ) return;
    // generate location rules for translatable titles
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
    
    acf_add_local_field_group([
      'key' => $field_group_key,
      'title' => "Title",
      'menu_order' => -1000,
      'style' => 'seamless',
      'position' => 'acf_after_title',
      'location' => $location,
    ]);
    
    acf_add_local_field(array(
      'key' => "field_{$this->prefix}_title",
      'label' => '',
      'placeholder' => 'Title',
      'name' => "{$this->prefix}_title",
      'type' => 'text',
      'is_translatable' => true,
      'required' => true,
      'parent' => $field_group_key,
      'wrapper' => [
        'class' => "$this->prefix-title"
      ]
    ));

  }

  /**
   * Renders the slug input
   *
   * @param Array $field
   * @return void
   */
  public function render_slug_input($field) {
    echo 'TODO: render slug input';
  }

  /**
   * Filter title
   *
   * @param String $title
   * @param Int $post_id
   * @return String
   */
  public function filter_post_title($title, $post_id) {
    $acfml_title = get_field('acfml_title', $post_id);
    return $acfml_title ? $acfml_title : $title;
  }

  /**
   * Filter Admin Body Class
   *
   * @param String $class
   * @return String
   */
  public function admin_body_class($class) {
    global $pagenow, $typenow;
    if( !in_array($pagenow, ['post.php', 'post-new.php']) ) return $class;
    if( post_type_supports( $typenow, "$this->prefix-title" ) ) $class .= " supports-$this->prefix-title";
    return $class;
  }

  /**
   * Save post title of default language
   *
   * @param [type] $post_id
   * @return void
   */
  public function wp_insert_post_data($data , $_post) {
    $default_language_title = $_post["acf"]["field_{$this->prefix}_title"]["field_{$this->prefix}_title_{$this->default_language}"] ?? null;
    if( $default_language_title ) $data['post_title'] = $default_language_title;
    return $data;
  }
}
