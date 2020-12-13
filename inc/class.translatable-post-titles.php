<?php 

namespace R\ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Translatable_Post_Titles extends Singleton {
  
  private $prefix;
  private $default_language;

  public function __construct() {
    $this->prefix = acfml()->get_prefix();
    $this->default_language = acfml()->get_default_language();
    add_action('init', [$this, 'init'], PHP_INT_MAX);
    add_filter('the_title', [$this, 'filter_post_title'], 10, 2);
    add_filter('admin_body_class', [$this, 'admin_body_class'], 20);
    add_action("acf/render_field/key=field_{$this->prefix}_post_title", [$this, 'render_slug_input']);
    add_action('wp_insert_post_data', [$this, 'wp_insert_post_data'], 10, 2);
  }

  /**
   * Init hook
   *
   * @return void
   */
  public function init() {
    $this->add_title_field_group();
  }

  /**
   * Get translatable post types
   *
   * @return Array
   */
  private function get_translatable_post_types() {
    return array_unique( apply_filters("{$this->prefix}/translatable_post_types", []) );
  }

  /**
   * Adds a custom field group for the  title
   * for each post_type that supports `acfml-title`
   *
   * @return void
   */
  private function add_title_field_group() {
    global $pagenow;
    
    $field_group_key = "{$this->prefix}_group_post_title";
    
    $post_types = $this->get_translatable_post_types();
    
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
    
    $instructions = 'Permalink: https://rassohilber.com';

    acf_add_local_field(array(
      'key' => "field_{$this->prefix}_post_title",
      'label' => 'Title',
      'instructions' => $instructions,
      'placeholder' => __('Title'),
      'name' => "{$this->prefix}_post_title",
      'type' => 'text',
      'is_translatable' => true,
      'required' => true,
      'parent' => $field_group_key,
      'wrapper' => [
        'class' => "$this->prefix-post-title"
      ]
    ));

  }

  /**
   * Renders the slug input
   *
   * @param Array $field
   * @return void
   */
  public function render_slug_input($field) {
    global $pagenow, $post_type, $post_type_object, $post;
    if( !in_array($pagenow, ['post.php', 'post-new.php']) ) return;
    if( !is_post_type_viewable( $post_type_object ) ) return;
    if( !current_user_can( $post_type_object->cap->publish_posts ) ) return;
    if( in_array(get_post_status( $post ), ['pending', 'auto-draft']) )  return;

    $sample_permalink_html = get_sample_permalink_html( $post->ID );
    if( !$sample_permalink_html ) return;
    ob_start() ?>
    <div id="edit-slug-box" class="hide-if-no-js">
      <?= $sample_permalink_html ?>
    </div>
    <?= wp_nonce_field( 'samplepermalink', 'samplepermalinknonce', false, false ); ?>
    <?php echo ob_get_clean();
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
  }

  /**
   * Filter Admin Body Class
   *
   * @param String $class
   * @return String
   */
  public function admin_body_class($class) {
    global $pagenow, $typenow;
    if( !in_array($pagenow, ['post.php', 'post-new.php']) ) return $class;
    if( in_array($typenow, $this->get_translatable_post_types()) ) $class .= " $this->prefix-supports-post-title";
    return $class;
  }

  /**
   * Save post title of default language
   *
   * @param [type] $post_id
   * @return void
   */
  public function wp_insert_post_data($data , $_post) {
    $default_language_title = $_post["acf"]["field_{$this->prefix}_post_title"]["field_{$this->prefix}_post_title_{$this->default_language}"] ?? null;
    if( $default_language_title ) $data['post_title'] = $default_language_title;
    return $data;
  }
}
