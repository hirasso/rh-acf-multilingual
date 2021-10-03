<?php
/**
 * Class SavePostTest
 *
 * @package ACF_Multilingual
 */

require_once(ACFML_PATH . 'inc/class.post-types-controller.php');

use ACFML\ACF_Multilingual;
use ACFML\Post_Types_Controller;

/**
 * Tests add_lannguage
 */
class SavePostTest extends WP_UnitTestCase {

  public function setUp() {
    parent::setUp();
    
    $acfml = new ACF_Multilingual();
    $acfml->add_language('en', 'en', 'English');
    
    $post_types_controller = new Post_Types_Controller($acfml);
    $post_types_controller->add_post_type('post');
    
  }

	/**
	 * A single example test.
	 */
	public function test_save_post() {
    
    $post_id = self::factory()->post->create([
      'post_title' => 'Test Post'
    ]);
    
    $this->assertNotEmpty(get_field('acfml_post_title_en', $post_id));
    $this->assertNotEmpty(get_field('acfml_slug_en', $post_id));
    $this->assertNotEmpty(get_field('acfml_lang_active_en', $post_id));
    $this->assertEmpty(get_field('acfml_post_title_de', $post_id));
		
	}
}
