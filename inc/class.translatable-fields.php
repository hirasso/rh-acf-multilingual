<?php 

namespace R\ACFL;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class TranslatableFields extends Singleton {

  // for which field types should 'is_translatable' be available?
  private $translatable_field_types = [
    'text', 'textarea', 'url', 'image', 'file', 'wysiwyg', 'post_object'
  ];

  private $prefix;

  /**
   * Constructor
   */
  public function __construct() {
    $this->prefix = acfl()->get_prefix();
    $this->add_hooks();
  }

  /**
   * Add hooks to ACF
   *
   * @return void
   */
  private function add_hooks() {
    // allow custom field types to be translatable, as well
    $translatable_field_types = apply_filters("$this->prefix/translatable_field_types", $this->translatable_field_types);
    // add hooks to translatable field types
    foreach( $translatable_field_types as $field_type ) {
      add_action("acf/render_field_settings/type=$field_type", [$this, 'render_field_settings'], 9);
      add_filter("acf/load_field/type=$field_type", [$this, 'load_translatable_field'], 20);
    }
    // filter field wrapper attributes
    // add_filter("acf/field_wrapper_attributes", [$this, 'acf_field_wrapper_attributes'], 10, 2);
    // add hooks for generated translatable fields (type of those will be 'group')
    add_filter("acf/format_value/type=group", [$this, 'format_translatable_field_value'], 11, 3);
    add_filter("acf/render_field/type=group", [$this, 'render_translatable_field'], 5);
    add_filter("acf/load_value/type=group", [$this, 'load_translatable_field_value'], 10, 3);
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
   * Load translatable fields. If a fields 'is_translatable' setting is set to 'true', then:
   * 
   *    - create one sub-field for each language with the same type of the field (e.g. text, textarea, ...)
   *    - create a field with type 'group' that will hold the different sub-fields
   *    – if the field is set to 'required', set the sub-field for the default language to required,
   *      but not the group itself
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
      // if the current sub-field is the same as the $admin_language, show it by default
      if( $lang === $admin_language ) $wrapper['class'] .= ' is-visible';
      if( !empty($wrapper['id']) ) $wrapper['id'] = "{$wrapper['id']}--{$lang}";
      $wrapper['width'] = '';
      // prepare subfield
      $sub_field = array_merge($field, [
        'key' => "{$field['key']}_{$lang}",
        'label' => "{$field['label']} ({$lang})",
        'name' => "{$field['name']}_{$lang}",
        '_name' => "$lang",
        // Only the default language of a sub-field should be required
        'required' => $lang === $default_language && $field['required'],
        'is_translatable' => 0,
        'wrapper' => $wrapper
      ]);
      // add the subfield
      $sub_fields[] = $sub_field;
    }
    // Add 'required'-indicator to the groups label, if it is set to required
    $label = $field['label'];
    if( $field['required'] ) $label .= " <span class=\"acf-required\">*</span>";
    // Change the $field to a group that will hold all sub-fields for all languages
    $field = array_merge( $field, [
      'label' => $label,
      'type' => 'group',
      'layout' => 'block',
      'sub_fields' => $sub_fields,
      'required' => false,
      'wrapper' => [
        'width' >= $field['wrapper']['width'],
        'class' => $field['wrapper']['class'] . " acfl-translatable-field",
        'id' >= $field['wrapper']['id'],
      ],
    ]);
    return $field;
  }

  /**
   * Automatically loads possible value of previously non-translatable field
   * to the sub_field assigned to the default language
   *
   * @param Mixed $value
   * @param Int $post_id
   * @param Array $field
   * @return Mixed
   */
  public function load_translatable_field_value( $value, $post_id, $field ) {
    // bail early if field is empty or not translatable
    if( !$this->is_acfl_group($field) ) return $value;
    $default_language = acfl()->get_default_language();
    if( is_string($value) && strlen($value) > 0 ) {
      add_filter("acf/load_value/key={$field['key']}_$default_language", function() use ($value) {
        return $value;
      });
    }
    return $value;
  }

  /**
   * Formats a fields value
   *
   * @param Mixed $value
   * @param Int $post_id
   * @param Array $field
   * @return Mixed formatted value
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
    <div class="acfl-tabs-wrap">
      <div class="acfl-tabs acf-js-tooltip" title="<?= __('Double-click to switch globally', $this->prefix) ?>">
      <?php foreach( $languages as $language ) : ?>
      <a href="##" class="acfl-tab <?= $language['iso'] === $current_language ? 'is-active' : '' ?>" data-language="<?= $language['iso'] ?>">
        <?= $language['name'] ?>
      </a>
      <?php endforeach; ?>
      <!-- <span class="dashicons dashicons-info acf-js-tooltip acfl-info-icon" 
        title="<?= __('Double-click a language to switch all translatable fields', $this->prefix) ?>">
      </span> -->
      </div>
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

  public function acf_field_wrapper_attributes($wrapper, $field) {
    if( $field['_name'] !== 'a_translatable_field_en' ) return $wrapper;
    // pre_dump([$field]);
    return $wrapper;
  }
  
}