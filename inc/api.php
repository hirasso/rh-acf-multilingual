<?php 

/**
 * This function is documented in acfml.php > get_language_switcher
 */
function acfml_get_language_switcher(?array $args = []) {
  return acfml()->get_language_switcher($args);
}

/**
 * This function is documented in acfml.php > convert_url
 */
function acfml_convert_url(?string $url = null, ?string $lang = null): string {
  return acfml()->convert_url($url, $lang);
}

/**
 * This function is documented in acfml.php > get_converted_urls
 */
function acfml_get_converted_urls(?string $url = null): array {
  return acfml()->get_converted_urls($url);
}

/**
 * This function is documented in acfml.php > add_language
 */
function acfml_add_language(string $slug, ?string $locale = null, ?string $name = null): array {
  return acfml()->add_language($slug, $locale, $name);
}

/**
 * This function is documented in acfml.php > get_curret_language
 */
function acfml_get_current_language() {
  return acfml()->get_current_language();
}

/**
 * This function is documented in acfml.php > get_languages
 */
function acfml_get_languages(?string $format = null) {
  return acfml()->get_languages($format);
}

/**
 * This function is documented in class.post-types-controller.php > add_post_type
 */
function acfml_add_post_type($post_type, ?array $args = []) {
  if( did_action('init') || doing_action('init') ) {
    acfml()->post_types_controller->add_post_type($post_type, $args);
  } else {
    add_action('init', function () use ($post_type, $args) {
      acfml()->post_types_controller->add_post_type($post_type, $args);
    }, 11); // after default 'init' hook
  }
}

/**
* This function is documented in class.taxonomies-controller.php > add_taxonomy
*/
function acfml_add_taxonomy( $taxonomy ) {
  if( did_action('init') || doing_action('init') ) {
    acfml()->taxonomies_controller->add_taxonomy($taxonomy);
  } else {
    add_action('init', function () use ($taxonomy) {
      acfml()->taxonomies_controller->add_taxonomy($taxonomy);
    }, 11); // after default 'init' hook
  }
}