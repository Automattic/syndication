<?php
namespace Automattic\Syndication;

/**
 * Tests for Class Syndication_Notifier.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */
class Test_Syndication_Notifier extends \WP_UnitTestCase {
	/**
	 * Instance of Syndication_Notifier
	 *
	 * @var Syndication_Notifier
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

		$this->instance = new Syndication_Notifier();
	}

	/**
	 * Test that `should_notify` returns true when slack is enabled.
	 *
	 * @since 2.1
	 * @covers Syndication_Notifier::should_notify()
	 */
	public function test_should_notifiy_returns_true_when_slack_event_enabled() {
		// Setup the settings.
		add_action( 'pre_option_push_syndicate_settings', function() {
			return array(
				'notification_methods' => array(
					'slack',
				),
				'notification_slack_types' => array(
					'create',
				),
			);
		} );

		// Reinit settings so it applys the filter above.
		global $settings_manager;
		$settings_manager->init();

		$this->assertTrue( $this->instance->should_notify( 'create' ) );
	}

	/**
	 * Test that `should_notify` returns true when email is enabled.
	 *
	 * @since 2.1
	 * @covers Syndication_Notifier::should_notify()
	 */
	public function test_should_notifiy_returns_true_when_email_event_enabled() {
		// Setup the settings.
		add_action( 'pre_option_push_syndicate_settings', function() {
			return array(
				'notification_methods' => array(
					'email',
				),
				'notification_email_types' => array(
					'update',
				),
			);
		} );

		// Reinit settings so it applys the filter above.
		global $settings_manager;
		$settings_manager->init();

		$this->assertTrue( $this->instance->should_notify( 'update' ) );
	}

	/**
	 * Test that `should_notify` returns false when email is disabled but an
	 * event is enabled.
	 *
	 * @since 2.1
	 * @covers Syndication_Notifier::should_notify()
	 */
	public function test_should_notifiy_returns_false_when_email_disabled_event_enabled() {
		// Setup the settings.
		add_action( 'pre_option_push_syndicate_settings', function() {
			return array(
				'notification_methods' => array(
					'slack',
				),
				'notification_email_types' => array(
					'update',
				),
			);
		} );

		// Reinit settings so it applys the filter above.
		global $settings_manager;
		$settings_manager->init();

		$this->assertFalse( $this->instance->should_notify( 'update' ) );
	}

	/**
	 * Test that `should_notify` returns false when slack is enabled but no
	 * events are enabled.
	 *
	 * @since 2.1
	 * @covers Syndication_Notifier::should_notify()
	 */
	public function test_should_notifiy_returns_false_when_slack_enabled_event_disabled() {
		// Setup the settings.
		add_action( 'pre_option_push_syndicate_settings', function() {
			return array(
				'notification_methods' => array(
					'slack',
				),
				'notification_slack_types' => array(),
			);
		} );

		// Reinit settings so it applys the filter above.
		global $settings_manager;
		$settings_manager->init();

		$this->assertFalse( $this->instance->should_notify( 'update' ) );
	}

	/**
	 * Test that action verb returns the correct verb for an event.
	 *
	 * @since 2.1
	 * @covers Syndication_Notifier::action_verb()
	 */
	public function test_action_verb_returns_verb_for_event() {
		$this->assertEquals( 'created', $this->instance->action_verb( 'create' ) );
		$this->assertEquals( 'updated', $this->instance->action_verb( 'update' ) );
		$this->assertEquals( 'deleted', $this->instance->action_verb( 'delete' ) );
	}

	/**
	 * Test that HTML links get formatted to Slack links correctly.
	 *
	 * @since 2.1
	 * @covers Syndication_Notifier::format_slack_message()
	 */
	public function test_format_slack_message_converts_links_correctly() {
		$this->assertEquals( 'This is a test <http://slack.com|message>.', $this->instance->format_slack_message( 'This is a test <a href="http://slack.com">message</a>.' ) );
	}
}