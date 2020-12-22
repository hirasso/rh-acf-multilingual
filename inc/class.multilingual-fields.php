<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Multilingual_Fields extends Singleton {

  // for which field types should 'is_multilingual' be available?
  private $multilingual_field_types = [
    'text', 'textarea', 'url', 'image', 'file', 'wysiwyg', 'post_object'
  ];

  private $prefix;

  /**
   * Constructor
   */
  public function __construct() {
    $this->prefix = acfml()->get_prefix();
    $this->add_hooks();
  }

  /**
   * Add hooks to ACF
   *
   * @return void
   */
  private function add_hooks() {
    // allow custom field types to be multilingual
    $multilingual_field_types = apply_filters("$this->prefix/multilingual_field_types", $this->multilingual_field_types);
    // add hooks to multilingual field types
    foreach( $multilingual_field_types as $field_type ) {
      add_action("acf/render_field_settings/type=$field_type", [$this, 'render_field_settings'], 9);
      add_filter("acf/load_field/type=$field_type", [$this, 'load_multilingual_field'], 20);
      add_filter("acf/load_value/type=$field_type", [$this, 'inject_previous_multilingual_default_language_value'], 10, 3);
    }
    // add hooks for generated multilingual fields (type of those will be 'group')
    add_filter("acf/format_value/type=group", [$this, 'format_multilingual_field_value'], 12, 3);
    add_action("acf/render_field/type=group", [$this, 'render_multilingual_field'], 5);
    add_filter("acf/load_value/type=group", [$this, 'inject_previous_monolingual_value'], 10, 3);
  }

  /**
   * Render field settings for multilingual fields
   *
   * @param Array $field
   * @return void
   */
  public function render_field_settings( $field ) {

    acf_render_field_setting( $field, array(
      'label'			=> __('Multilingual?'),
      'instructions'	=> '',
      'name'			=> 'is_multilingual',
      'type'			=> 'true_false',
      'ui'			=> 1,
    ), false);

  }

  /**
   * Load multilingual fields. If a fields 'is_multilingual' setting is set to 'true', then:
   * 
   *    - create one sub-field for each language with the same type of the field (e.g. text, textarea, ...)
   *    - create a field with type 'group' that will hold the different sub-fields
   *    – if the field is set to 'required', set the sub-field for the default language to required,
   *      but not the group itself
   *
   * @param Array $field
   * @return void
   */
  public function load_multilingual_field( $field ) {
    global $post, $post_type;
    // return of no $field
    if( !is_array($field) ) return $field;
    // return of on acf-field-group edit screen
    $post_type = $_GET['post_type'] ?? get_post_type();
    if( $post_type === 'acf-field-group' ) return $field;
    // bail early if field is empty or not multilingual
    if( empty($field['is_multilingual']) ) return $field;

    $default_language = acfml()->get_default_language();
    $sub_fields = [];

    $languages = acfml()->get_languages('slug');
    
    foreach( $languages as $id => $lang ) {
      // prepare wrapper
      $wrapper = $field['wrapper'];
      $wrapper['class'] .= ' acfml-field';
      if( $id === 0 ) $wrapper['class'] .= ' is-visible';
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
        'is_multilingual' => 0,
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
        'class' => $field['wrapper']['class'] . " acfml-multilingual-field",
        'id' => $field['wrapper']['id'],
      ],
    ]);
    return $field;
  }


  /**
   * Automatically loads possible value of previously monolingual field
   * to the sub_field assigned to the default language
   *
   * @param Mixed $value
   * @param Int $post_id
   * @param Array $field
   * @return Mixed
   */
  public function inject_previous_monolingual_value( $value, $post_id, $field ) {
    // bail early if field is empty or not multilingual
    if( !$this->is_acfml_group($field) ) return $value;
    // parse value from before the field became multilingual to the default value
    $default_language = acfml()->get_default_language();
    if( $value && !is_array($value) ) {
      add_filter("acf/load_value/key={$field['key']}_$default_language", function($sub_field_value) use ($value) {
        return $sub_field_value ? $sub_field_value : $value;
      });
    }
    return $value;
  }

  /**
   * Inject a previous multilingual default language value
   *
   * @param mixed $value
   * @param int $post_id
   * @param array $field
   * @return mixed
   */
  public function inject_previous_multilingual_default_language_value( $value, $post_id, $field ) {
    if( !empty($field['is_multilingual']) ) return $value;
    $default_language = acfml()->get_default_language();
    if( !$value ) $value = get_field("{$field['name']}_{$default_language}", $post_id, false);
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
  public function format_multilingual_field_value( $value, $post_id, $field ) {
    if( !$this->is_acfml_group($field) ) return $value;
    $language = acfml()->get_current_language();
    $value = !empty($value[$language]) ? $value[$language] : ($value[acfml()->get_default_language()] ?? null);
    return $value;
  }

  /**
   * Renders Language Tabs for multilingual fields
   *
   * @param Array $field
   * @return void
   */
  public function render_multilingual_field( $field ) {
    if( !$this->is_acfml_group($field) ) return;
    $default_language = acfml()->get_default_language();
    $languages = acfml()->get_languages();
    // maybe remove default language
    ob_start(); ?>
    <div class="acfml-tabs-wrap">
      <div class="acfml-tabs acf-js-tooltip" title="<?= __('Double-click to switch globally', $this->prefix) ?>">
      <?php foreach( $languages as $id => $language ) : ?>
      <a href="##" class="acfml-tab <?= $id === 0 ? 'is-active' : '' ?>" data-language="<?= $language['slug'] ?>">
        <?= $language['name'] ?>
      </a>
      <?php endforeach; ?>
      </div>
    </div>
    <?php echo ob_get_clean();
  }

  /**
   * Check if a field is multilingual
   *
   * @param Array|Boolean $field
   * @return Boolean
   */
  private function is_acfml_group($field) {
    return is_array($field) && $field['type'] === 'group' && !empty($field['is_multilingual']);
  }

  /**
   * Filter field wrapper
   *
   * @param Array $wrapper
   * @param Array $field
   * @return Array
   */
  public function acf_field_wrapper_attributes($wrapper, $field) {
    return $wrapper;
  }
  
}