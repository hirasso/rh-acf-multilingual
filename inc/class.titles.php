<?php 

namespace R\ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Titles extends Singleton {
  
  private $prefix;

  public function __construct() {
    $this->prefix = acfml()->get_prefix();
    add_action('init', [$this, 'init'], PHP_INT_MAX);
    add_filter('the_title', [$this, 'filter_post_title'], 10, 2);
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
      'parent' => $field_group_key,
      'wrapper' => [
        'class' => "$this->prefix-title"
      ]
    ));
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
    // $language = $this->get_language();
    // if( $language === $this->get_default_language() ) return $title;
    // if( $translated_title = get_field("title_$language", $post_id) ) {
    //   $title = $translated_title;
    // }
    // return $title;
  }
}
