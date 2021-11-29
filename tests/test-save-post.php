<?php
/**
 * Class SavePostTest
 *
 * @package ACFMultilingual
 */

use ACFML\ACFMultilingual;
use ACFML\Config;

/**
 * Tests add_lannguage
 */
class SavePostTest extends WP_UnitTestCase {

  /**
  * ACFML instance
  *
  * @var ACFMultilingual
  */
  private $acfml;

  public function setUp() {
    parent::setUp();
    $this->setup_acfml_instance();
  }

  private function setup_acfml_instance() {
    $config = $this->createMock(Config::class);
    $config->languages = (object) [
      'en' => (object) [
        'locale' => 'en',
        'name' => 'English'
      ],
      'de' => (object) [
        'locale' => 'de',
        'name' => 'Deutsch'
      ]
    ];
    $config->post_types = (object) [
      'post' => true,
      'page' => true,
    ];
    $config->method('is_loaded')->willReturn(true);
    $this->acfml = new ACFMultilingual($config);
    $this->acfml->initialize()->fully_initialize()->add_multilingual_object_types();
    $this->set_permalink_structure('/%postname%/');
  }

	/**
	 * A single example test.
	 */
	public function test_save_post() {
    
    $post_id = self::factory()->post->create([
      'post_title' => 'Test Post'
    ]);
    $this->assertEquals(
      get_post_meta($post_id, 'acfml_post_title_en', true), 
      'Test Post',
    );
    $this->assertEquals(
      get_post_meta($post_id, 'acfml_slug_en', true),
      'test-post',
    );
    $this->assertEquals(
      get_post_meta($post_id, 'acfml_lang_active_en', true),
      '1'
    );
    $this->assertEquals(
      get_post_meta($post_id, 'acfml_post_title_de', true),
      'Test Post'
    );
    $this->assertEquals(
      get_post_meta($post_id, 'acfml_slug_de', true),
      'test-post'
    );
		
	}
}
