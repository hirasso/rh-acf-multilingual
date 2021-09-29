<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Fields_Controller {

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
    add_filter("acf/update_value/type=group", [$this, 'before_update_multilingual_value'], 9, 4);
    add_filter("acf/update_value/type=group", [$this, 'after_update_multilingual_value'], 12, 4);
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
      'label'         => __('Multilingual?', 'acfml'),
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
    $required_all = $field['acfml_all_required'] ?? false;

    // allow themes to alter ACFML-Fields
    $field = apply_filters('acfml/load_field/type=' . $field['type'], $field);
    $field = apply_filters('acfml/load_field/name=' . $field['_name'], $field);
    $field = apply_filters('acfml/load_field/key=' . $field['key'], $field);

    $ui_style = $this->get_field_ui_style($field);

    $default_language = acfml()->get_default_language();
    $sub_fields = [];
    $languages = acfml()->get_languages();
    foreach( $languages as $lang => $language_info ) {
      
      // prepare wrapper
      $wrapper = $field['wrapper'];
      $wrapper['class'] .= ' acfml-field';
      if( $lang === $active_language_tab || $ui_style !== 'tabs' ) $wrapper['class'] .= ' acfml-is-visible';
      if( !empty($wrapper['id']) ) $wrapper['id'] = "{$wrapper['id']}--{$lang}";
      $wrapper['width'] = '';
      // prepare subfield
      $sub_field = array_merge($field, [
        'key' => "{$field['key']}_{$lang}",
        'label' => "{$field['label']} ({$language_info['name']})",
        'name' => "{$field['name']}_{$lang}",
        '_name' => "$lang",
        // Only the default language of a sub-field should be required
        'required' => $required_all || $lang === $default_language && $field['required'],
        'acfml_multilingual' => 0,
        'acfml_multilingual_subfield' => 1,
        'wrapper' => $wrapper,
      ]);
      if( !empty($field['prepend']) ) {
        $sub_field['prepend'] = acfml()->convert_urls_in_string($field['prepend'], $lang);
      }
      if( $ui_style === 'simple' ) $sub_field['prepend'] = strtoupper($lang);
      
      // add the subfield
      $sub_fields[] = $sub_field;
    }
    
    // Add 'required'-indicator to the groups label, if it is set to required
    $label = $field['label'];
    if( $field['required'] ) $label .= " <span class=\"acf-required\">*</span>";
    $field_classes = explode(' ', $field['wrapper']['class']);
    $field_classes[] = "acfml-multilingual-field";
    $field_classes[] = "acfml-ui-style--$ui_style";
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
    // bail early if no value or array
    if( !$value || is_array($value) ) return $value;
    
    $default_language = acfml()->get_default_language();
    // This field's value will be autofilled by the monolingual value
    $hook_name = "acf/load_value/key={$field['key']}_$default_language";
    // A self-erasing hook, since filters would add up 
    // inside a repeater or flexible content field.
    // https://gist.github.com/stevegrunwell/c8307af5b88310ac1c49f6fa91f62bcb
    $self_erasing_hook = function() use ($value, $hook_name, &$self_erasing_hook) {
      remove_filter($hook_name, $self_erasing_hook);
      return $value;
    };
    add_filter($hook_name, $self_erasing_hook);
    
    return $value;
  }

  /**
   * Register a filter to run exactly one time.
   *
   * The arguments match that of add_filter(), but this function will also register a second
   * callback designed to remove the first immediately after it runs.
   *
   * @param string   $hook     The filter name.
   * @param callable $callback The callback function.
   * @param int      $priority Optional. The priority at which the callback should be executed.
   *                           Default is 10.
   * @param int      $args     Optional. The number of arguments expected by the callback function.
   *                           Default is 1.
   * @return bool Like add_filter(), this function always returns true.
   */
  public function add_filter_once( $hook, $callback, $priority = 10, $args = 1 ) {
    $singular = function () use ( $hook, $callback, $priority, $args, &$singular ) {
      call_user_func_array( $callback, func_get_args() );
      remove_filter( $hook, $singular, $priority, $args );
    };

    return add_filter( $hook, $singular, $priority, $args );
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
   * Applies custom "acfml_sanitize_callback" to field values before saving to the database.
   * Used for slugs
   *
   * @param mixed $value
   * @param int $post_id
   * @param array $field
   * @return mixed
   */
  public function before_update_multilingual_value( $value, $post_id, $field, $value_before ) {
    if( !$this->is_acfml_group($field) ) return $value;
    if( is_array($value) && !empty($field['acfml_sanitize_callback']) && function_exists($field['acfml_sanitize_callback']) ) {
      $value = array_map($field['acfml_sanitize_callback'], $value);
    }
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
  public function after_update_multilingual_value( $value, $post_id, $field, $value_before ) {
    if( !$this->is_acfml_group($field) ) return $value;
    $default_language = acfml()->get_default_language();
    $value = get_field("{$field['name']}_$default_language", $post_id, false);
    return $value;
  }

  /**
   * Field uses language tabs
   *
   * @param [type] $field
   * @return void
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  private function get_field_ui_style($field) {
    return $field['acfml_ui_style'] ?? 'tabs';
  }

  /**
   * Renders Language Tabs for multilingual fields
   *
   * @param Array $field
   * @return void
   */
  public function render_multilingual_field( $field ): void {
    if( !$this->is_acfml_group($field) ) return;
    $default_field_language = $this->get_active_language_tab($field);
    $languages = acfml()->get_languages();
    if( count($languages) < 2 ) return;
    if( ($field['acfml_show_ui'] ?? true) === false ) return;
    if( $this->get_field_ui_style($field) === 'tabs' ) {
      $this->render_language_tabs($languages, $default_field_language);
    }
    
  }

  /**
   * Renders language tabs for a field
   *
   * @param array $languages
   * @param string $default_field_language
   * @return void
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  private function render_language_tabs(array $languages, string $default_field_language): void {
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
  private function get_active_language_tab(array $field): string {
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