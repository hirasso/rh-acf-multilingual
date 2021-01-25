<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ACFML_Admin {
  
  private $prefix;

  public function __construct() {
    $this->prefix = acfml()->get_prefix();
    add_action('admin_notices', [$this, 'show_added_notices']);
    add_action('acf/init', [$this, 'acf_init']);
  }

  /**
   * Runs on acf_init
   *
   * @return void
   */
  function acf_init() {
    //$this->add_options_page();
    add_action('admin_init', [$this, 'maybe_show_notice_flush_rewrite_rules']);
    add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
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
  public function add_notice( $key, $message, $args = [] ) {
    // create the $notice object
    $notice = wp_parse_args($args, [
      'key' => $key,
      'message' => $message,
      'type' => 'warning', 
      'is_dismissible' => false,
    ]);
    // add the notice to the transient
    $notices = get_transient("$this->prefix-admin-notices") ?: [];
    $notices[$key] = $notice;
    set_transient("$this->prefix-admin-notices", $notices);
  }

  /**
   * Shows admin notices from transient
   *
   * @return void
   */
  public function show_added_notices() {
    if( !defined('ACF') ) return;
    $notices = get_transient("$this->prefix-admin-notices") ?: [];
    foreach( $notices as $notice ) {
      $this->show_notice($notice['message'], $notice);
    }
    delete_transient("$this->prefix-admin-notices");
  }

  /**
   * Show an admin notice
   *
   * @param object $notice
   * @return void
   */
  public function show_notice(string $message, array $args = []) {
    // if the messsage is naked, wrap it inside a <p>-tag
    if( strpos($message, '<p>') === false ) $message = "<p>$message</p>";
    // parse the args
    $args = acfml()->to_object(wp_parse_args($args, [
      'type' => 'warning', 
      'is_dismissible' => false,
      'key' => ''
    ]));
    $id = str_replace('_', '-', $args->key);
    ob_start() ?>
    <div id="acfml-notice--<?= $id ?>" class="notice acfml-admin-notice notice-<?= $args->type ?> <?= $args->is_dismissible ? 'is-dismissible' : '' ?>">
      <?= $message ?>
    </div>
    <?php echo ob_get_clean();
  }

  /**
   * Get a hash of the active languages
   *
   * @return string
   */
  private function get_hashed_languages(): string {
    return md5( json_encode(acfml()->get_languages('slug')) );
  }

  /**
   * Verifies a nonce for a certain action
   *
   * @param string $action
   * @return bool
   */
  public function verify_nonce($action): bool {
    $nonce = $_POST['_acfml_nonce'] ?? null;
    if( !$nonce ) return false;
    return wp_verify_nonce($nonce, $action);
  }

  /**
   * Check for changes in the language settings.
   * Show a notice to flush the rewrite rules if a change was detected
   *
   * @return void
   */
  public function maybe_show_notice_flush_rewrite_rules(): void {
    $hashed_languages = $this->get_hashed_languages();
    // delete_option('acfml_hashed_languages');
    $saved_hashed_languages = (string) get_option('acfml_hashed_languages');
    if( hash_equals($hashed_languages, $saved_hashed_languages) ) return;
    // add nag to flush the rewrite rules
    $this->add_notice(
      'acfml_flush_rewrite_rules',
      acfml()->get_template('notice-flush-rewrite-rules', null, false)
    ); 
  }


  /**
   * Flushes Rewrite Rules if asked for it
   *
   * @return void
   */
  public function maybe_flush_rewrite_rules() {
    if( !$this->verify_nonce('acfml_flush_rewrite_rules') ) return;
    // add success notice
    $this->add_notice(
      'acfml_flush_rewrite_rules',
      __('Rewrite Rules successfully flushed', 'acfml'),
      [
        'type' => 'success',
        'is_dismissible' => true
      ]
    );
    // update the option
    update_option('acfml_hashed_languages', $this->get_hashed_languages());
    // flush the rules
    flush_rewrite_rules();
  }

}
