<?php
/**
 * Unit tests for TransportFactory.
 *
 * @package Automattic\Syndication\Tests\Unit\Infrastructure\Transport
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Infrastructure\Transport;

use Automattic\Syndication\Domain\Contracts\EncryptorInterface;
use Automattic\Syndication\Infrastructure\Transport\TransportFactory;
use Automattic\Syndication\Tests\Unit\TestCase;
use Brain\Monkey\Functions;
use Mockery;
use WP_Post;

/**
 * Test case for TransportFactory.
 *
 * @group unit
 * @covers \Automattic\Syndication\Infrastructure\Transport\TransportFactory
 */
class TransportFactoryTest extends TestCase {

	/**
	 * TransportFactory instance.
	 *
	 * @var TransportFactory
	 */
	private TransportFactory $factory;

	/**
	 * Mock encryptor.
	 *
	 * @var EncryptorInterface&\Mockery\MockInterface
	 */
	private EncryptorInterface $encryptor;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->encryptor = Mockery::mock( EncryptorInterface::class );
		$this->factory   = new TransportFactory( $this->encryptor );
	}

	/**
	 * Test create returns null when post not found.
	 */
	public function test_create_returns_null_when_post_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$result = $this->factory->create( 123 );

		$this->assertNull( $result );
	}

	/**
	 * Test create returns null when post is wrong type.
	 */
	public function test_create_returns_null_when_wrong_post_type(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $post );

		$result = $this->factory->create( 123 );

		$this->assertNull( $result );
	}

	/**
	 * Test create returns null when transport type is empty.
	 */
	public function test_create_returns_null_when_transport_type_empty(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$result = $this->factory->create( 123 );

		$this->assertNull( $result );
	}

	/**
	 * Test create returns null when transport type is unknown.
	 */
	public function test_create_returns_null_when_transport_type_unknown(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 123, 'syn_transport_type', true )
			->andReturn( 'UNKNOWN_TYPE' );

		$result = $this->factory->create( 123 );

		$this->assertNull( $result );
	}

	/**
	 * Test get_available_transports returns expected transports.
	 */
	public function test_get_available_transports(): void {
		$transports = $this->factory->get_available_transports();

		$this->assertArrayHasKey( 'WP_XMLRPC', $transports );
		$this->assertArrayHasKey( 'WP_REST', $transports );
		$this->assertArrayHasKey( 'WP_RSS', $transports );

		$this->assertEquals( 'WordPress XML-RPC', $transports['WP_XMLRPC']['name'] );
		$this->assertEquals( 'WordPress.com REST', $transports['WP_REST']['name'] );
		$this->assertEquals( 'RSS Feed', $transports['WP_RSS']['name'] );
	}

	/**
	 * Test supports_push returns correct values.
	 */
	public function test_supports_push(): void {
		$this->assertTrue( $this->factory->supports_push( 'WP_XMLRPC' ) );
		$this->assertTrue( $this->factory->supports_push( 'WP_REST' ) );
		$this->assertFalse( $this->factory->supports_push( 'WP_RSS' ) );
		$this->assertFalse( $this->factory->supports_push( 'UNKNOWN' ) );
	}

	/**
	 * Test supports_pull returns correct values.
	 */
	public function test_supports_pull(): void {
		$this->assertTrue( $this->factory->supports_pull( 'WP_XMLRPC' ) );
		$this->assertFalse( $this->factory->supports_pull( 'WP_REST' ) );
		$this->assertTrue( $this->factory->supports_pull( 'WP_RSS' ) );
		$this->assertFalse( $this->factory->supports_pull( 'UNKNOWN' ) );
	}

	/**
	 * Test create_push_transport returns null for pull-only transport.
	 */
	public function test_create_push_transport_returns_null_for_pull_only(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_transport_type'          => 'WP_RSS',
					'syn_feed_url'                => 'https://example.com/feed/',
					'syn_default_post_type'       => 'post',
					'syn_default_post_status'     => 'draft',
					'syn_default_comment_status'  => 'closed',
					'syn_default_ping_status'     => 'closed',
					'syn_default_cat_status'      => 'no',
					default                       => '',
				};
			}
		);

		$result = $this->factory->create_push_transport( 123 );

		$this->assertNull( $result );
	}

	/**
	 * Test create_pull_transport returns null for push-only transport.
	 */
	public function test_create_pull_transport_returns_null_for_push_only(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_transport_type' => 'WP_REST',
					'syn_site_token'     => 'encrypted_token',
					'syn_site_id'        => '12345',
					default              => '',
				};
			}
		);

		$this->encryptor
			->shouldReceive( 'decrypt' )
			->with( 'encrypted_token' )
			->andReturn( 'decrypted_token' );

		$result = $this->factory->create_pull_transport( 123 );

		$this->assertNull( $result );
	}

	/**
	 * Test create_xmlrpc_transport returns null when credentials missing.
	 */
	public function test_create_xmlrpc_returns_null_when_missing_url(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_transport_type'  => 'WP_XMLRPC',
					'syn_site_url'        => '',
					'syn_site_username'   => 'user',
					'syn_site_password'   => 'encrypted',
					default               => '',
				};
			}
		);

		$this->encryptor
			->shouldReceive( 'decrypt' )
			->andReturn( 'decrypted' );

		$result = $this->factory->create( 123 );

		$this->assertNull( $result );
	}

	/**
	 * Test create_rest_transport returns null when token missing.
	 */
	public function test_create_rest_returns_null_when_missing_token(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_transport_type' => 'WP_REST',
					'syn_site_token'     => '',
					'syn_site_id'        => '12345',
					default              => '',
				};
			}
		);

		$this->encryptor
			->shouldReceive( 'decrypt' )
			->with( '' )
			->andReturn( '' );

		$result = $this->factory->create( 123 );

		$this->assertNull( $result );
	}

	/**
	 * Test create_rss_transport returns null when feed URL missing.
	 */
	public function test_create_rss_returns_null_when_missing_feed_url(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_transport_type' => 'WP_RSS',
					'syn_feed_url'       => '',
					default              => '',
				};
			}
		);

		$result = $this->factory->create( 123 );

		$this->assertNull( $result );
	}
}
