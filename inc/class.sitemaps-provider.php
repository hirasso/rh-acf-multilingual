<?php 

namespace ACFML;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Sitemaps
 */
class ACFML_Sitemaps_Provider extends \WP_Sitemaps_Provider {

  private $acfml = null;

  /**
   * Constructor
   *
   * @param ACF_Multilingual|null $acfml
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  public function __construct(?\ACF_Multilingual $acfml = null) {

    // inject main class
    $this->acfml       = $acfml;
    $this->name        = 'languages';
    $this->object_type = 'language';
  }

  /**
  * Gets a URL list for a sitemap.
  *
  * @since 5.5.0
  *
  * @param int    $page_num       Page of results.
  * @param string $object_subtype Optional. Object subtype name. Default empty.
  * @return array Array of URLs for a sitemap.
  */
  public function get_url_list($page_num, $object_subtype = '') {
    global $wp_sitemaps;
    $url_list = [];
    $index_url = $wp_sitemaps->index->get_index_url();
    // get language information
    $languages = $this->acfml->get_languages('slug');
    $default_language = $this->acfml->get_default_language();
    $current_language = $this->acfml->get_current_language();

    foreach( $languages as $lang ) {
      // don't generate a link to the default language
      if( $lang === $default_language ) continue;
      $sitemaps_entry = array(
        'loc' => esc_url( $this->acfml->simple_convert_url($index_url, $lang) ),
      );
      $url_list[] = $sitemaps_entry;
    }
    
    return $url_list;
  }


  /**
   * Gets the URL of a sitemap entry.
   *
   * @since 5.5.0
   *
   * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
   *
   * @param string $name The name of the sitemap.
   * @param int    $page The page of the sitemap.
   * @return string The composed URL for a sitemap entry.
   */
  public function get_sitemap_url( $name, $page ) {
    global $wp_rewrite;

    // Accounts for cases where name is not included, ex: sitemaps-users-1.xml.
    $params = array_filter(
      array(
        'sitemap'         => $this->name,
        'sitemap-subtype' => $name,
        'paged'           => $page,
      )
    );

    $basename = sprintf(
      '/wp-sitemap-%1$s.xml',
      implode( '-', $params )
    );

    if ( ! $wp_rewrite->using_permalinks() ) {
      $basename = '/?' . http_build_query( $params, '', '&' );
    }

    return home_url( $basename );
  }
  
  /**
   * Lists sitemap pages exposed by this provider.
   *
   * The returned data is used to populate the sitemap entries of the index.
   *
   * @since 5.5.0
   *
   * @return array[] Array of sitemap entries.
   */
  public function get_sitemap_entries() {
    global $wp_sitemaps;
    $sitemaps = [];

    $sitemaps[] = array(
      'loc' => $this->get_sitemap_url(null, 1),
    );

    return $sitemaps;
  }

  /**
   * Gets the max number of pages available for the object type.
   *
   * @since 5.5.0
   *
   * @param string $object_subtype Optional. Object subtype. Default empty.
   * @return int Total number of pages.
   */
  public function get_max_num_pages( $object_subtype = '' ) {
    return 1;
  }

}