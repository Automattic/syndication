<?php
/**
 * Tests for Class WP_Push_Syndication_Server.
 *
 * @package Syndication
 */
class Test_WP_Push_Syndication_Server extends WP_UnitTestCase {
	/**
	 * WP_Push_Syndication_Server instance.
	 *
	 * @var WP_Push_Syndication_Server
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

		$this->instance = new WP_Push_Syndication_Server();
	}

	/**
	 * Test that actions are registered.
	 *
	 * @since 2.1
	 * @covers WP_Push_Syndication_Server::__construct()
	 */
	public function test_actions_are_registered() {
		$this->assertEquals( 10, has_action( 'init', array( $this->instance, 'init' ) ) );
	}

	/**
	 * Test that post types and taxonomies are registered.
	 *
	 * @since 2.1
	 * @covers WP_Push_Syndication_Server::init()
	 */
	public function test_post_type_and_taxonomies_are_registered() {
		$this->assertTrue( post_type_exists( 'syn_site' ) );
		$this->assertTrue( taxonomy_exists( 'syn_sitegroup' ) );

		$post_type = get_post_type_object( 'syn_site' );
		$this->assertEquals( 'Syndication Endpoints', $post_type->labels->name );

		$taxonomy = get_taxonomy( 'syn_sitegroup' );
		$this->assertEquals( 'Syndication Endpoint Groups', $taxonomy->labels->name );
	}

	/**
	 * Test that the default options get set on init.
	 *
	 * @since 2.1
	 * @covers WP_Push_Syndication_Server::init()
	 */
	public function test_default_options_get_set() {
		$this->instance->init();
		$this->assertEquals( 'off', $this->instance->push_syndicate_settings['delete_pushed_posts'] );
		$this->assertEquals( 3600, $this->instance->push_syndicate_settings['pull_time_interval'] );
		$this->assertEquals( '', $this->instance->push_syndicate_settings['client_id'] );
	}

	/**
	 * Test that database options that are saved override the default options.
	 *
	 * @since 2.1
	 * @covers WP_Push_Syndication_Server::init()
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
		$this->assertEquals( 'on', $this->instance->push_syndicate_settings['delete_pushed_posts'] );
		$this->assertEquals( 7200, $this->instance->push_syndicate_settings['pull_time_interval'] );
		$this->assertEquals( '123', $this->instance->push_syndicate_settings['client_id'] );
	}
}
