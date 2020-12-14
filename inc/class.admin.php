<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Admin extends Singleton {
  
  public function __construct() {
    add_filter('wp_unique_post_slug', [$this, 'unique_post_slug'], 10, 6);
  }

  /**
   * Don't overwrite languages with post or top level page slugs
   *
   * @param String $slug
   * @param Int $post_id
   * @param String $post_status
   * @param String $post_type
   * @param Int $post_parent
   * @param String $original_slug
   * @return String
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
