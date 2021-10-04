<?php
/**
 * Class ConvertAndResolveUrlsTest
 *
 * @package ACFMultilingual
 */

use ACFML\ACFMultilingual;

class ConvertAndResolveUrlsTest extends WP_UnitTestCase {

  /**
  * FieldsController instance
  *
  * @var ACFMultilingual
  */
  private $acfml;

  public function setUp() {
    parent::setUp();

    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en_US', 'English');
    $acfml->add_language('de', 'de_DE', 'Deutsch');

    $acfml->initialize();
    $acfml->maybe_fully_initialize();

    $acfml->post_types_controller->add_post_type('post');
    $this->set_permalink_structure('/%postname%/');
    $this->acfml = $acfml;
  }

  public function test_simple_convert_url() {

    $expected = 'http://example.org/de/test-path/';
    $result  = $this->acfml->simple_convert_url('http://example.org/test-path/', 'de');

    $this->assertSame($result, $expected);

  }

  public function test_resolve_url_post_type_post() {

    $post = self::factory()->post->create_and_get();
    update_field('acfml_post_title_de', 'Eintragstitel', $post->ID);
    $post_name_de = str_replace('post-title', 'eintragstitel', $post->post_name);
    update_field('acfml_slug_de', $post_name_de, $post->ID);

    $resolved_post_ids = [
      'en' => $this->acfml->resolve_url(home_url("/$post->post_name/"))->get_queried_object_id(),
      'de' => $this->acfml->resolve_url(home_url("/de/$post_name_de/"))->get_queried_object_id(),
    ];

    $expected_post_ids = [
      'en' => $post->ID,
      'de' => $post->ID 
    ];
    
    $this->assertSame($resolved_post_ids, $expected_post_ids);
  }

}
