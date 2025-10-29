<?php

/**
 * Class ConvertAndResolveUrlsTest
 *
 * @package ACFMultilingual
 */

use ACFML\ACFMultilingual;
use ACFML\Config;

class ConvertAndResolveUrlsTest extends WP_UnitTestCase
{
    /**
    * ACFML instance
    *
    * @var ACFMultilingual
    */
    private $acfml;

    public function setUp()
    {
        parent::setUp();
        $this->setup_acfml_instance();
    }

    private function setup_acfml_instance()
    {
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

    public function test_simple_convert_url()
    {

        $expected = 'http://example.org/de/test-path/';
        $result  = $this->acfml->simple_convert_url('http://example.org/test-path/', 'de');

        $this->assertSame($result, $expected);

    }

    public function test_simple_convert_url_starts_with_default_language_slug()
    {

        $expected = 'http://example.org/english-path/';
        $result  = $this->acfml->simple_convert_url('http://example.org/english-path/', 'en');

        $this->assertSame($result, $expected);

    }

    public function test_get_multilingual_post_types()
    {
        $expected = ['post', 'page'];
        $result = $this->acfml->post_types_controller->get_multilingual_post_types();
        $this->assertSame($expected, $result);
    }

    public function test_get_translated_post_permalink()
    {

        // Create a test post. The generated post_name should be 'test-resolve-url'
        $post = self::factory()->post->create_and_get([
            'post_title' => 'Test: Post Permalink'
        ]);
        // Manually insert the required fields for 'de'
        \update_field('acfml_lang_active_de', 1, $post->ID);
        \update_field('acfml_slug_de', 'test-eintrags-link', $post->ID);

        $this->acfml->switch_to_language('de');
        $expected = \home_url("/de/test-eintrags-link/");
        $result = \get_permalink($post->ID);
        $this->acfml->reset_language();

        $this->assertSame($expected, $result);

    }

    public function test_get_translated_page_permalink()
    {

        $granny = self::factory()->post->create_and_get([
            'post_type' => 'page',
            'post_title' => 'Granny',
        ]);
        \update_field('acfml_slug_de', 'grossmutter', $granny->ID);
        $mum = self::factory()->post->create_and_get([
            'post_type' => 'page',
            'post_title' => 'Mum',
            'post_parent' => $granny->ID
        ]);
        \update_field('acfml_slug_de', 'mutter', $mum->ID);
        $child = self::factory()->post->create_and_get([
            'post_type' => 'page',
            'post_title' => 'Child',
            'post_parent' => $mum->ID
        ]);
        \update_field('acfml_slug_de', 'kind', $child->ID);

        $this->acfml->switch_to_language('de');
        $result = \get_permalink($child->ID);
        $expected = \home_url("/de/grossmutter/mutter/kind/");
        $this->acfml->reset_language();

        $this->assertSame($expected, $result);

    }


}
