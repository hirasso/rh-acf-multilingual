<?php
/**
 * Class AddLanguageTest
 *
 * @package ACFMultilingual
 */

/**
 * Tests add_lannguage
 */
class AddLanguageTest extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_add_language() {
    
    acfml_add_language('en', 'en', 'English');
    acfml_add_language('de', 'de_DE', 'Deutsch');

    $expected = array (
      'en' => 
      array (
        'slug' => 'en',
        'locale' => 'en',
        'name' => 'English',
      ),
      'de' => 
      array (
        'slug' => 'de',
        'locale' => 'de_DE',
        'name' => 'Deutsch',
      ),
    );
    
    $this->assertSame(acfml_get_languages(), $expected);
		
	}
}
