<?php
/**
 * Post Edit Screen
 *
 * @since 2.1
 * @package Automattic\Syndication\Admin
 */

namespace Automattic\Syndication\Admin;

/**
 * Class Post_Edit_Screen
 *
 * @since 2.1
 * @package Automattic\Syndication\Admin
 */
class Post_Edit_Screen {
	/**
	 * Post_Edit_Screen constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_post_metaboxes' ) );
		add_action( 'transition_post_status', array( $this, 'save_syndicate_settings' ) ); // use transition_post_status instead of save_post because the former is fired earlier which causes race conditions when a site group select and publish happen on the same load

		// Filter admin notices in custom post types.
		add_filter( 'post_updated_messages', array( $this, 'push_syndicate_admin_messages' ) );

		// Post columns
		add_filter( 'manage_posts_columns', array( $this, 'posts_add_new_columns' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'posts_manage_columns' ), 10, 2 );
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

	public function add_post_metaboxes() {
		global $settings_manager;
		// return if no post types supports push syndication
		$setting = $settings_manager->get_setting( 'selected_post_types' );
		if ( empty( $setting ) ) {
			return;
		}

		if ( ! $settings_manager->current_user_can_syndicate() ) {
			return;
		}

		$selected_post_types = $settings_manager->get_setting( 'selected_post_types' );
		foreach ( $selected_post_types as $selected_post_type ) {
			add_meta_box(
				'syndicatediv',
				__( 'Syndicate', 'push-syndication' ),
				array( $this, 'add_syndicate_metabox' ),
				$selected_post_type,
				/**
				 * Filters the 'Syndicate' meta box context.
				 *
				 * @param string $context Meta box context. Default 'side'.
				 * @param string $selected_post_type Post type the meta box is being added to.
				 */
				apply_filters( 'syn_syndicate_metabox_context', 'side', $selected_post_type ),
				/**
				 * Filters the 'Syndicate' meta box priority.
				 *
				 * @param string $priority Meta box priority. Default 'high'.
				 * @param string $selected_post_type Post type the meta box is being added to.
				 */
				apply_filters( 'syn_syndicate_metabox_priority', 'high', $selected_post_type )
			);
			//add_meta_box( 'syndicationstatusdiv', __( ' Syndication Status ' ), array( $this, 'add_syndication_status_metabox' ), $selected_post_type, 'normal', 'high' );
		}

	}

	public function add_syndicate_metabox( ) {

		global $post;

		// nonce for verification when saving
		wp_nonce_field( 'syndicate_post_edit', 'syndicate_noncename' );
		// get all sitegroups
		$sitegroups = get_terms(
			'syn_sitegroup',
			array(
				'fields'     => 'all',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		// if there are no sitegroups defined return
		if ( empty( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
			echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=syn_sitegroup&post_type=syn_site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
			return;
		}

		/**
		 * Filters the selectable Site Groups in the 'Syndicate' meta box.
		 *
		 * @param array $sitegroups Array of found 'syn_sitegroup' \WP_Term objects.
		 * @param \WP_Post $post Post object for the post being edited.
		 */
		$sitegroups = apply_filters( 'syn_syndicate_metabox_sitegroups', $sitegroups, $post );

		if ( empty( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No Site Groups found.', 'push-syndication' ) . '</p>';
			return;
		}

		$selected_sitegroups = get_post_meta( $post->ID, '_syn_selected_sitegroups', true );
		$selected_sitegroups = ! empty( $selected_sitegroups ) ? $selected_sitegroups : array() ;

		echo '<ul>';

		foreach ( $sitegroups as $sitegroup  ) {

			?>
			<li>
				<label>
					<input type="checkbox" name="selected_sitegroups[]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $selected_sitegroups ) ?> />
					<?php echo esc_html( $sitegroup->name ); ?>
				</label>
				<p> <?php echo esc_html( $sitegroup->description ); ?> </p>
			</li>
		<?php

		}

		echo '</ul>';

		/**
		 * Fires after the 'Syndicate' meta box content renders.
		 *
		 * @param \WP_Post $post Post object for the post being edited.
		 * @param array $sitegroups \WP_Term objects for the sitegroups that were selectable.
		 */
		do_action( 'syn_after_syndicate_metabox', $post, $sitegroups );
	}

	public function save_syndicate_settings() {
		global $post, $settings_manager;

		// autosave verification
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		// if our nonce isn't there, or we can't verify it return
		if ( ! isset( $_POST['syndicate_noncename'] ) || ! wp_verify_nonce( $_POST['syndicate_noncename'], 'syndicate_post_edit' ) )
			return;

		if ( ! $settings_manager->current_user_can_syndicate() )
			return;

		$selected_sitegroups = ! empty( $_POST['selected_sitegroups'] ) ? array_map( 'sanitize_key', $_POST['selected_sitegroups'] ) : '' ;
		update_post_meta( $post->ID, '_syn_selected_sitegroups', $selected_sitegroups );

	}

	public function push_syndicate_admin_messages( $messages ) {

		// general error messages
		$messages['syn_site'][250] = __( 'Transport class not found!', 'push-syndication' );
		$messages['syn_site'][251] = __( 'Connection Successful!', 'push-syndication' );
		$messages['syn_site'][252] = __( 'Something went wrong when connecting to the site. Site disabled.', 'push-syndication' );

		// xmlrpc error messages.
		$messages['syn_site'][301] = __( 'Invalid URL.', 'push-syndication' );
		$messages['syn_site'][302] = __( 'You do not have sufficient capability to perform this action.', 'push-syndication' );
		$messages['syn_site'][303] = __( 'Bad login/pass combination.', 'push-syndication' );
		$messages['syn_site'][304] = __( 'XML-RPC services are disabled on this site.', 'push-syndication' );
		$messages['syn_site'][305] = __( 'Transport error. Invalid endpoint', 'push-syndication' );
		$messages['syn_site'][306] = __( 'Something went wrong when connecting to the site.', 'push-syndication' );

		// WordPress.com REST error messages
		$messages['site'][301] = __( 'Invalid URL', 'push-syndication' );

		// RSS error messages

		return $messages;

	}

	/**
	 * Posts Add New Columns
	 *
	 * Add custom column to the Posts list table.
	 *
	 * @since 2.1
	 * @see manage_posts_columns
	 * @param array $post_columns An array of column names.
	 * @return array An array of new column names.
	 */
	public function posts_add_new_columns( $post_columns ) {
		$post_columns['syn_post_sitegroup'] = _x( 'Syndication Groups', 'column name', 'push-syndication' );
		return $post_columns;
	}

	/**
	 * Posts Manage Columns
	 *
	 * Display site group links in the Posts list table.
	 *
	 * @since 2.1
	 * @see manage_posts_custom_column
	 * @param string $column_name The name of the column to display.
	 * @param int    $post_id     The current post ID.
	 */
	public function posts_manage_columns( $column_name, $post_id ) {
		if ( 'syn_post_sitegroup' === $column_name ) {
			// Get post site groups.
			$selected_sitegroups = get_post_meta( $post_id, '_syn_selected_sitegroups', true );

			if ( empty( $selected_sitegroups ) ) {
				echo '<span aria-hidden="true">—</span>';
			} else {
				// Get post's site groups
				$sitegroups = get_terms( 'syn_sitegroup', array(
					'fields'     => 'all',
					'slug'       => $selected_sitegroups,
					'hide_empty' => false,
					'orderby'    => 'name',
				) );

				// Build links
				$links = array();

				foreach ( $sitegroups as $sitegroup ) {
					$url     = get_admin_url() . 'edit.php?post_type=syn_site&syn_sitegroup=' . $sitegroup->slug;
					$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $sitegroup->name ) . '</a>';
				}

				echo wp_kses_post( implode( ', ', $links ) );
			}
		}
	}
}
