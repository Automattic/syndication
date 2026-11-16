<?php
/**
 * Tests the upgrade that saves XML sites' effective status settings.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class UpgradeXmlSiteStatusTest
 *
 * Before 2.2.3 the XML client ignored its status settings: every new pulled
 * post was published, with the default comment and ping status for its post
 * type. Now the settings apply, so the upgrade saves what has actually been
 * happening as the settings.
 *
 * @covers WP_Push_Syndication_Server::upgrade
 */
class UpgradeXmlSiteStatusTest extends WPIntegrationTestCase {

	/**
	 * The server's stored version before the test changed it.
	 *
	 * @var mixed
	 */
	private $original_version;

	/**
	 * Remember the server's stored version.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_version = $this->version_property()->getValue( $GLOBALS['push_syndication_server'] );
	}

	/**
	 * Restore the server's stored version.
	 */
	public function tear_down(): void {
		$this->version_property()->setValue( $GLOBALS['push_syndication_server'], $this->original_version );
		parent::tear_down();
	}

	/**
	 * Get the server's private version property.
	 *
	 * @return \ReflectionProperty
	 */
	private function version_property(): \ReflectionProperty {
		$property = new \ReflectionProperty( 'WP_Push_Syndication_Server', 'version' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		return $property;
	}

	/**
	 * Run the upgrade as if from the given version.
	 *
	 * @param string $from Version the site is upgrading from.
	 */
	private function upgrade_from( string $from ): void {
		$this->version_property()->setValue( $GLOBALS['push_syndication_server'], $from );

		$method = new \ReflectionMethod( 'WP_Push_Syndication_Server', 'upgrade' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$method->invoke( $GLOBALS['push_syndication_server'] );
	}

	/**
	 * Create a site with the given transport and status settings.
	 *
	 * @param string $transport   Transport type.
	 * @param string $post_type   Post type pulled posts are created as.
	 * @param string $post_status Status the site creates its posts with.
	 * @return int The site ID.
	 */
	private function make_site( string $transport, string $post_type = 'post', string $post_status = 'publish' ): int {
		$site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => $post_status,
			)
		);

		update_post_meta( $site_id, 'syn_transport_type', $transport );
		update_post_meta( $site_id, 'syn_default_post_type', $post_type );
		update_post_meta( $site_id, 'syn_default_post_status', 'draft' );
		update_post_meta( $site_id, 'syn_default_comment_status', 'closed' );
		update_post_meta( $site_id, 'syn_default_ping_status', 'closed' );

		return $site_id;
	}

	/**
	 * Get a site's status settings.
	 *
	 * @param int $site_id The site ID.
	 * @return string[] Post, comment and ping status.
	 */
	private function statuses( int $site_id ): array {
		return array(
			get_post_meta( $site_id, 'syn_default_post_status', true ),
			get_post_meta( $site_id, 'syn_default_comment_status', true ),
			get_post_meta( $site_id, 'syn_default_ping_status', true ),
		);
	}

	/**
	 * XML sites get the statuses their posts have been getting; other sites are untouched.
	 */
	public function test_upgrade_saves_the_statuses_xml_sites_have_been_using(): void {
		update_option( 'default_comment_status', 'open' );
		update_option( 'default_ping_status', 'open' );

		$xml_post_site  = $this->make_site( 'WP_XML' );
		$xml_page_site  = $this->make_site( 'WP_XML', 'page' );
		$xml_draft_site = $this->make_site( 'WP_XML', 'post', 'draft' );
		$rss_site       = $this->make_site( 'WP_RSS' );

		$this->upgrade_from( '2.2.1' );

		$this->assertSame( array( 'publish', 'open', 'open' ), $this->statuses( $xml_post_site ) );
		$this->assertSame( array( 'publish', 'closed', 'closed' ), $this->statuses( $xml_page_site ), 'Pages default to closed comments and pings.' );
		$this->assertSame( array( 'publish', 'open', 'open' ), $this->statuses( $xml_draft_site ), 'A site that is not published itself is still upgraded.' );
		$this->assertSame( array( 'draft', 'closed', 'closed' ), $this->statuses( $rss_site ) );
	}

	/**
	 * A site already on 2.2.3 keeps the settings an admin has chosen since.
	 */
	public function test_upgrade_does_not_run_again(): void {
		$site_id = $this->make_site( 'WP_XML' );

		$this->upgrade_from( '2.2.3' );

		$this->assertSame( array( 'draft', 'closed', 'closed' ), $this->statuses( $site_id ) );
	}
}
