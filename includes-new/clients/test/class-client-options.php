<?php
/**
 * Site Options
 *
 * Site options are options specific to site using a particular client.
 */

namespace Automattic\Syndication\Clients\Test;

class Client_Options {

	public function __construct() {

		add_action( 'syndication/render_site_options/test_pull', [ $this, 'render_site_options_pull' ] );
		add_action( 'syndication/save_site_options/test_pull', [ $this, 'save_site_options_pull' ] );
		add_action( 'syndication/render_site_options/test_push', [ $this, 'render_site_options_push' ] );
		add_action( 'syndication/save_site_options/test_push', [ $this, 'save_site_options_push' ] );
	}

	public function render_site_options_pull( $site_id ) {

		// Render options making sure form fields have unique names.
		?>
		<h3>Test Client Options Pull</h3>
		<p>These are the Test Client options specific to this site.</p>
		<?php
	}

	public function save_site_options_pull( $site_id ) {

		// Save options from the $_POST object.
	}

	public function render_site_options_push( $site_id ) {

		// Render options making sure form fields have unique names.
		?>
		<h3>Test Client Options push</h3>
		<p>These are the Test Client options specific to this site.</p>
		<?php
	}

	public function save_site_options_push( $site_id ) {

		// Save options from the $_POST object.
	}
}
