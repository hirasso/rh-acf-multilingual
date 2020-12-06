<?php 

namespace R\MultiLang;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class MultiLangAcfField extends Singleton {

  private $translatable_field_types = [
    'text', 'textarea', 'url', 'image', 'file', 'wysiwyg', 'post_object'
  ];

  public function __construct() {
    $this->setup();
  }


  private function setup() {
    // add_action("acf/render_field_settings/", [$this, 'render_field_settings']);
    foreach( $this->translatable_field_types as $field_type ) {
      add_action("acf/render_field_settings/type=$field_type", [$this, 'render_field_settings'], 9);
      add_filter("acf/load_field/type=$field_type", [$this, 'load_field']);
    }
  }

  /**
   * Render field settings for translatable fields
   *
   * @param Array $field
   * @return void
   */
  function render_field_settings( $field ) {
    // pre_dump($field);

    // if( !in_array($field['type'], $this->translatable_field_types ) ) return;

    acf_render_field_setting( $field, array(
      'label'			=> __('Translatable?'),
      'instructions'	=> '',
      'name'			=> 'is_translatable',
      'type'			=> 'true_false',
      'ui'			=> 1,
    ), true);

  }

  /**
   * Load translatable fields
   *
   * @param Array $field
   * @return void
   */
  function load_field( $field ) {
    $post_type = get_post_type();
    if( !$post_type ) $post_type = $_GET['post_type'] ?? false;
    if( $post_type === 'acf-field-group' ) return $field;
    // bail early if field is empty or not translatable
    if( !is_array($field) || empty($field['is_translatable']) ) return $field;
    $sub_fields = [];
    foreach( ml()->get_enabled_languages() as $lang ) {
      $sub_fields[] = array_merge($field, [
        'key' => "{$field['key']}_{$lang}",
        'label' => "{$field['label']} ({$lang})",
        'name' => "{$field['name']}_{$lang}",
        '_name' => $lang,
      ]);
    }
    $field = array_merge( $field, [
      'type' => 'group',
      'layout' => 'block',
      'sub_fields' => $sub_fields,
    ]);
    return $field;
  } 
  
}