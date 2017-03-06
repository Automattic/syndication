<?php
/**
 * Client Options
 *
 * Client options are options that are specific to a client but apply to all
 * sites using that client.
 */

namespace Automattic\Syndication\Clients\Test;

class Client_Options {

	public function __construct() {

		add_action( 'syndication/render_client_options', [ $this, 'render_client_options' ] );
		add_action( 'syndication/save_client_options', [ $this, 'save_client_options' ] );
	}

	public function render_client_options() {
		?>

		<h3>Test Client Options</h3>
		<p>This is options that are specific to the Test Client and apply to all sites that use the Test Client. </p>

		<?php
	}

	public function save_client_options() {

	}
}