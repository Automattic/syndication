<?php
namespace Automattic\Syndication;

class Test_Upgrade_Tasks extends \WP_UnitTestCase {
	function test_upgrade_custom_transport_types_to_3_0_0() {
		$transport_type = rand_str();

		$site_id = $this->factory->post->create( [
			'post_type' => 'syn_site',
			'meta_input' => [
				'syn_transport_type' => $transport_type,
			],
		] );

		( new Upgrade_Tasks() )->upgrade_to_3_0_0();

		$this->assertSame( $transport_type, get_post_meta( $site_id, 'syn_transport_type', true ),
			'Upgrading to version 3.0.0 should not delete custom transport type values.'
		);
	}
}
