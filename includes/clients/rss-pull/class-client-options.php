<?php
/**
 * Site Options
 *
 * Site options are options specific to site using a particular client.
 */

namespace Automattic\Syndication\Clients\RSS_Pull;

class Client_Options {

	public function __construct() {

		add_action( 'syndication/render_site_options/rss_pull', [ $this, 'render_site_options_pull' ] );
		add_action( 'syndication/save_site_options/rss_pull', [ $this, 'save_site_options_pull' ] );

		// Add hooks client can for rendering and saving client settings.
		add_action( 'syndication/render_client_options', [ $this, 'render_client_options' ] );
		add_action( 'syndication/save_client_options', [ $this, 'save_client_options' ] );

	}

	public function render_site_options_pull( $site_id ) {
		//TODO: JS if is_meta show text box, if is_photo show photo select with numbers as values, else show select of post fields
		//TODO: JS Validation
		//TODO: deal with ability to select, i.e. media:group/media:thumbnail[@width="75"]/@url (can't be unserialized as is with quotes around 75)
		$feed_url					= get_post_meta( $site_id, 'syn_feed_url', true );
		$default_post_type			= get_post_meta( $site_id, 'syn_default_post_type', true );
		$default_post_status		= get_post_meta( $site_id, 'syn_default_post_status', true );
		$default_comment_status		= get_post_meta( $site_id, 'syn_default_comment_status', true );
		$default_ping_status		= get_post_meta( $site_id, 'syn_default_ping_status', true );
		$node_config				= get_post_meta( $site_id, 'syn_node_config', true );
		$default_cat_status		    = get_post_meta( $site_id, 'syn_default_cat_status', true );

		if ( isset( $node_config['namespace'] ) ) {
			$namespace = $node_config['namespace'];
		}

		?>
		<p>
			<label for="feed_url"><?php echo esc_html__( 'Enter feed URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="feed_url" id="feed_url" size="100" value="<?php echo esc_attr( $feed_url ); ?>" />
		</p>
		<p>
			<label for="default_post_type"><?php echo esc_html__( 'Select post type', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_type" id="default_post_type" />

			<?php

			$post_types = get_post_types();

			foreach ( $post_types as $post_type ) {
				echo '<option value="' . esc_attr( $post_type ) . '"' . selected( $post_type, $default_post_type ) . '>' . esc_html( $post_type )  . '</option>';
			}

			?>

			</select>
		</p>
		<p>
			<label for="default_post_status"><?php echo esc_html__( 'Select post status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_status" id="default_post_status" />

			<?php

			$post_statuses  = get_post_statuses();

			foreach ( $post_statuses as $key => $value ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $default_post_status ) . '>' . esc_html( $key )  . '</option>';
			}

			?>

			</select>
		</p>
		<p>
			<label for="default_comment_status"><?php echo esc_html__( 'Select comment status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_comment_status" id="default_comment_status" />
			<option value="open" <?php selected( 'open', $default_comment_status )  ?> >open</option>
			<option value="closed" <?php selected( 'closed', $default_comment_status )  ?> >closed</option>
			</select>
		</p>
		<p>
			<label for="default_ping_status"><?php echo esc_html__( 'Select ping status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_ping_status" id="default_ping_status" />
			<option value="open" <?php selected( 'open', $default_ping_status )  ?> >open</option>
			<option value="closed" <?php selected( 'closed', $default_ping_status )  ?> >closed</option>
			</select>
		</p>
		<p>
			<label for="default_cat_status"><?php echo esc_html__( 'Select category status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_cat_status" id="default_cat_status" />
			<option value="yes" <?php selected( 'yes', $default_cat_status )  ?> ><?php echo esc_html__( 'import categories', 'push-syndication' ); ?></option>
			<option value="no" <?php selected( 'no', $default_cat_status )  ?> ><?php echo esc_html__( 'ignore categories', 'push-syndication' ); ?></option>
			</select>
		</p>



		<?php
	}

	public function save_site_options_pull( $site_id ) {
		// TODO: adjust to save all settings required by XML feed
		// TODO: validate saved values (e.g. valid post_type? valid status?)
		// TODO: actually check if saving was successful or not and return a proper bool

		update_post_meta( $site_id, 'syn_feed_url', isset ( $_POST['feed_url'] ) ? esc_url_raw( $_POST['feed_url'] ) : '' );
		update_post_meta( $site_id, 'syn_default_post_type', isset ( $_POST['default_post_type'] ) ? sanitize_text_field( $_POST['default_post_type'] ) : '' );
		update_post_meta( $site_id, 'syn_default_post_status', isset ( $_POST['default_post_status'] ) ? sanitize_text_field( $_POST['default_post_status'] ) : '' );
		update_post_meta( $site_id, 'syn_default_comment_status', isset ( $_POST['default_comment_status'] ) ? sanitize_text_field( $_POST['default_comment_status'] ) : '' );
		update_post_meta( $site_id, 'syn_default_ping_status', isset ( $_POST['default_ping_status'] ) ? sanitize_text_field( $_POST['default_ping_status'] ) : '' );

		return true;
	}

	/**
	 * Rewrite wp_dropdown_categories output to enable a multiple select
	 * @param  string $result rendered category dropdown list
	 * @return string altered category dropdown list
	 */
	public static function make_multiple_categories_dropdown( $result ) {
		$result = preg_replace( '#^<select name#', '<select multiple="multiple" name', $result );
		return $result;
	}

	/**
	 * Render client options on the Settings->Syndication screen.
	 */
	public function render_client_options() {
	}

	/**
	 * Save client settings from the Settings->Syndication screen.
	 */
	public function save_client_options() {
	}
}
