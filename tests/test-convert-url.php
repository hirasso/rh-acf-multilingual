<?php
/**
 * Class SavePostTest
 *
 * @package ACFMultilingual
 */

use ACFML\ACFMultilingual;
use ACFML\PostTypesController;

/**
 * Tests add_lannguage
 */
class ConvertUrlTest extends WP_UnitTestCase {

  public function setUp() {
    parent::setUp();
    
  }

  public function test_simple_convert_url() {

    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en_US', 'English');
    $acfml->add_language('de', 'de_DE', 'Deutsch');

    $expected = 'http://example.org/de/test-path/';
    $result  = $acfml->simple_convert_url('http://example.org/test-path/', 'de');

    $this->assertSame($result, $expected);

  }

  public function test_convert_url_post_type_post() {
    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en_US', 'English');
    $acfml->add_language('de', 'de_DE', 'Deutsch');
    $acfml->post_types_controller = new PostTypesController($acfml);
    $acfml->post_types_controller->add_post_type('post');
    $this->set_permalink_structure('/%postname%/');

    $post_id = self::factory()->post->create();
    update_field('acfml_post_title_de', 'Testeintrag', $post_id);
    update_field('acfml_slug_de', 'test-eintrag', $post_id);
    $expected = home_url('/de/test-eintrag/');
    $result = $acfml->convert_url(get_permalink($post_id), 'de');
    $this->assertSame($result, $expected);
  }

}
