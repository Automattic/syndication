<?php
/**
 * Settings Screen
 *
 * Responsible for plugin-level settings screens.
 */

namespace Automattic\Syndication\Admin;

class Settings_Screen {


	public function __construct() {

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'register_syndicate_settings' ) );
	}

	public function admin_init() {

		register_setting( 'push_syndicate_settings', 'push_syndicate_settings', array( $this, 'push_syndicate_settings_validate' ) );
		register_setting( 'push_syndicate_settings', 'push_syndication_max_pull_attempts', array( $this, 'validate_max_pull_attempts' ) );
	}

	/**
	 * Validate the push syndication settings.
	 *
	 * @param $raw_settings array Settings to validate.
	 *
	 * @return array              Validated settings.
	 */
	public function push_syndicate_settings_validate( $raw_settings ) {

		$settings                               = array();
		$settings['client_id']                  = sanitize_text_field( $raw_settings['client_id'] );
		$settings['client_secret']              = sanitize_text_field( $raw_settings['client_secret'] );
		$settings['selected_post_types']        = !empty( $raw_settings['selected_post_types'] ) ? $raw_settings['selected_post_types'] : array() ;
		$settings['delete_pushed_posts']        = !empty( $raw_settings['delete_pushed_posts'] ) ? $raw_settings['delete_pushed_posts'] : 'off' ;
		$settings['selected_pull_sitegroups']   = !empty( $raw_settings['selected_pull_sitegroups'] ) ? $raw_settings['selected_pull_sitegroups'] : array() ;
		$settings['pull_time_interval']         = !empty( $raw_settings['pull_time_interval'] ) ? max( $raw_settings['pull_time_interval'], 300 ) : '3600';
		$settings['update_pulled_posts']        = !empty( $raw_settings['update_pulled_posts'] ) ? $raw_settings['update_pulled_posts'] : 'off' ;

		return $settings;

	}

	public function register_syndicate_settings() {

		add_submenu_page( 'options-general.php', esc_html__( 'Syndication Settings', 'push-syndication' ), esc_html__( 'Syndication', 'push-syndication' ), apply_filters('syn_syndicate_cap', 'manage_options'), 'push-syndicate-settings', array( $this, 'display_syndicate_settings' ) );
	}

	public function display_syndicate_settings() {

		// @todo all validation and sanitization should be moved to a separate object.
		add_settings_section( 'push_syndicate_pull_sitegroups', esc_html__( 'Site Groups' , 'push-syndication' ), array( $this, 'display_pull_sitegroups_description' ), 'push_syndicate_pull_sitegroups' );
		add_settings_field( 'pull_sitegroups_selection', esc_html__( 'select sitegroups', 'push-syndication' ), array( $this, 'display_pull_sitegroups_selection' ), 'push_syndicate_pull_sitegroups', 'push_syndicate_pull_sitegroups' );

		add_settings_section( 'push_syndicate_pull_options', esc_html__( 'Pull Options' , 'push-syndication' ), array( $this, 'display_pull_options_description' ), 'push_syndicate_pull_options' );
		add_settings_field( 'pull_time_interval', esc_html__( 'Specify time interval in seconds', 'push-syndication' ), array( $this, 'display_time_interval_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );
		add_settings_field( 'max_pull_attempts', esc_html__( 'Maximum pull attempts', 'push-syndication' ), array( $this, 'display_max_pull_attempts' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );
		add_settings_field( 'update_pulled_posts', esc_html__( 'update pulled posts', 'push-syndication' ), array( $this, 'display_update_pulled_posts_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );

		add_settings_section( 'push_syndicate_post_types', esc_html__( 'Post Types' , 'push-syndication' ), array( $this, 'display_push_post_types_description' ), 'push_syndicate_post_types' );
		add_settings_field( 'post_type_selection', esc_html__( 'select post types', 'push-syndication' ), array( $this, 'display_post_types_selection' ), 'push_syndicate_post_types', 'push_syndicate_post_types' );

		add_settings_section( 'delete_pushed_posts', esc_html__(' Delete Pushed Posts ', 'push-syndication' ), array( $this, 'display_delete_pushed_posts_description' ), 'delete_pushed_posts' );
		add_settings_field( 'delete_post_check', esc_html__(' delete pushed posts ', 'push-syndication' ), array( $this, 'display_delete_pushed_posts_selection' ), 'delete_pushed_posts', 'delete_pushed_posts' );

		?>

		<div class="wrap" xmlns="http://www.w3.org/1999/html">

			<?php screen_icon(); // @TODO custom screen icon ?>

			<h2><?php esc_html_e( 'Syndication Settings', 'push-syndication' ); ?></h2>

			<form action="options.php" method="post">

				<?php settings_fields( 'push_syndicate_settings' ); ?>

				<?php do_settings_sections( 'push_syndicate_pull_sitegroups' ); ?>

				<?php do_settings_sections( 'push_syndicate_pull_options' ); ?>

				<?php submit_button( '  Pull Now ' ); ?>

				<?php do_settings_sections( 'push_syndicate_post_types' ); ?>

				<?php do_settings_sections( 'delete_pushed_posts' ); ?>

				<?php
				// @todo not sure of the validity of this
				do_action( 'syndication/render_plugin_options' );
				do_action( 'syndication/render_client_options' );
				?>

				<?php submit_button(); ?>

			</form>

		</div>

	<?php

	}

	public function display_pull_sitegroups_description() {
		echo esc_html__( 'Select the sitegroups to pull content', 'push-syndication' );
	}

	public function display_pull_sitegroups_selection() {
		global $settings_manager;
		// get all sitegroups
		$sitegroups = get_terms( 'syn_sitegroup', array(
			'fields'        => 'all',
			'hide_empty'    => false,
			'orderby'       => 'name'
		) );

		// if there are no sitegroups defined return
		if( empty( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
			echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=syn_sitegroup&post_type=syn_site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
			return;
		}

		foreach( $sitegroups as $sitegroup ) {

			?>

			<p>
				<label>
					<input type="checkbox" name="push_syndicate_settings[selected_pull_sitegroups][]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $settings_manager->get_setting( 'selected_pull_sitegroups' ) ) ?> />
					<?php echo esc_html( $sitegroup->name ); ?>
				</label>
				<?php echo esc_html( $sitegroup->description ); ?>
			</p>

		<?php

		}

	}

	public  function display_pull_options_description() {
		echo esc_html__( 'Configure options for pulling content', 'push-syndication' );
	}

	public function display_time_interval_selection() {
		global $settings_manager;
		echo '<input type="text" size="10" name="push_syndicate_settings[pull_time_interval]" value="' . esc_attr( $settings_manager->get_setting( 'pull_time_interval' ) ) . '"/>';
	}

	/**
	 * Display the form field for the push_syndication_max_pull_attempts option.
	 */
	public function display_max_pull_attempts() {
		?>
		<input type="text" size="10" name="push_syndication_max_pull_attempts" value="<?php echo esc_attr( get_option( 'push_syndication_max_pull_attempts', 0 ) ); ?>" />
		<p><?php echo esc_html__( 'Site will be disabled after failure threshold is reached. Set to 0 to disable.', 'push-syndication' ); ?></p>
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
		 * @param int $upper_limit Maximum value that can be used. Defaults to 100.
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
		global $settings_manager;
		// @TODO add more suitable filters
		$post_types = get_post_types( array( 'public' => true ) );

		echo '<ul>';

		foreach( $post_types as $post_type  ) {

			?>

			<li>
				<label>
					<input type="checkbox" name="push_syndicate_settings[selected_post_types][]" value="<?php echo esc_attr( $post_type ); ?>" <?php echo $this->checked_array( $post_type, $settings_manager->get_setting( 'selected_post_types' ) ); ?> />
					<?php echo esc_html( $post_type ); ?>
				</label>
			</li>

		<?php

		}

		echo '</ul>';

	}

	public function display_delete_pushed_posts_description() {
		echo esc_html__( 'Tick the box to delete all the pushed posts when the master post is deleted', 'push-syndication' );
	}

	public function display_delete_pushed_posts_selection() {
		global $settings_manager;
		// @TODO refractor this
		echo '<input type="checkbox" name="push_syndicate_settings[delete_pushed_posts]" value="on" ';
		echo checked( $settings_manager->get_setting( 'delete_pushed_posts' ), 'on' ) . ' />';
	}

	public function display_client_id() {
		global $settings_manager;
		echo '<input type="text" size=100 name="push_syndicate_settings[client_id]" value="' . esc_attr( $settings_manager->get_setting( 'client_id' ) ) . '"/>';
	}

	public function display_client_secret() {
		global $settings_manager;
		echo '<input type="text" size=100 name="push_syndicate_settings[client_secret]" value="' . esc_attr( $settings_manager->get_setting( 'client_secret' ) ) . '"/>';
	}

	public function display_sitegroups_selection() {

		echo '<h3>' . esc_html__( 'Select Sitegroups', 'push-syndication' ) . '</h3>';

		$selected_sitegroups = get_option( 'syn_selected_sitegroups' );
		$selected_sitegroups = !empty( $selected_sitegroups ) ? $selected_sitegroups : array() ;

		// get all sitegroups
		$sitegroups = get_terms( 'syn_sitegroup', array(
			'fields'        => 'all',
			'hide_empty'    => false,
			'orderby'       => 'name'
		) );

		// if there are no sitegroups defined return
		if( empty( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
			echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=syn_sitegroup&post_type=syn_site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
			return;
		}

		foreach( $sitegroups as $sitegroup ) {

			?>

			<p>
				<label>
					<input type="checkbox" name="syn_selected_sitegroups[]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $selected_sitegroups ) ?> />
					<?php echo esc_html( $sitegroup->name ); ?>
				</label>
				<?php echo esc_html( $sitegroup->description ); ?>
			</p>

		<?php

		}

	}

	public function checked_array( $value, $group ) {
		if( !empty( $group ) ) {
			if( in_array( $value, $group ) ) {
				echo 'checked="checked"';
			}
		}
	}
}
