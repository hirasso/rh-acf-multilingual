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
   * @param Int $post_ID
   * @param String $post_status
   * @param String $post_type
   * @param Int $post_parent
   * @param String $original_slug
   * @return String
   */
  public function unique_post_slug( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
    if( !in_array($post_type, ['post', 'page']) || $post_parent ) return $slug;
    if( $slug === acfl()->get_default_language() ) $slug .= '-1';
    return $slug;
  }

}
