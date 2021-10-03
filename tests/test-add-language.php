<?php
/**
 * Class AddLanguageTest
 *
 * @package ACFMultilingual
 */

use ACFML\ACFMultilingual;

/**
 * Tests add_lannguage
 */
class GetLanguagesTest extends WP_UnitTestCase {

  public function setUp() {
    parent::setUp();
  }

	public function test_get_languages_full() {

    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en', 'English');
    $acfml->add_language('de', 'de_DE', 'Deutsch');

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
    
    $this->assertSame($acfml->get_languages(), $expected);
		
	}

  
  public function test_get_languages_slug() {
    
    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en', 'English');
    $acfml->add_language('de', 'de_DE', 'Deutsch');

    $expected = ['en', 'de'];

    $this->assertSame($acfml->get_languages('slug'), $expected);

  }
}
