<?php 

/**
 * This file is not in use. 
 * It is only here for ideas and probable future improvements.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Idea: Replace the query of wp get_page_by_path so that it looks 
 * the current language's slug instead of post_name
 */
$sql = "SELECT ID, $wpdb->postmeta.meta_value AS post_name, post_parent, post_type FROM $wpdb->posts
LEFT JOIN $wpdb->postmeta ON $wpdb->postmeta.post_id = $wpdb->posts.ID
WHERE 
$wpdb->posts.post_type IN ($post_type_in_string)
AND (
  $wpdb->postmeta.meta_key = 'acfml_slug_$language'
  AND
  $wpdb->postmeta.meta_value IN ($in_string)
)";