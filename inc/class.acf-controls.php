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
      add_filter("acf/load_value/type=group", [$this, 'load_translatable_field_value'], 10, 3);
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
    $post_type = $_GET['post_type'] ?? get_post_type();
    if( $post_type === 'acf-field-group' ) return $field;
    // bail early if field is empty or not translatable
    if( !is_array($field) || empty($field['is_translatable']) ) return $field;
    $admin_language = acfl()->get_admin_language();
    $default_language = acfl()->get_default_language();
    $sub_fields = [];
    foreach( acfl()->get_languages('iso') as $lang ) {
      // prepare wrapper
      $wrapper = $field['wrapper'];
      $wrapper['class'] .= ' acfl-field';
      if( $lang === $admin_language ) $wrapper['class'] .= ' is-visible';
      if( !empty($wrapper['id']) ) $wrapper['id'] = "{$wrapper['id']}--{$lang}";
      $wrapper['width'] = '';
      // prepare subfield
      $sub_field = array_merge($field, [
        'key' => "{$field['key']}_{$lang}",
        'label' => "{$field['label']} ({$lang})",
        'name' => "{$field['name']}_{$lang}",
        '_name' => $lang,
        'required' => $lang === $default_language && $field['required'],
        'is_translatable' => 0,
        'wrapper' => $wrapper
      ]);
      // add the subfield
      $sub_fields[] = $sub_field;
    }
    $label = $field['label'];
    if( $field['required'] ) $label .= " <span class=\"acf-required\">*</span>";
    $field = array_merge( $field, [
      'label' => $label,
      'type' => 'group',
      'layout' => 'block',
      'sub_fields' => $sub_fields,
      'required' => false,
      'wrapper' => [
        'width' >= $field['wrapper']['width'],
        'class' => $field['wrapper']['class'] . " acfl-group",
        'id' >= $field['wrapper']['id'],
      ],
    ]);
    return $field;
  }

  /**
   * Automatically parses possible value of previously non-translatable field
   *
   * @param Mixed $value
   * @param Int $post_id
   * @param Array $field
   * @return Mixed
   */
  public function load_translatable_field_value( $value, $post_id, $field ) {
    // bail early if field is empty or not translatable
    if( !$this->is_acfl_group($field) ) return $value;
    if( $value && !is_array($value) ) {
      update_field($field['key'], [
        "{$field['key']}_en" => $value
      ], $post_id);
    }
    return $value;
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
    if( !$this->is_acfl_group($field) ) return $value;
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
    if( !$this->is_acfl_group($field) ) return;
    $current_language = acfl()->get_admin_language();
    $languages = acfl()->get_languages();
    ob_start(); ?>
    <div class="acfl-tabs">
    <?php foreach( $languages as $language ) : ?>
    <a href="##" class="acfl-tab <?= $language['iso'] === $current_language ? 'is-active' : '' ?>" data-language="<?= $language['iso'] ?>">
      <?= $language['name'] ?>
    </a>
    <?php endforeach; ?>
    <span class="dashicons dashicons-info acf-js-tooltip acfl-info-icon" 
      title="<?= __('Double-click a language to switch all translatable fields', acfl()->prefix) ?>">
    </span>
    </div>
    <?php echo ob_get_clean();
  }

  /**
   * Check if a field is translatable
   *
   * @param Array|Boolean $field
   * @return Boolean
   */
  private function is_acfl_group($field) {
    return is_array($field) && $field['type'] === 'group' && !empty($field['is_translatable']);
  }
  
}