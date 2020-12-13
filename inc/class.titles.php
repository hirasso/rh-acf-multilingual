<?php 

namespace R\ACFL;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Titles extends Singleton {
  
  private $prefix;

  public function __construct() {
    $this->prefix = acfl()->get_prefix();
    add_action('init', [$this, 'init'], PHP_INT_MAX);
  }

  public function init() {
    $this->add_title_field_group();
  }

  private function add_title_field_group() {
    $field_group_key = "{$this->prefix}_title_group";
    $post_types  = get_post_types();
    $post_types = array_filter($post_types, function($pt) {
      return post_type_supports( $pt, 'title' ) && is_post_type_viewable( $pt );
    });
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

}
