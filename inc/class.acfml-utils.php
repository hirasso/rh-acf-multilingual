<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ACFML_Utils {
  
  private $prefix;

  public function __construct() {
    $this->prefix = acfml()->get_prefix();
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
      'page_title' => __('ACF Multilingual Settings', 'acfml'),
      'menu_title' => __('ACF Multilingual', 'acfml'),
      'menu_slug' => "$this->prefix-options",
      'capability' => 'manage_options',
      'parent_slug' => 'options-general.php'
    ]);
  }

  /**
   * Adds an admin notice
   *
   * @param string $key
   * @param string $message
   * @param string $type
   * @param boolean $is_complex
   * @return void
   */
  public function add_admin_notice( $key, $message, $type = 'warning', $is_dismissible = false, $is_complex = false ) {
    $notices = get_transient("$this->prefix-admin-notices");
    if( !$notices ) $notices = [];
    if( !$is_complex ) $message = "<p>$message</p>";
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
        <?= $notice['message'] ?>
      </div>
      <?php echo ob_get_clean();
    }
  }

}
