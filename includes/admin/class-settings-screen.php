<?php
/**
 * Settings Screen
 *
 * Responsible for plugin-level settings screens.
 *
 * @since 2.1
 * @package Automattic\Syndication\Admin
 */

namespace Automattic\Syndication\Admin;

/**
 * Class Settings_Screen
 *
 * @since 2.1
 * @package Automattic\Syndication\Admin
 */
class Settings_Screen {
	/**
	 * Settings_Screen constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'register_syndicate_settings' ) );
	}

	public function admin_init() {
		register_setting( 'push_syndicate_settings', 'push_syndicate_settings', array( $this, 'push_syndicate_settings_validate' ) );
		register_setting( 'push_syndicate_settings', 'push_syndication_max_pull_attempts', array( $this, 'validate_max_pull_attempts' ) );
	}

	/**
	 * Push Syndicate Settings Validate
	 *
	 * Validate the push syndication settings.
	 *
	 * @param $raw_settings array Settings to validate.
	 * @return array              Validated settings.
	 */
	public function push_syndicate_settings_validate( $raw_settings ) {
		if ( isset( $_POST['push_syndicate_pull_now'] ) && 'Pull Now & Save Changes' === $_POST['push_syndicate_pull_now'] ) {
			\Automattic\Syndication\Syndication_Runner::pull_now_job();
		}

		$settings                                       = array();
		$settings['client_id']                          = ! empty( $raw_settings['client_id'] ) ? sanitize_text_field( $raw_settings['client_id'] ) : '';
		$settings['client_secret']                      = ! empty( $raw_settings['client_secret'] ) ? sanitize_text_field( $raw_settings['client_secret'] ) : '';
		$settings['selected_post_types']                = ! empty( $raw_settings['selected_post_types'] ) ? $this->sanitize_array( $raw_settings['selected_post_types'] ) : array() ;
		$settings['notification_methods']               = ! empty( $raw_settings['notification_methods'] ) ? $this->sanitize_array( $raw_settings['notification_methods'] ) : array();
		$settings['notification_email_address']         = ! empty( $raw_settings['notification_email_address'] ) ? sanitize_email( $raw_settings['notification_email_address'] ) : '';
		$settings['notification_email_types']           = ! empty( $raw_settings['notification_email_types'] ) ? $this->sanitize_array( $raw_settings['notification_email_types'] ) : array();
		$settings['notification_slack_webhook']         = ! empty( $raw_settings['notification_slack_webhook'] ) ? esc_url_raw( $raw_settings['notification_slack_webhook'] ) : '';
		$settings['notification_slack_types']           = ! empty( $raw_settings['notification_slack_types'] ) ? $this->sanitize_array( $raw_settings['notification_slack_types'] ) : array();
		$settings['delete_pushed_posts']                = ! empty( $raw_settings['delete_pushed_posts'] ) ? sanitize_text_field( $raw_settings['delete_pushed_posts'] ) : 'off' ;
		$settings['selected_pull_sitegroups']           = ! empty( $raw_settings['selected_pull_sitegroups'] ) ? $this->sanitize_array( $raw_settings['selected_pull_sitegroups'] ) : array() ;
		$settings['pull_time_interval']                 = ! empty( $raw_settings['pull_time_interval'] ) ? intval( max( $raw_settings['pull_time_interval'] ), 300 ) : '3600';
		$settings['update_pulled_posts']                = ! empty( $raw_settings['update_pulled_posts'] ) ? sanitize_text_field( $raw_settings['update_pulled_posts'] ) : 'off' ;
		$settings['push_syndication_max_pull_attempts'] = ! empty( $raw_settings['push_syndication_max_pull_attempts'] ) ? intval( $raw_settings['push_syndication_max_pull_attempts'] ) : 0 ;

		\Automattic\Syndication\Syndication_Runner::refresh_pull_jobs();
		return $settings;

	}

	/**
	 * Sanitize Array
	 *
	 * Takes an array of raw data and runs through each element, sanitizing the
	 * raw data on the way.
	 *
	 * @since 2.1
	 * @param mixed $data The data to be sanitized.
	 * @return array|string The sanitized datsa.
	 */
	public function sanitize_array( $data ) {
		if ( ! is_array( $data ) ) {
			return sanitize_text_field( $data );
		} else {
			foreach ( $data as $key => $item ) {
				if ( is_array( $item ) ) {
					$data[ $key ] = $this->sanitize_array( $item );
				} else {
					$data[ $key ] = sanitize_text_field( $item );
				}
				return $data;
			}
		}
	}

	public function register_syndicate_settings() {
		add_submenu_page(
			'edit.php?post_type=syn_site',
			esc_html__( 'Syndication Settings', 'push-syndication' ),
			esc_html__( 'Settings', 'push-syndication' ),
			/* This filter is documented in includes/admin/class-settings-screen.php */
			apply_filters( 'syn_syndicate_cap', 'manage_options' ),
			'push-syndicate-settings',
			array( $this, 'display_syndicate_settings' )
		);
	}

	/**
	 * Display Syndicate Settings
	 *
	 * Registers the setting sections and fields and outputs the markup for the
	 * settings page.
	 */
	public function display_syndicate_settings() {
		// @todo all validation and sanitization should be moved to a separate object.
		add_settings_section( 'push_syndicate_pull_sitegroups', esc_html__( 'Syndication Endpoint Groups' , 'push-syndication' ), array( $this, 'display_pull_sitegroups_description' ), 'push_syndicate_pull_sitegroups' );
		add_settings_field( 'pull_sitegroups_selection', esc_html__( 'Select Syndication Endpoint Groups', 'push-syndication' ), array( $this, 'display_pull_sitegroups_selection' ), 'push_syndicate_pull_sitegroups', 'push_syndicate_pull_sitegroups' );

		add_settings_section( 'push_syndicate_pull_options', esc_html__( 'Pull Options' , 'push-syndication' ), array( $this, 'display_pull_options_description' ), 'push_syndicate_pull_options' );
		add_settings_field( 'pull_time_interval', esc_html__( 'Specify time interval in seconds', 'push-syndication' ), array( $this, 'display_time_interval_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );
		add_settings_field( 'max_pull_attempts', esc_html__( 'Maximum pull attempts', 'push-syndication' ), array( $this, 'display_max_pull_attempts' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );
		add_settings_field( 'update_pulled_posts', esc_html__( 'Update pulled posts', 'push-syndication' ), array( $this, 'display_update_pulled_posts_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );

		add_settings_section( 'push_syndicate_post_types', esc_html__( 'Post Types' , 'push-syndication' ), array( $this, 'display_push_post_types_description' ), 'push_syndicate_post_types' );
		add_settings_field( 'post_type_selection', esc_html__( 'Select post types', 'push-syndication' ), array( $this, 'display_post_types_selection' ), 'push_syndicate_post_types', 'push_syndicate_post_types' );

		// Delete Pushed Posts section.
		add_settings_section(
			'delete_pushed_posts',
			esc_html__( 'Delete Pushed Posts', 'push-syndication' ),
			array( $this, 'display_delete_pushed_posts_description' ),
			'delete_pushed_posts'
		);

		add_settings_field(
			'delete_post_check',
			esc_html__( 'Delete pushed posts', 'push-syndication' ),
			array( $this, 'display_delete_pushed_posts_selection' ),
			'delete_pushed_posts',
			'delete_pushed_posts'
		);

		// Notifications section.
		add_settings_section(
			'notifications',
			esc_html__( 'Notifications', 'push-syndication' ),
			array( $this, 'display_notifications_description' ),
			'notifications'
		);

		add_settings_field(
			'notification_email_enabled',
			esc_html__( 'Email notifications', 'push-syndication' ),
			array( $this, 'display_notification_email_enabled' ),
			'notifications',
			'notifications'
		);

		add_settings_field(
			'notification_email_address',
			false,
			array( $this, 'display_notification_email_address' ),
			'notifications',
			'notifications'
		);

		add_settings_field(
			'notification_email_types',
			false,
			array( $this, 'display_notification_email_types' ),
			'notifications',
			'notifications'
		);

		add_settings_field(
			'notification_slack_enabled',
			esc_html__( 'Slack notifications', 'push-syndication' ),
			array( $this, 'display_notification_slack_enabled' ),
			'notifications',
			'notifications'
		);

		add_settings_field(
			'notification_slack_webhook',
			false,
			array( $this, 'display_notification_slack_webhook' ),
			'notifications',
			'notifications'
		);

		add_settings_field(
			'notification_slack_types',
			false,
			array( $this, 'display_notification_slack_types' ),
			'notifications',
			'notifications'
		);
		?>
		<div class="wrap" xmlns="http://www.w3.org/1999/html">
			<?php screen_icon(); // @todo custom screen icon ?>

			<h2><?php esc_html_e( 'Syndication Settings', 'push-syndication' ); ?></h2>

			<form action="options.php" method="post">
				<?php settings_fields( 'push_syndicate_settings' ); ?>

				<?php do_settings_sections( 'push_syndicate_pull_sitegroups' ); ?>

				<?php do_settings_sections( 'push_syndicate_pull_options' ); ?>

				<?php submit_button( 'Pull Now & Save Changes', 'primary', 'push_syndicate_pull_now' ); ?>

				<?php do_settings_sections( 'push_syndicate_post_types' ); ?>

				<?php do_settings_sections( 'delete_pushed_posts' ); ?>

				<?php do_settings_sections( 'notifications' ); ?>

				<?php submit_button(); ?>
			</form>
		</div>
	<?php
	}

	/**
	 * Display Pull Sitegroups Description
	 *
	 * Displays a description under the group selection heading on the settings page.
	 */
	public function display_pull_sitegroups_description() {
		echo esc_html__( 'Select the Syndication Endpoint Groups to pull content', 'push-syndication' );
	}

	/**
	 * Display Pull Sitegroups Selection
	 *
	 * Displays a checkbox form item to select Syndication Endpoint Groups to enable.
	 */
	public function display_pull_sitegroups_selection() {
		global $settings_manager;

		// Get all sitegroups.
		$sitegroups = get_terms(
			'syn_sitegroup',
			array(
				'fields'        => 'all',
				'hide_empty'    => false,
				'orderby'       => 'name',
			)
		);

		// If there are no Syndication Endpoint Groups defined return.
		if ( empty( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No Syndication Endpoint Groups defined yet. You must group your Syndication Endpoints into Syndication Endpoint Groups to syndicate content', 'push-syndication' ) . '</p>';
			echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=syn_sitegroup&post_type=syn_site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
			return;
		}

		$options = array();

		foreach ( $sitegroups as $sitegroup ) {
			$options[ $sitegroup->slug ] = array(
				'name'        => $sitegroup->name,
				'description' => $sitegroup->description,
			);
		}

		$this->form_checkbox( $options, 'selected_pull_sitegroups' );
	}

	public  function display_pull_options_description() {
		echo esc_html__( 'Configure options for pulling content', 'push-syndication' );
	}

	public function display_time_interval_selection() {
		global $settings_manager;
		echo '<input type="text" size="10" name="push_syndicate_settings[pull_time_interval]" value="' . esc_attr( $settings_manager->get_setting( 'pull_time_interval' ) ) . '"/>';
	}

	/**
	 * Display Max Pull Attempts
	 *
	 * Display the form field for the push_syndication_max_pull_attempts option.
	 */
	public function display_max_pull_attempts() {
		global $settings_manager;
		?>
		<input type="text" size="10" name="push_syndicate_settings[push_syndication_max_pull_attempts]" value="<?php echo esc_attr( $settings_manager->get_setting( 'push_syndication_max_pull_attempts', 0 ) ); ?>" />
		<p><?php echo esc_html__( 'Syndication Endpoint will be disabled after failure threshold is reached. Set to 0 to disable.', 'push-syndication' ); ?></p>
		<?php
	}

	/**
	 * Validate the push_syndication_max_pull_attempts option.
	 *
	 * @param $val
	 * @return int
	 */
	public function validate_max_pull_attempts( $val ) {
		/**
		 * Filter the maximum value that can be used for the
		 * push_syndication_max_pull_attempts option. This only takes effect when the
		 * option is set. Use the pre_option_push_syndication_max_pull_attempts or
		 * option_push_syndication_max_pull_attempts filters to modify values that
		 * have already been set.
		 *
		 * @param int $upper_limit Maximum value that can be used. Defaults is 100.
		 */
		$upper_limit = apply_filters( 'push_syndication_max_pull_attempts_upper_limit', 100 );

		// Ensure a value between zero and the upper limit.
		return min( $upper_limit, max( 0, (int) $val ) );
	}

	public function display_update_pulled_posts_selection() {
		global $settings_manager;
		// @TODO refractor this
		echo '<input type="checkbox" name="push_syndicate_settings[update_pulled_posts]" value="on" ';
		echo checked( $settings_manager->get_setting( 'update_pulled_posts' ), 'on' ) . ' />';
	}

	public function display_push_post_types_description() {
		echo esc_html__( 'Select the post types to add support for pushing content', 'push-syndication' );
	}

	public function display_post_types_selection() {
		// @todo: Add more suitable filters.
		$post_types = get_post_types( array( 'public' => true ) );
		$options    = array();

		foreach ( $post_types as $post_type ) {
			$options[ $post_type ] = array(
				'name' => $post_type,
			);
		}

		$this->form_checkbox( $options, 'selected_post_types' );
	}

	public function display_delete_pushed_posts_description() {
		echo esc_html__( 'Tick the box to delete all the pushed posts when the master post is deleted', 'push-syndication' );
	}

	public function display_delete_pushed_posts_selection() {
		global $settings_manager;

		// @todo Refractor this.
		echo '<input type="checkbox" name="push_syndicate_settings[delete_pushed_posts]" value="on" ';
		echo checked( $settings_manager->get_setting( 'delete_pushed_posts' ), 'on' ) . ' />';
	}

	/**
	 * Display Nofication Description
	 *
	 * Displays the description for the notification settings section.
	 *
	 * @since 2.1
	 */
	public function display_notifications_description() {
		echo esc_html__( 'Setup email and Slack notifications.', 'push-syndication' );
	}

	/**
	 * Displays a checkbox selector to enabling/disabling email notifications
	 *
	 * @since 2.1
	 */
	public function display_notification_email_enabled() {
		$this->form_checkbox(
			array(
				'email' => array(
					'name' => __( 'Enable email notifications', 'push-syndication' ),
				),
			),
			'notification_methods'
		);
	}

	/**
	 * Displays an input box for saving a notification email address
	 *
	 * @since 2.1
	 */
	public function display_notification_email_address() {
		$this->form_input(
			'notification_email_address',
			array(
				'placeholder' => __( 'Email address', 'push-syndication' ),
				'description' => __( 'The email address where alerts should be sent', 'push-syndication' ),
			)
		);
	}

	/**
	 * Displays a checkbox to enabled different email notification types
	 *
	 * @since 2.1
	 */
	public function display_notification_email_types() {
		echo '<p><strong>' . esc_html__( 'Send notification when', 'push-syndication' ) . '</strong></p>';

		$this->form_checkbox(
			array(
				'processed' => array(
					'name' => __( 'Endpoint processed', 'push-syndication' ),
				),
				'create'       => array(
					'name' => __( 'New post created', 'push-syndication' ),
				),
				'update'      => array(
					'name' => __( 'Existing post updated', 'push-syndication' ),
				),
				'delete'    => array(
					'name' => __( 'Existing post deleted', 'push-syndication' ),
				),
			),
			'notification_email_types'
		);
	}

	/**
	 * Displays a checkbox selector to enabling/disabling Slack notifications
	 *
	 * @since 2.1
	 */
	public function display_notification_slack_enabled() {
		$this->form_checkbox(
			array(
				'slack' => array(
					'name' => __( 'Enable Slack notifications', 'push-syndication' ),
				),
			),
			'notification_methods'
		);
	}

	/**
	 * Displays an input box for saving a notification Slack webhook
	 *
	 * @since 2.1
	 */
	public function display_notification_slack_webhook() {
		$this->form_input(
			'notification_slack_webhook',
			array(
				'placeholder' => __( 'Slack Webhook URL', 'push-syndication' ),
				'description' => sprintf( __( 'Setup a new Slack webhook URL %s', 'push-syndication' ), '<a href="https://my.slack.com/services/new/incoming-webhook/" target="_blank">' . __( 'here', 'push-syndication' ) . '</a>' ),
			)
		);
	}

	/**
	 * Displays a checkbox to enabled different Slack notification types
	 *
	 * @since 2.1
	 */
	public function display_notification_slack_types() {
		echo '<p><strong>' . esc_html__( 'Send notification when', 'push-syndication' ) . '</strong></p>';

		$this->form_checkbox(
			array(
				'processed' => array(
					'name' => __( 'Endpoint processed', 'push-syndication' ),
				),
				'create'       => array(
					'name' => __( 'New post created', 'push-syndication' ),
				),
				'update'      => array(
					'name' => __( 'Existing post updated', 'push-syndication' ),
				),
				'delete'    => array(
					'name' => __( 'Existing post deleted', 'push-syndication' ),
				),
			),
			'notification_slack_types'
		);
	}

	public function display_client_id() {
		global $settings_manager;
		echo '<input type="text" size=100 name="push_syndicate_settings[client_id]" value="' . esc_attr( $settings_manager->get_setting( 'client_id' ) ) . '"/>';
	}

	public function display_client_secret() {
		global $settings_manager;
		echo '<input type="text" size=100 name="push_syndicate_settings[client_secret]" value="' . esc_attr( $settings_manager->get_setting( 'client_secret' ) ) . '"/>';
	}

	/**
	 * Form Checkbox
	 *
	 * Generates a checkbox form item.
	 *
	 * @since 2.1
	 * @param array  $setting_options The options for the checkboxes.
	 * @param string $setting_key The settings key which stores the values of the form item.
	 */
	public function form_checkbox( $setting_options = array(), $setting_key ) {
		global $settings_manager;

		$saved_option = $settings_manager->get_setting( $setting_key );

		foreach ( $setting_options as $option_key => $option ) {
			?>
			<p>
				<label>
					<input type="checkbox" name="push_syndicate_settings[<?php echo esc_attr( $setting_key ); ?>][]" value="<?php echo esc_attr( $option_key ); ?>" <?php $this->checked_array( $option_key, $saved_option ); ?> />
					<?php echo esc_html( $option['name'] ); ?>
				</label>
				<?php
				if ( ! empty( $option['description'] ) ) :
					echo wp_kses_post( $option['description'] );
				endif;
				?>
			</p>
			<?php
		}
	}

	/**
	 * Form Input
	 *
	 * Generates a form input box. Has the following arguments which should be
	 * passed as the second method argument.
	 *
	 * `default` Sets the default value for the form input box
	 * `class` Override the default class value for the input element
	 *
	 * @since 2.1
	 * @param string $setting_key The settings key which stores the values of the form item.
	 * @param array  $args Options for the form output (see above).
	 */
	public function form_input( $setting_key, $args ) {
		global $settings_manager;

		$default     = '';
		$class       = 'regular-text';
		$placeholder = '';

		if ( ! empty( $args['default'] ) ) {
			$default = $args['default'];
		}

		if ( ! empty( $args['class'] ) ) {
			$class = $args['class'];
		}

		if ( ! empty( $args['placeholder'] ) ) {
			$placeholder = $args['placeholder'];
		}
		?>
		<p><input type="text" name="push_syndicate_settings[<?php echo esc_attr( $setting_key ); ?>]" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="<?php echo esc_attr( $class ); ?>" value="<?php echo esc_attr( $settings_manager->get_setting( $setting_key, $default ) ); ?>" /></p>
		<?php if ( ! empty( $args['description'] ) ) : ?>
		<p class="description"><?php echo wp_kses_post( $args['description'] ); ?></p>
		<?php
		endif;
	}

	/**
	 * Checked Array
	 *
	 * Checks if a value exists in an array, and if it does outputs markup to
	 * mark a checkbox as checked. Used for checkbox inputs on forms.
	 *
	 * @param string $value The needle.
	 * @param array  $group The haystack.
	 */
	public function checked_array( $value, $group ) {
		if ( ! empty( $group ) ) {
			if ( in_array( $value, $group, true ) ) {
				echo 'checked="checked"';
			}
		}
	}
}
