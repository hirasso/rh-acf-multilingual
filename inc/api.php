<?php 

/**
 * This function is documented in acfml.php > get_language_switcher
 */
function acfml_get_language_switcher(?array $args = []) {
  return acfml()->get_language_switcher($args);
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
 *
 */
function acfml_get_languages(?string $format = null) {
  return acfml()->get_languages($format);
}

/**
 * This function is documented in class.post-types-controller.php > add_post_type
 */
function acfml_add_post_type($post_type, ?array $args = []) {
  if( did_action('init') ) {
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
  if( did_action('init') ) {
    acfml()->taxonomies_controller->add_taxonomy($taxonomy);
  } else {
    add_action('init', function () use ($taxonomy) {
      acfml()->taxonomies_controller->add_taxonomy($taxonomy);
    }, 11); // after default 'init' hook
  }
}

/**
 * Get a permalink in each language for a post
 *
 * @param int|\WP_Post $post
 * @return array|null
 */
function acfml_get_permalinks($post): ?array {
  $permalink = get_permalink($post);
  $switcher = acfml_get_language_switcher([
    'url' => $permalink,
    'format' => 'slug:url'
  ]);
  return $switcher;
}