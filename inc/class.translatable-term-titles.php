<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Translatable_Term_Titles extends Singleton {
  
  private $prefix;
  private $default_language;
  private $field_postfix = "term_name";
  private $field_name;
  private $field_key;

  public function __construct() {
    
    $this->prefix = acfml()->get_prefix();
    $this->default_language = acfml()->get_default_language();
    $this->field_name = "{$this->prefix}_{$this->field_postfix}";
    $this->field_key = "field_{$this->field_name}";
    $this->field_group_key = "group_{$this->field_name}";

    add_action('init', [$this, 'init'], PHP_INT_MAX);
    add_filter('admin_body_class', [$this, 'admin_body_class'], 20);
    add_filter('pre_insert_term', [$this, 'pre_insert_term'], 10, 2);
    add_filter('get_term', [$this, 'get_term'], 10, 2);
    add_action("acf/load_value/key={$this->field_key}_{$this->default_language}", [$this, "load_default_value"], 10, 3);
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
    
    $taxonomies = $this->get_translatable_taxonomies();
    
    // bail early if no post types support `translatable-title`
    if( !count($taxonomies) ) return;
    // generate location rules for translatable titles
    $location = [];
    foreach( $taxonomies as $tax ) {
      $location[] = [
        [
          'param' => 'taxonomy',
          'operator' => '==',
          'value' => $tax
        ]
      ];
    }
    
    acf_add_local_field_group([
      'key' => $this->field_group_key,
      'title' => __('Name'),
      'menu_order' => -1000,
      'style' => 'seamless',
      'position' => 'acf_after_title',
      'location' => $location,
    ]);

    acf_add_local_field(array(
      'key' => $this->field_key,
      'label' => __('Name'),
      'instructions' => __('The name is how it appears on your site.'),
      'name' => $this->field_name,
      'type' => 'text',
      'is_translatable' => true,
      'required' => true,
      'parent' => $this->field_group_key,
      'wrapper' => [
        'class' => str_replace('_', '-', $this->field_name)
      ]
    ));

  }

  /**
   * Get translatable taxonomies
   *
   * @return Array
   */
  private function get_translatable_taxonomies() {
    return  array_unique( apply_filters("{$this->prefix}/translatable_taxonomies", []) );
  }

  /**
   * Filter Admin Body Class
   *
   * @param String $class
   * @return String
   */
  public function admin_body_class($class) {
    global $pagenow, $taxonomy;
    if( !in_array($pagenow, ['term.php', 'edit-tags.php'] ) ) return $class;
    if( in_array($taxonomy, $this->get_translatable_taxonomies()) ) $class .= " supports-$this->prefix-title";
    return $class;
  }

  /**
   * Parse Custom Field value for term name
   *
   * @param [type] $term
   * @param [type] $taxonomy
   * @return void
   */
  public function pre_insert_term( $term, $taxonomy ) {
    $default_language_name = $_POST["acf"][$this->field_key]["{$this->field_key}_{$this->default_language}"] ?? null;
    if( !$term && $default_language_name ) $term = $default_language_name;
    return $term;
  }

  /**
   * Filter terms
   *
   * @param WP_Term $term
   * @param String $taxonomy
   * @return WP_Term
   */
  public function get_term($term, $taxonomy) {
    $language = acfml()->get_current_language();
    if( $custom_name = get_field($this->field_name, $term) ) {
      $term->name = $custom_name;
    }
    return $term;
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
    global $pagenow, $taxonomy;
    if( $value ) return $value;
    if( !in_array($pagenow, ['term.php']) ) return $value;
    if( is_string($post_id) && strpos($post_id, '_') !== false ) {
      $term_id = explode('_', $post_id)[1];
      remove_filter('get_term', [$this, 'get_term']);
      $value = get_term(intval($term_id), $taxonomy)->name;
      add_filter('get_term', [$this, 'get_term'], 10, 2);
    }
    return $value;
  }
}
