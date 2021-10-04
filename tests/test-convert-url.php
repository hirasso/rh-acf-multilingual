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
    $acfml->post_types_controller->add_post_type('page');
    $this->set_permalink_structure('/%postname%/');
    $this->acfml = $acfml;
  }

  public function test_simple_convert_url() {

    $expected = 'http://example.org/de/test-path/';
    $result  = $this->acfml->simple_convert_url('http://example.org/test-path/', 'de');

    $this->assertSame($result, $expected);

  }

  public function test_get_translated_post_permalink() {
    
    // Create a test post. The generated post_name should be 'test-resolve-url'
    $post = self::factory()->post->create_and_get([
      'post_title' => 'Test: Post Permalink'
    ]);
    // Manually insert the required fields for 'de'
    update_field('acfml_slug_de', 'test-eintrags-link', $post->ID);

    $this->acfml->switch_to_language('de');
    $result = get_permalink($post->ID);
    $expected = home_url("/de/test-eintrags-link/");
    $this->acfml->reset_language();

    $this->assertSame($result, $expected);
    
  }

  public function test_get_translated_page_permalink() {
    $granny = self::factory()->post->create_and_get([
      'post_type' => 'page',
      'post_title' => 'Granny',
    ]);
    update_field('acfml_slug_de', 'grossmutter', $granny->ID);
    $mum = self::factory()->post->create_and_get([
      'post_type' => 'page',
      'post_title' => 'Mum',
      'post_parent' => $granny->ID
    ]);
    update_field('acfml_slug_de', 'mutter', $mum->ID);
    $child = self::factory()->post->create_and_get([
      'post_type' => 'page',
      'post_title' => 'Child',
      'post_parent' => $mum->ID
    ]);
    update_field('acfml_slug_de', 'kind', $child->ID);
    
    $this->acfml->switch_to_language('de');
    $result = get_permalink($child->ID);
    $expected = home_url("/de/grossmutter/mutter/kind/");
    $this->acfml->reset_language();

    $this->assertSame($result, $expected);
    
  }

}
