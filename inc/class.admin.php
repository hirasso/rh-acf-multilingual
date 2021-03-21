<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Admin {
  
  private $prefix;

  public function __construct() {
    $this->prefix = acfml()->get_prefix();
    add_action('admin_init', [$this, 'maybe_show_acf_missing_notice']);
    add_action('admin_notices', [$this, 'show_added_notices']);
  }

  /**
   * Must be called from ACFML
   *
   * @return void
   */
  public function add_hooks() {
    add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);
    add_action('admin_init', [$this, 'maybe_set_admin_language']);
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
  public function add_notice( $key, $message, $args = [] ) {
    // create the $notice object
    $notice = wp_parse_args($args, [
      'key' => $key,
      'message' => $message,
      'type' => 'warning', 
      'is_dismissible' => false,
    ]);
    
    // add the notice to the transient
    $notices = get_transient($this->get_transient_name()) ?: [];
    $notices[$key] = $notice;
    set_transient($this->get_transient_name(), $notices);
  }

  /**
   * Get the transient name for the current user
   *
   * @return void
   */
  private function get_transient_name() {
    $user_id = get_current_user_id();
    return "acfml-admin-notices-$user_id";
  }

  /**
   * Shows admin notices from transient
   *
   * @return void
   */
  public function show_added_notices() {
    if( !defined('ACF') ) return;
    $notices = get_transient($this->get_transient_name()) ?: [];
    foreach( $notices as $notice ) {
      $this->show_notice($notice['message'], $notice);
    }
    delete_transient($this->get_transient_name());
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
   * Hash something
   *
   * @param mixed $value
   * @return string
   */
  private function get_hashed_settings(): string {
    $settings = [
      'languages' => acfml()->get_languages('slug'),
      'post_types' => acfml()->post_types_controller->get_multilingual_post_types('full', false)
    ];
    $hash = md5( json_encode($settings) );
    return $hash;
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
    $languages = acfml()->get_languages();
    if( !count($languages) ) return;

    $hashed_settings = $this->get_hashed_settings();
    // delete_option('acfml_hashed_settings');
    
    $settings_changed = !hash_equals($hashed_settings, (string) get_option('acfml_hashed_settings'));
    
    if( !$settings_changed ) return;
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
    // update settings hash
    update_option('acfml_hashed_settings', $this->get_hashed_settings());
    // flush the rules
    flush_rewrite_rules();
  }

  /**
   * Adds the admin bar menu
   * 
   * @param \WP_Admin_Bar $wp_adminbar
   * @return void
   */
  public function add_admin_bar_menu(\WP_Admin_Bar $wp_adminbar) {

    $current_language = acfml()->get_language_info(acfml()->get_current_language());

    $icon = "<span class='ab-icon acfml-ab-icon dashicons dashicons-translation'></span>";
    $title = $current_language['name'];

    $wp_adminbar->add_node([
      'parent' => 'top-secondary',
      'id' => 'acfml',
      'title' => "$icon $title",
      'meta'  => [ 'title' => __( 'Switch your admin language', 'acfml' ) ],
    ]);

    $switcher = acfml()->get_language_switcher([
      'format' => 'raw'
    ]);

    foreach( $switcher as $item ) {
      if( $item['is_current'] ) continue;
      $wp_adminbar->add_node([
        'parent' => 'acfml',
        'id' => "acfml-switch-{$item['slug']}",
        'title' => "{$item['display_name']}",
        'meta'  => [ 'title' => sprintf( __( 'Switch to %s', 'acfml' ), $item['display_name']) ],
        'href' => $item['url']
      ]);
    }

  }

  /**
   * Set the admin language and reload
   *
   * @return void
   */
  public function maybe_set_admin_language() {
    // bail early if this is an AJAX request
    if( wp_doing_ajax() ) return;
    // get the language from the URL
    $lang_GET = $_GET['lang'] ?? null;
    // bail early if no 'lang' param found in $_GET
    if( !$lang_GET ) return;
    $languages = acfml()->get_languages();
    // bail early if the requested language is not installed
    if( !array_key_exists($lang_GET, $languages) ) return;
    $language = $languages[$lang_GET];
    $locale = $language['locale'];
    // bail early if the user locale is the same as the requested already
    if( $locale === get_user_locale() ) return;
    // update the current users locale, redirect afterwards
    $user = wp_get_current_user();
    update_user_meta($user->ID, 'locale', $locale);
    // add a notice
    $this->add_notice(
      'acfml-changed-admin-language', 
      sprintf(__('Changed the admin language to %s'), $language['name']),
      [
        'type' => 'success',
        'is_dismissible' => true
      ]
    );
    // do the redirect
    wp_redirect( remove_query_arg('lang') );
    exit;
  }

  /**
   * Renders a notice of ACF is not installed
   *
   * @return void
   */
  public function  maybe_show_acf_missing_notice() {
    if( defined('ACF') ) return;
    $locale = determine_locale();
    $message = wp_sprintf(
      __("ACF Multilingual requires the plugin %s to be installed and activated.", 'acfml'),
      '<a href="https://www.advancedcustomfields.com/" target="_blank">Advanced Custom Fields</a>',
    );
    $this->show_notice($message, [
      'type' => 'error'
    ]);
  }

}
