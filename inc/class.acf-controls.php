<?php 

namespace R\ACFL;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AcfControls extends Singleton {

  private $translatable_field_types = [
    'text', 'textarea', 'url', 'image', 'file', 'wysiwyg', 'post_object'
  ];

  public function __construct() {
    $this->setup();
  }


  private function setup() {
    foreach( $this->translatable_field_types as $field_type ) {
      add_action("acf/render_field_settings/type=$field_type", [$this, 'render_field_settings'], 9);
      add_filter("acf/load_field/type=$field_type", [$this, 'load_field'], 20);
      add_filter("acf/format_value/type=group", [$this, 'format_value'], 11, 3);
    }
  }

  /**
   * Render field settings for translatable fields
   *
   * @param Array $field
   * @return void
   */
  function render_field_settings( $field ) {

    acf_render_field_setting( $field, array(
      'label'			=> __('Translatable?'),
      'instructions'	=> '',
      'name'			=> 'is_translatable',
      'type'			=> 'true_false',
      'ui'			=> 1,
    ), false);

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
    $default_language = acfl()->get_default_language();
    foreach( acfl()->get_languages('iso') as $lang ) {
      $sub_fields[] = array_merge($field, [
        'key' => "{$field['key']}_{$lang}",
        'label' => "{$field['label']} ({$lang})",
        'name' => "{$field['name']}_{$lang}",
        '_name' => $lang,
        'required' => $lang === $default_language && $field['required'],
        'wrapper' => [
          'width' => '',
          'class' => $field['class'] ?? '',
          'id' => $field['wrapper']['id'] ?  "{$field['wrapper']['id']}--{$lang}" : ''
        ]
      ]);
    }
    $field = array_merge( $field, [
      'type' => 'group',
      'layout' => 'block',
      'sub_fields' => $sub_fields,
      'required' => false
    ]);
    return $field;
  }

  /**
   * Formats a fields value
   *
   * @param [type] $value
   * @param [type] $post_id
   * @param [type] $field
   * @return void
   */
  function format_value( $value, $post_id, $field ) {
    if( !is_array($value) || empty($field['is_translatable']) ) return $value;
    $language = acfl()->get_language();
    $default_language = acfl()->get_default_language();
    $value = !empty($value[$language]) ? $value[$language] : ($value[$default_language] ?? null);
    return $value;
  }
  
}