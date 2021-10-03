<?php
/**
 * Class SavePostTest
 *
 * @package ACFMultilingual
 */

use ACFML\ACFMultilingual;

/**
 * Tests add_lannguage
 */
class HomeUrlTest extends WP_UnitTestCase {

  public function setUp() {
    parent::setUp();
    
  }

  public function test_home_url_current_language() {

    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en', 'English');

    $expected = 'http://example.org/test-path/';
    $result  = $acfml->home_url('/test-path/');

    $this->assertSame($result, $expected);

  }

	public function test_home_url_default_language() {
    
    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en', 'English');

    $expected = 'http://example.org/test-path/';
    $result  = $acfml->home_url('/test-path/', 'en');

    $this->assertSame($result, $expected);
		
	}

  public function test_home_url_non_default_language() {

    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en', 'English');
    $acfml->add_language('de', 'de_DE', 'Deutsch');

    $expected = 'http://example.org/de/test-path/';
    $result  = $acfml->home_url('/test-path/', 'de');

    $this->assertSame($result, $expected);

  }
}
