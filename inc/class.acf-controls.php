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
      add_filter("acf/load_field/type=$field_type", [$this, 'load_translatable_field'], 20);
      add_filter("acf/format_value/type=group", [$this, 'format_translatable_field_value'], 11, 3);
      add_filter("acf/render_field/type=group", [$this, 'render_translatable_field'], 5);
    }
  }

  /**
   * Render field settings for translatable fields
   *
   * @param Array $field
   * @return void
   */
  public function render_field_settings( $field ) {

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
  public function load_translatable_field( $field ) {
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
        'is_translatable' => 0,
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
      'required' => false,
      'wrapper' => [
        'width' >= $field['wrapper']['width'],
        'class' => $field['wrapper']['class'] . " acfl-is-translatable",
        'id' >= $field['wrapper']['id'],
      ],
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
  public function format_translatable_field_value( $value, $post_id, $field ) {
    if( !$this->is_translatable_field($field) ) return $value;
    $language = acfl()->get_language();
    $default_language = acfl()->get_default_language();
    $value = !empty($value[$language]) ? $value[$language] : ($value[$default_language] ?? null);
    return $value;
  }

  /**
   * Renders Language Tabs for translatable fields
   *
   * @param Array $field
   * @return void
   */
  public function render_translatable_field( $field ) {
    if( !$this->is_translatable_field($field) ) return;
    $current_language = acfl()->get_admin_language();
    $languages = acfl()->get_languages();
    ob_start(); ?>
    <?php foreach( $languages as $language ) : ?>
    <button class="acfl-language-tab <?= $language['iso'] === $current_language ? 'is-active' : '' ?>" data-language="<?= $language['iso'] ?>">
      <?= $language['name'] ?>
    </button>
    <?php endforeach; ?>
    <?php echo ob_get_clean();
  }

  /**
   * Check if a field is translatable
   *
   * @param Array|Boolean $field
   * @return Boolean
   */
  private function is_translatable_field($field) {
    return is_array($field) && $field['type'] === 'group' && !empty($field['is_translatable']);
  }
  
}