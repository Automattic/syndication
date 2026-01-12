<?php
/**
 * Unit tests for AbstractPushTransport.
 *
 * @package Automattic\Syndication\Tests\Unit\Infrastructure\Transport
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Infrastructure\Transport;

use Automattic\Syndication\Infrastructure\Transport\AbstractPushTransport;
use Automattic\Syndication\Tests\Unit\TestCase;
use Brain\Monkey\Functions;
use Mockery;
use WP_Error;
use WP_Post;

/**
 * Test case for AbstractPushTransport.
 *
 * @group unit
 * @covers \Automattic\Syndication\Infrastructure\Transport\AbstractPushTransport
 */
class AbstractPushTransportTest extends TestCase {

	/**
	 * Test transport instance.
	 *
	 * @var TestPushTransport
	 */
	private TestPushTransport $transport;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->transport = new TestPushTransport( 123, 30 );
	}

	/**
	 * Test push returns error when post not found.
	 */
	public function test_push_returns_error_when_post_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$result = $this->transport->push( 999 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_post', $result->get_error_code() );
	}

	/**
	 * Test push prepares post data and calls do_push.
	 */
	public function test_push_calls_do_push_with_prepared_data(): void {
		$post              = Mockery::mock( WP_Post::class );
		$post->ID          = 42;
		$post->post_title  = 'Test Post';
		$post->post_content = 'Test content';
		$post->post_excerpt = 'Test excerpt';
		$post->post_status  = 'publish';
		$post->post_password = '';
		$post->post_date     = '2024-01-15 10:00:00';
		$post->post_date_gmt = '2024-01-15 10:00:00';
		$post->post_type     = 'post';

		Functions\when( 'get_post' )->justReturn( $post );

		$result = $this->transport->push( 42 );

		$this->assertEquals( 100, $result ); // TestPushTransport returns 100.
		$this->assertEquals( 42, $this->transport->last_push_post_id );
		$this->assertEquals( 'Test Post', $this->transport->last_push_data['post_title'] );
	}

	/**
	 * Test push returns true when filtered out.
	 */
	public function test_push_returns_true_when_filtered_out(): void {
		$post              = Mockery::mock( WP_Post::class );
		$post->ID          = 42;
		$post->post_title  = 'Test Post';
		$post->post_content = 'Test content';
		$post->post_excerpt = '';
		$post->post_status  = 'publish';
		$post->post_password = '';
		$post->post_date     = '2024-01-15 10:00:00';
		$post->post_date_gmt = '2024-01-15 10:00:00';
		$post->post_type     = 'post';

		Functions\when( 'get_post' )->justReturn( $post );

		// Set transport to filter out posts.
		$this->transport->filter_out_push = true;

		$result = $this->transport->push( 42 );

		$this->assertTrue( $result );
	}

	/**
	 * Test update returns error when post not found.
	 */
	public function test_update_returns_error_when_post_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$result = $this->transport->update( 999, 888 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_post', $result->get_error_code() );
	}

	/**
	 * Test update calls do_update with correct parameters.
	 */
	public function test_update_calls_do_update_with_remote_id(): void {
		$post              = Mockery::mock( WP_Post::class );
		$post->ID          = 42;
		$post->post_title  = 'Updated Post';
		$post->post_content = 'Updated content';
		$post->post_excerpt = '';
		$post->post_status  = 'publish';
		$post->post_password = '';
		$post->post_date     = '2024-01-15 10:00:00';
		$post->post_date_gmt = '2024-01-15 10:00:00';
		$post->post_type     = 'post';

		Functions\when( 'get_post' )->justReturn( $post );

		$result = $this->transport->update( 42, 888 );

		$this->assertEquals( 42, $result );
		$this->assertEquals( 888, $this->transport->last_update_remote_id );
	}

	/**
	 * Test delete calls do_delete.
	 */
	public function test_delete_calls_do_delete(): void {
		$result = $this->transport->delete( 555 );

		$this->assertTrue( $result );
		$this->assertEquals( 555, $this->transport->last_delete_remote_id );
	}

	/**
	 * Test prepared post data contains expected fields.
	 */
	public function test_prepare_post_data_contains_expected_fields(): void {
		$post              = Mockery::mock( WP_Post::class );
		$post->ID          = 42;
		$post->post_title  = 'My Title';
		$post->post_content = 'My Content';
		$post->post_excerpt = 'My Excerpt';
		$post->post_status  = 'draft';
		$post->post_password = 'secret';
		$post->post_date     = '2024-01-15 10:00:00';
		$post->post_date_gmt = '2024-01-15 15:00:00';
		$post->post_type     = 'page';

		Functions\when( 'get_post' )->justReturn( $post );

		$this->transport->push( 42 );

		$data = $this->transport->last_push_data;

		$this->assertEquals( 'My Title', $data['post_title'] );
		$this->assertEquals( 'My Content', $data['post_content'] );
		$this->assertEquals( 'My Excerpt', $data['post_excerpt'] );
		$this->assertEquals( 'draft', $data['post_status'] );
		$this->assertEquals( 'secret', $data['post_password'] );
		$this->assertEquals( '2024-01-15 10:00:00', $data['post_date'] );
		$this->assertEquals( '2024-01-15 15:00:00', $data['post_date_gmt'] );
		$this->assertEquals( 'page', $data['post_type'] );
		$this->assertEquals( 42, $data['post_id'] );
	}
}

/**
 * Concrete test implementation of AbstractPushTransport.
 */
class TestPushTransport extends AbstractPushTransport {

	/**
	 * Whether to filter out push operations.
	 *
	 * @var bool
	 */
	public bool $filter_out_push = false;

	/**
	 * Whether to filter out update operations.
	 *
	 * @var bool
	 */
	public bool $filter_out_update = false;

	/**
	 * Last push data received.
	 *
	 * @var array<string, mixed>
	 */
	public array $last_push_data = array();

	/**
	 * Last push post ID.
	 *
	 * @var int
	 */
	public int $last_push_post_id = 0;

	/**
	 * Last update remote ID.
	 *
	 * @var int
	 */
	public int $last_update_remote_id = 0;

	/**
	 * Last delete remote ID.
	 *
	 * @var int
	 */
	public int $last_delete_remote_id = 0;

	/**
	 * Get client data.
	 *
	 * @return array{id: string, modes: array<string>, name: string}
	 */
	public static function get_client_data(): array {
		return array(
			'id'    => 'TEST',
			'modes' => array( 'push' ),
			'name'  => 'Test Transport',
		);
	}

	/**
	 * Test connection.
	 *
	 * @return bool
	 */
	public function test_connection(): bool {
		return true;
	}

	/**
	 * Check if post exists.
	 *
	 * @param int $remote_id Remote post ID.
	 * @return bool
	 */
	public function is_post_exists( int $remote_id ): bool {
		return true;
	}

	/**
	 * Apply pre-push filter.
	 *
	 * @param array<string, mixed> $post_data Post data.
	 * @param int                  $post_id   Post ID.
	 * @return array<string, mixed>|false
	 */
	protected function apply_pre_push_filter( array $post_data, int $post_id ): array|false {
		if ( $this->filter_out_push ) {
			return false;
		}
		return $post_data;
	}

	/**
	 * Apply pre-update filter.
	 *
	 * @param array<string, mixed> $post_data Post data.
	 * @param int                  $post_id   Post ID.
	 * @return array<string, mixed>|false
	 */
	protected function apply_pre_update_filter( array $post_data, int $post_id ): array|false {
		if ( $this->filter_out_update ) {
			return false;
		}
		return $post_data;
	}

	/**
	 * Perform push.
	 *
	 * @param array<string, mixed> $post_data Post data.
	 * @param int                  $post_id   Post ID.
	 * @return int|WP_Error
	 */
	protected function do_push( array $post_data, int $post_id ): int|WP_Error {
		$this->last_push_data    = $post_data;
		$this->last_push_post_id = $post_id;
		return 100; // Simulated remote ID.
	}

	/**
	 * Perform update.
	 *
	 * @param array<string, mixed> $post_data Post data.
	 * @param int                  $post_id   Post ID.
	 * @param int                  $remote_id Remote ID.
	 * @return int|WP_Error
	 */
	protected function do_update( array $post_data, int $post_id, int $remote_id ): int|WP_Error {
		$this->last_update_remote_id = $remote_id;
		return $post_id;
	}

	/**
	 * Perform delete.
	 *
	 * @param int $remote_id Remote ID.
	 * @return bool|WP_Error
	 */
	protected function do_delete( int $remote_id ): bool|WP_Error {
		$this->last_delete_remote_id = $remote_id;
		return true;
	}
}
