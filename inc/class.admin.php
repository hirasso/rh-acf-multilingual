<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Admin extends Singleton {
  
  private $prefix;

  public function __construct() {
    $this->prefix = acfml()->get_prefix();
    add_filter('wp_unique_post_slug', [$this, 'unique_post_slug'], 10, 6);
    add_action('admin_notices', [$this, 'show_admin_notices']);
    add_action('acf/init', [$this, 'acf_init']);
  }

  /**
   * Runs on acf_init
   *
   * @return void
   */
  function acf_init() {
    //$this->add_options_page();
  }

  /**
   * Adds an options page for the plugin settings
   *
   * @return void
   */
  private function add_options_page() {
    acf_add_options_page([
      'page_title' => __('ACF Multilingual Settings'),
      'menu_title' => __('ACF Multilingual'),
      'menu_slug' => "$this->prefix-options",
      'capability' => 'manage_options',
      'parent_slug' => 'options-general.php'
    ]);
  }

  /**
   * Don't overwrite languages with post or top level page slugs
   *
   * @param string $slug
   * @param Int $post_id
   * @param string $post_status
   * @param string $post_type
   * @param Int $post_parent
   * @param string $original_slug
   * @param string
   */
  public function unique_post_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
    // add 'one' to slugs that would overwrite the  default language 
    // e.g. 'en' or 'de'
    if( in_array($post_type, ['post', 'page'])
        && !$post_parent
        && $slug === acfml()->get_default_language() ) {
      remove_filter('unique_post_slug', [$this, 'unique_post_slug']);
      $slug = wp_unique_post_slug("$slug-1", $post_id, $post_status, $post_type, $post_parent);
      add_filter('unique_post_slug', [$this, 'unique_post_slug'], 10, 6);
    }
    
    return $slug;
  }

  /**
   * Adds an admin notice
   *
   * @param string $key
   * @param string $message
   * @param string $type
   * @return void
   */
  public function add_admin_notice( $key, $message, $type = 'warning', $is_dismissible = false ) {
    $notices = get_transient("$this->prefix-admin-notices");
    if( !$notices ) $notices = [];
    $notices[$key] = [
      'message' => $message,
      'type' => $type,
      'is_dismissible' => $is_dismissible
    ];
    set_transient("$this->prefix-admin-notices", $notices);
  }

  /**
   * Shows admin notices from transient
   *
   * @return void
   */
  public function show_admin_notices() {
    $notices = get_transient("$this->prefix-admin-notices");
    delete_transient("$this->prefix-admin-notices");
    if( !is_array($notices) ) return;
    foreach( $notices as $notice ) {
      ob_start() ?>
      <div class="notice notice-<?= $notice['type'] ?> <?= $notice['is_dismissible'] ? 'is-dismissible' : '' ?>">
        <p><?= $notice['message'] ?></p>
      </div>
      <?php echo ob_get_clean();
    }
  }

}
