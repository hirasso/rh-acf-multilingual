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
class HomeUrlTest extends WP_UnitTestCase
{
    public function setUp()
    {
        parent::setUp();
        $config = $this->createMock(Config::class);
        $config->method('is_loaded')->willReturn(true);
        $this->config = $config;

    }

    public function test_home_url_current_language()
    {
        $this->config->languages = (object) [
            'en' => (object) [
                'locale' => 'en',
                'name' => 'English'
            ]
        ];
        $acfml = new ACFMultilingual($this->config);
        $acfml->initialize();

        $expected = 'http://example.org/test-path/';
        $result  = $acfml->home_url('/test-path/');

        $this->assertSame($expected, $result);

    }

    public function test_home_url_default_language()
    {
        $this->config->languages = (object) [
            'en' => (object) [
                'locale' => 'en',
                'name' => 'English'
            ]
        ];
        $acfml = new ACFMultilingual($this->config);
        $acfml->initialize();

        $expected = 'http://example.org/test-path/';
        $result  = $acfml->home_url('/test-path/', 'en');

        $this->assertSame($expected, $result);

    }

    public function test_home_url_non_default_language()
    {
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
        $acfml->initialize();

        $expected = 'http://example.org/de/test-path/';
        $result  = $acfml->home_url('/test-path/', 'de');

        $this->assertSame($expected, $result);

    }
}
