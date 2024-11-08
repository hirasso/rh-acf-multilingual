<?php
/**
 * Class AddGetLanguagesTest
 *
 * @package ACFMultilingual
 */

use ACFML\ACFMultilingual;
use ACFML\Config;

/**
 * Tests add_lannguage
 */
class AddGetLanguagesTest extends WP_UnitTestCase {

  public function setUp() {
    parent::setUp();
    $config = $this->createMock(Config::class);
    $config->method('is_loaded')->willReturn(true);
    $this->config = $config;
  }

  /**
   * makes is_admin() return true
   *
   * @return boolean
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  private function is_admin_true() {
    set_current_screen('edit-post');
  }

  /**
   * resets is_admin() to false
   *
   * @return boolean
   * @author Rasso Hilber <mail@rassohilber.com>
   */
  private function is_admin_false() {
    unset( $GLOBALS['current_screen'] );
  }
  
  public function test_add_text_direction() {
    $this->config->languages = (object) [
      'en' => (object) [
        'locale' => 'en_US',
        'name' => 'English'
      ],
      'ar' => (object) [
        'locale' => 'ar',
        'name' => 'Arabic'
      ]
    ];
    $acfml = new ACFMultilingual($this->config);
    $acfml->initialize();
    $this->assertSame('rtl', $acfml->get_text_direction('ar'));
  }

  public function test_add_language() {
    $this->config->languages = (object) [
      'en' => (object) [
        'locale' => 'en',
        'name' => 'English'
      ]
    ];
    $acfml = new ACFMultilingual($this->config);
    $acfml->initialize()->fully_initialize();
    $result = $acfml->get_language_info('en');
    $expected = [
      'slug' => 'en',
      'locale' => 'en',
      'name' => 'English',
      'dir' => 'ltr'
    ];
    $this->assertSame($result, $expected);
  }

	public function test_get_languages_full() {
    $this->config->languages = (object) [
      'en' => (object) [
        'locale' => 'en',
        'name' => 'English'
      ],
      'de' => (object) [
        'locale' => 'de',
        'name' => 'Deutsch'
      ],
    ];
    $acfml = new ACFMultilingual($this->config);
    $acfml->initialize()->fully_initialize();
    $result = $acfml->get_languages();
    
    $expected = array (
      'en' => 
      array (
        'slug' => 'en',
        'locale' => 'en',
        'name' => 'English',
        'dir' => 'ltr',
      ),
      'de' => 
      array (
        'slug' => 'de',
        'locale' => 'de',
        'name' => 'Deutsch',
        'dir' => 'ltr',
      ),
    );
    $this->assertSame($expected, $result);
	}

  
  public function test_get_languages_slug() {
    $this->config->languages = (object) [
      'en' => (object) [
        'locale' => 'en',
        'name' => 'English'
      ],
      'de' => (object) [
        'locale' => 'de_DE',
        'name' => 'Deutsch'
      ],
    ];
    $acfml = new ACFMultilingual($this->config);
    $acfml->initialize();
    $result = $acfml->get_languages('slug');
    $expected = ['en', 'de'];
    $this->assertSame($expected, $result);
  }

  public function test_get_language_info_language_missing() {
    $this->config->languages = (object) [
      'en' => (object) [
        'locale' => 'en',
        'name' => 'English'
      ]
    ];
    $acfml = new ACFMultilingual($this->config);
    $acfml->initialize();
    $result = $acfml->get_language_info('de');
    
    $this->assertSame(null, $result);
  }

  public function test_get_default_language() {
    $this->config->languages = (object) [
      'de' => (object) [
        'locale' => 'de_DE',
        'name' => 'Deutsch'
      ],
      'en' => (object) [
        'locale' => 'en',
        'name' => 'English'
      ]
    ];
    $acfml = new ACFMultilingual($this->config);
    $acfml->initialize();
    $this->assertSame($acfml->get_default_language(), 'de');
  }

  public function test_detect_language_doing_ajax() {
    $this->config->languages = (object) [
      'en' => (object) [
        'locale' => 'en',
        'name' => 'English'
      ],
      'de' => (object) [
        'locale' => 'de_DE',
        'name' => 'Deutsch'
      ]
    ];
    
    add_filter('wp_doing_ajax', '__return_true');
    $acfml = new ACFMultilingual($this->config);
    $_GET['lang'] = 'de';
    $acfml->initialize();
    add_filter('wp_doing_ajax', '__return_false');

    $this->assertSame($acfml->get_current_language(), 'de');
    
  }

  public function test_detect_language_is_admin() {
    $this->config->languages = (object) [
      'de' => (object) [
        'locale' => 'de_DE',
        'name' => 'Deutsch'
      ],
      'en' => (object) [
        'locale' => 'en',
        'name' => 'English'
      ]
    ];
    $this->is_admin_true();
    $acfml = new ACFMultilingual($this->config);
    $acfml->initialize();
    $this->is_admin_false();
    
    $this->assertSame($acfml->get_current_language(), 'en');
  }

  public function test_get_language_in_url() {
    $this->config->languages = (object) [
      'de' => (object) [
        'locale' => 'de_DE',
        'name' => 'Deutsch'
      ],
      'en' => (object) [
        'locale' => 'en',
        'name' => 'English'
      ]
    ];
    $acfml = new ACFMultilingual($this->config);
    $acfml->initialize();
    
    $url = home_url('/de/my-path/');
    $result = $acfml->get_language_in_url($url);
    $this->assertSame($result, 'de');
  }
  
}
