<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Translatable_Post_Titles extends Singleton {
  
  private $prefix;
  private $default_language;
  private $field_postfix = "post_title";
  private $field_name;
  private $field_key;

  public function __construct() {
    
    $this->prefix = acfml()->get_prefix();
    $this->default_language = acfml()->get_default_language();
    $this->field_name = "{$this->prefix}_{$this->field_postfix}";
    $this->field_key = "field_{$this->field_name}";
    $this->field_group_key = "group_{$this->field_name}";

    add_action('init', [$this, 'init'], PHP_INT_MAX);
    add_filter('the_title', [$this, 'filter_post_title'], 10, 2);
    add_filter('admin_body_class', [$this, 'admin_body_class'], 20);
    add_action("acf/render_field/key={$this->field_key}", [$this, 'render_field']);
    add_action("acf/load_value/key={$this->field_key}_{$this->default_language}", [$this, "load_default_value"], 10, 3);
    add_action('wp_insert_post_data', [$this, 'wp_insert_post_data'], 10, 2);
  }

  /**
   * Init hook
   *
   * @return void
   */
  public function init() {
    foreach( $this->get_translatable_post_types() as $post_type ) {
      remove_post_type_support($post_type, 'title');
    }
    $this->add_title_field_group();
  }

  /**
   * Get translatable post types
   *
   * @return Array
   */
  private function get_translatable_post_types() {
    return array_unique( apply_filters("{$this->prefix}/translatable_post_types", []) );
  }

  /**
   * Adds a custom field group for the  title
   * for each post_type that supports `acfml-title`
   *
   * @return void
   */
  private function add_title_field_group() {
    global $pagenow;
    
    $post_types = $this->get_translatable_post_types();
    
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
      'key' => $this->field_group_key,
      'title' => "Title",
      'menu_order' => -1000,
      'style' => 'seamless',
      'position' => 'acf_after_title',
      'location' => $location,
    ]);
    
    $instructions = 'Permalink: https://rassohilber.com';

    acf_add_local_field(array(
      'key' => $this->field_key,
      'label' => 'Title',
      'instructions' => $instructions,
      'placeholder' => __( 'Add title' ),
      'name' => $this->field_name,
      'type' => 'text',
      'is_translatable' => true,
      'required' => true,
      'parent' => $this->field_group_key,
      'wrapper' => [
        'class' => str_replace('_', '-', $this->field_name),
        'id' => 'titlediv',
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
    $this->render_slug_box($field);
  }

  /**
   * Renders the slug box. 
   * Inspired by code found in /wp-admin/edit-form-advanced.php
   *
   * @param Array $field
   * @return void
   */
  private function render_slug_box($field) {
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
   * @param String $title
   * @param Int $post_id
   * @return String
   */
  public function filter_post_title($title, $post_id) {
    $acfml_title = get_field($this->field_name, $post_id);
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
   * @param String $class
   * @return String
   */
  public function admin_body_class($class) {
    global $pagenow, $typenow;
    if( !in_array($pagenow, ['post.php', 'post-new.php']) ) return $class;
    if( in_array($typenow, $this->get_translatable_post_types()) ) $class .= " $this->prefix-supports-post-title";
    return $class;
  }

  /**
   * Save post title of default language
   *
   * @param [type] $post_id
   * @return void
   */
  public function wp_insert_post_data($data , $_post) {
    $default_language_post_title = $_post["acf"][$this->field_key]["{$this->field_key}_{$this->default_language}"] ?? null;
    if( $default_language_post_title ) $data['post_title'] = $default_language_post_title;
    return $data;
  }
}
