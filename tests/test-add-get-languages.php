<?php
/**
 * Class AddGetLanguagesTest
 *
 * @package ACFMultilingual
 */

use ACFML\ACFMultilingual;

/**
 * Tests add_lannguage
 */
class AddGetLanguagesTest extends WP_UnitTestCase {

  public function setUp() {
    parent::setUp();
  }

  public function test_add_language() {
    $acfml = new ACFMultilingual();  
    $result = $acfml->add_language('en', 'en', 'English');
    $expected = [
      'slug' => 'en',
      'locale' => 'en',
      'name' => 'English',
    ];
    $this->assertSame($result, $expected);
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

  public function test_get_language_info() {
    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en', 'English');
    $result = $acfml->get_language_info('en');
    $expected = array (
      'slug' => 'en',
      'locale' => 'en',
      'name' => 'English',
    );
    $this->assertSame($result, $expected);
  }

  public function test_get_language_info_language_missing() {
    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en', 'English');
    $result = $acfml->get_language_info('de');
    $this->assertSame($result, null);
  }

  public function test_get_default_language() {
    $acfml = new ACFMultilingual();
    $acfml->add_language('de', 'de_DE', 'Deutsch');
    $acfml->add_language('en', 'en', 'English');
    $this->assertSame($acfml->get_default_language(), 'de');
  }

  public function test_detect_language_doing_ajax() {
    $acfml = new ACFMultilingual();
    $acfml->add_language('en', 'en', 'English');
    $acfml->add_language('de', 'de_DE', 'Deutsch');
    $_GET['lang'] = 'de';
    add_filter('wp_doing_ajax', '__return_true');
    $acfml->detect_language();
    add_filter('wp_doing_ajax', '__return_false');
    $this->assertSame($acfml->get_current_language(), 'de');
  }

  public function test_detect_language_is_admin() {
    $acfml = new ACFMultilingual();
    $acfml->add_language('de', 'de_DE', 'Deutsch');
    $acfml->add_language('en', 'en', 'English');
    // makes is_admin() return true
    set_current_screen('edit-post');
    $acfml->detect_language();
    // resets is_admin() to false
    unset( $GLOBALS['current_screen'] );
    
    $this->assertSame($acfml->get_current_language(), 'en');
  }

  public function test_get_language_in_url() {
    $acfml = new ACFMultilingual();
    $acfml->add_language('de', 'de_DE', 'Deutsch');
    $acfml->add_language('en', 'en', 'English');
    $url = home_url('/de/my-path/');
    $result = $acfml->get_language_in_url($url);
    $this->assertSame($result, 'de');
  }
  
}
