<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Config {
  
  public $languages = null;
  public $post_types = null;
  public $taxonomies = null;

  private $is_loaded = false;
  
  public function __construct() {}

  /**
   * Load the config file
   *
   * @return bool
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function load(): bool {

    $file = get_theme_file_path('acfml.config.json');
    if( !file_exists($file) ) return false;

    $this->is_loaded = true;
    $config = json_decode(file_get_contents($file));

    if( !empty($config->languages) ) $this->languages = $config->languages;
    if( !empty($config->post_types) ) $this->post_types = $config->post_types;
    if( !empty($config->taxonomies) ) $this->taxonomies = $config->taxonomies;

    return true;
  }

  /**
   * Is the config loaded
   *
   * @return boolean
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function is_loaded(): bool {
    return $this->is_loaded;
  }

}
