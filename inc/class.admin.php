<?php 

namespace R\ACFL;

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
        && $slug === acfl()->get_default_language() ) {
      remove_filter('unique_post_slug', [$this, 'unique_post_slug']);
      $slug = wp_unique_post_slug("$slug-1", $post_id, $post_status, $post_type, $post_parent);
      add_filter('unique_post_slug', [$this, 'unique_post_slug'], 10, 6);
    }
    
    return $slug;
  }

}
