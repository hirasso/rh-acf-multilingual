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
 * This function is documented in class.acfml-post-types.php > add_post_type
 */
function acfml_add_post_type($post_type, ?array $args = []) {
  if( did_action('init') ) {
    acfml()->acfml_post_types->add_post_type($post_type, $args);
  } else {
    add_action('init', function () use ($post_type, $args) {
      acfml()->acfml_post_types->add_post_type($post_type, $args);
    }, 11); // after default 'init' hook
  }
}