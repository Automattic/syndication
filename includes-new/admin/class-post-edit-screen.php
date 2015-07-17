<?php
/**
 * Post Edit Screen
 */

namespace Automattic\Syndication\Admin;

class Post_Edit_Screen {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_post_metaboxes' ) );
	}

	public function add_post_metaboxes() {
		global $settings_manager;
		// return if no post types supports push syndication
		if( empty( $settings_manager->get_setting( 'selected_post_types' ) ) ) {
			return;
		}

		if( ! $this->current_user_can_syndicate() ) {
			return;
		}

		$selected_post_types = $settings_manager->get_setting( 'selected_post_types' );
		foreach( $selected_post_types as $selected_post_type ) {
			add_meta_box( 'syndicatediv', __( ' Syndicate ' ), array( $this, 'add_syndicate_metabox' ), $selected_post_type, 'side', 'high' );
			//add_meta_box( 'syndicationstatusdiv', __( ' Syndication Status ' ), array( $this, 'add_syndication_status_metabox' ), $selected_post_type, 'normal', 'high' );
		}

	}

	// checking user capability
	public function current_user_can_syndicate() {
		$syndicate_cap = apply_filters( 'syn_syndicate_cap', 'manage_options' );
		return current_user_can( $syndicate_cap );
	}

	public function add_syndicate_metabox( ) {

		global $post;

		// nonce for verification when saving
		wp_nonce_field( plugin_basename( __FILE__ ), 'syndicate_noncename' );

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

		$selected_sitegroups = get_post_meta( $post->ID, '_syn_selected_sitegroups', true );
		$selected_sitegroups = !empty( $selected_sitegroups ) ? $selected_sitegroups : array() ;

		echo '<ul>';

		foreach( $sitegroups as $sitegroup  ) {

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

	}
}