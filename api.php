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
 * This function is documented in inc/class.post-types-controller.php > get_post_urls
 *
 * @param WP_Post $post
 * @return array
 */
function acfml_get_post_permalinks(WP_Post $post): array {
    return acfml()->post_types_controller->get_post_urls($post);
}

/**
 * This function is documented in acfml.php > home_url
 *
 */
function acfml_home_url(string $path = '', ?string $lang = null): string {
  return acfml()->home_url($path, $lang);
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

