<?php
namespace Automattic\Syndication;

/**
 * Tests for Class Syndication_Settings.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */
class Test_Syndication_Settings extends \WP_UnitTestCase {
	/**
	 * Instance of Syndication_Settings
	 *
	 * @var Syndication_Settings
	 */
	private $instance;

	/**
	 * Setup.
	 *
	 * @since 2.1
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->instance = new Syndication_Settings();
	}

	/**
	 * Test that `get_setting` returns a setting if it exists.
	 *
	 * @since 2.1
	 * @covers Syndication_Settings::get_setting()
	 */
	public function test_get_setting_returns_a_setting_if_exists() {
		// Add a setting.
		add_action( 'pre_option_push_syndicate_settings', function() {
			return array(
				'delete_pushed_posts' => 'on',
			);
		} );

		$this->instance->init();
		$this->assertEquals( 'on', $this->instance->get_setting( 'delete_pushed_posts' ) );
	}

	/**
	 * Test that `get_setting` returns a false if it doesnt exists.
	 *
	 * @since 2.1
	 * @covers Syndication_Settings::get_setting()
	 */
	public function test_get_setting_returns_false_if_doesnt_exists() {
		$this->assertFalse( $this->instance->get_setting( 'non_existant_setting' ) );
	}

	/**
	 * Test that `get_setting` returns a custom value if it doesnt exists and a
	 * custom value is pased in.
	 *
	 * @since 2.1
	 * @covers Syndication_Settings::get_setting()
	 */
	public function test_get_setting_returns_custom_value_if_doesnt_exists() {
		$this->assertEquals( 'empty', $this->instance->get_setting( 'non_existant_setting', 'empty' ) );
	}

	/**
	 * Test that the default options get set on init.
	 *
	 * @since 2.1
	 * @covers Syndication_Settings::init()
	 */
	public function test_default_options_get_set() {
		$this->instance->init();
		$this->assertEquals( 'off', $this->instance->get_setting( 'delete_pushed_posts' ) );
		$this->assertEquals( 3600, $this->instance->get_setting( 'pull_time_interval' ) );
		$this->assertEquals( '', $this->instance->get_setting( 'client_id' ) );
	}

	/**
	 * Test that database options that are saved override the default options.
	 *
	 * @since 2.1
	 * @covers Syndication_Settings::init()
	 */
	public function test_database_options_override_default_options() {
		// Setup option override data.
		add_action( 'pre_option_push_syndicate_settings', function() {
			return array(
				'delete_pushed_posts' => 'on',
				'pull_time_interval'  => 7200,
				'client_id'           => '123',
			);
		} );

		$this->instance->init();
		$this->assertEquals( 'on', $this->instance->get_setting( 'delete_pushed_posts' ) );
		$this->assertEquals( 7200, $this->instance->get_setting( 'pull_time_interval' ) );
		$this->assertEquals( '123', $this->instance->get_setting( 'client_id' ) );
	}
}