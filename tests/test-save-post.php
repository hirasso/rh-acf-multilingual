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
class SavePostTest extends WP_UnitTestCase {

  public function setUp() {
    parent::setUp();
    
    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en', 'English');
    
    $post_types_controller = new PostTypesController($acfml);
    $post_types_controller->add_post_type('post');
    
  }

	/**
	 * A single example test.
	 */
	public function test_save_post() {
    
    $post_id = self::factory()->post->create([
      'post_title' => 'Test Post'
    ]);
    
    $this->assertNotEmpty(get_post_meta($post_id, 'acfml_post_title_en', true));
    $this->assertNotEmpty(get_post_meta($post_id, 'acfml_slug_en', true));
    $this->assertNotEmpty(get_post_meta($post_id, 'acfml_lang_active_en', true));
    $this->assertEmpty(get_post_meta($post_id, 'acfml_post_title_de', true));
		
	}
}
