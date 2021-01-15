<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ACFML_Fields {

  // for which field types should 'acfml_multilingual' be available?
  private $multilingual_field_types = [
    'text', 'textarea', 'url', 'image', 'file', 'wysiwyg', 'post_object', 'true_false'
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
    }
    // add hooks for generated multilingual fields (type of those will be 'group')
    add_filter("acf/format_value/type=group", [$this, 'format_multilingual_value'], 12, 3);
    add_filter("acf/update_value/type=group", [$this, 'update_multilingual_value'], 12, 3);
    add_action("acf/render_field/type=group", [$this, 'render_multilingual_field'], 5);
    add_filter("acf/load_value/type=group", [$this, 'inject_previous_monolingual_value'], 10, 3);
    add_filter("acf/field_wrapper_attributes", [$this, "field_wrapper_attributes"], 10, 2);
  }

  /**
   * Render field settings for multilingual fields
   *
   * @param Array $field
   * @return void
   */
  public function render_field_settings( $field ) {

    acf_render_field_setting( $field, array(
      'label'         => __('Multilingual?'),
      'instructions'	=> '',
      'name'          => 'acfml_multilingual',
      'type'          => 'true_false',
      'ui'            => 1,
    ), false);

  }

  /**
   * Load multilingual fields. If a field's 'acfml_multilingual' setting is set to 'true', then:
   * 
   *    - create one sub-field for each language with the same type of the field (e.g. text, textarea, ...)
   *    - create a field of type 'group' that will hold the different sub-fields
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
    // bail early if the field is not multilingual
    if( empty($field['acfml_multilingual']) ) return $field;
    
    $active_language_tab = $this->get_active_language_tab($field);

    $default_language = acfml()->get_default_language();
    $sub_fields = [];
    $languages = acfml()->get_languages('slug');
    foreach( $languages as $id => $lang ) {
      // prepare wrapper
      $wrapper = $field['wrapper'];
      $wrapper['class'] .= ' acfml-field';
      if( $lang === $active_language_tab ) $wrapper['class'] .= ' is-visible';
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
        'acfml_multilingual' => 0,
        'acfml_multilingual_subfield' => 1,
        'wrapper' => $wrapper,
      ]);
      // add the subfield
      $sub_fields[] = $sub_field;
    }
    // Add 'required'-indicator to the groups label, if it is set to required
    $label = $field['label'];
    if( $field['required'] ) $label .= " <span class=\"acf-required\">*</span>";
    $field_classes = explode(' ', $field['wrapper']['class']);
    $field_classes[] = "acfml-multilingual-field";
    // Change the $field to a group that will hold all sub-fields for all languages
    
    $field = array_merge( $field, [
      'label' => $label,
      'type' => 'group',
      'layout' => 'block',
      'sub_fields' => $sub_fields,
      'required' => false,
      'wrapper' => [
        'width' => $field['wrapper']['width'],
        'class' => implode(' ', $field_classes),
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
   * Formats a fields value
   *
   * @param Mixed $value
   * @param Int $post_id
   * @param Array $field
   * @return Mixed formatted value
   */
  public function format_multilingual_value( $value, $post_id, $field ) {
    if( !$this->is_acfml_group($field) ) return $value;
    $language = acfml()->get_current_language();
    $value = !empty($value[$language]) ? $value[$language] : ($value[acfml()->get_default_language()] ?? null);
    return $value;
  }

  /**
   * Write the default language's $value to the group $value itself
   *
   * @param mixed $value
   * @param int $post_id
   * @param array $field
   * @return mixed
   */
  public function update_multilingual_value( $value, $post_id, $field ) {
    if( !$this->is_acfml_group($field) ) return $value;
    $default_language = acfml()->get_default_language();
    $value = get_field("{$field['name']}_$default_language", $post_id, false);
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
    $default_field_language = $this->get_active_language_tab($field);
    $languages = acfml()->get_languages();
    if( count($languages) < 2 ) return $field;
    $show_ui = $field['acfml_ui'] ?? true;
    if( !$show_ui ) return;
    // maybe remove default language
    ob_start(); ?>
    <div class="acfml-tabs-wrap">
      <div class="acfml-tabs acf-js-tooltip" title="<?= __('Double-click to switch globally', $this->prefix) ?>">
      <?php foreach( $languages as $id => $language ) : ?>
      <a href="##" class="acfml-tab <?= $language['slug'] === $default_field_language ? 'is-active' : '' ?>" data-language="<?= $language['slug'] ?>">
        <?= $language['name'] ?>
      </a>
      <?php endforeach; ?>
      </div>
    </div>
    <?php echo ob_get_clean();
  }

  /**
   * Get the default language for an ACF field in the admin
   *
   * @param Array $field
   * @return string
   */
  private function get_active_language_tab($field): string {
    $cookie = (array) acfml()->get_admin_cookie('acfml_language_tabs');
    return $cookie[$field['key']] ?? acfml()->get_default_language();
  }

  /**
   * Check if a field is multilingual
   *
   * @param Array|Boolean $field
   * @return Boolean
   */
  private function is_acfml_group($field) {
    return is_array($field) && $field['type'] === 'group' && !empty($field['acfml_multilingual']);
  }

  /**
   * Filter field wrapper
   *
   * @param Array $wrapper
   * @param Array $field
   * @return Array
   */
  public function field_wrapper_attributes($wrapper, $field) {
    if( $switch_with = $field['acfml_ui_listen_to'] ?? null ) {
      $wrapper['data-acfml-ui-listen-to'] = $switch_with;
    }
    if( !empty($field['acfml_multilingual_subfield']) && acfml()->is_default_language($field['_name']) ) {
      $wrapper['class'] .= ' acfml-is-default-language';
    }
    return $wrapper;
  }
  
}