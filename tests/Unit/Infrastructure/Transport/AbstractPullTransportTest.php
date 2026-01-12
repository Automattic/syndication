<?php
/**
 * Unit tests for AbstractPullTransport.
 *
 * @package Automattic\Syndication\Tests\Unit\Infrastructure\Transport
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Infrastructure\Transport;

use Automattic\Syndication\Infrastructure\Transport\AbstractPullTransport;
use Automattic\Syndication\Tests\Unit\TestCase;

/**
 * Test case for AbstractPullTransport.
 *
 * @group unit
 * @covers \Automattic\Syndication\Infrastructure\Transport\AbstractPullTransport
 */
class AbstractPullTransportTest extends TestCase {

	/**
	 * Test transport instance.
	 *
	 * @var TestPullTransport
	 */
	private TestPullTransport $transport;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->transport = new TestPullTransport( 123, 30 );
	}

	/**
	 * Test pull returns normalised posts.
	 */
	public function test_pull_returns_normalised_posts(): void {
		$this->transport->posts_to_return = array(
			array(
				'post_title'   => 'Remote Post 1',
				'post_content' => 'Content 1',
				'remote_id'    => 101,
			),
			array(
				'post_title'   => 'Remote Post 2',
				'post_content' => 'Content 2',
				'remote_id'    => 102,
			),
		);

		$result = $this->transport->pull();

		$this->assertCount( 2, $result );
		$this->assertEquals( 'Remote Post 1', $result[0]['post_title'] );
		$this->assertEquals( 'Remote Post 2', $result[1]['post_title'] );
	}

	/**
	 * Test pull adds default fields to posts.
	 */
	public function test_pull_adds_default_fields(): void {
		$this->transport->posts_to_return = array(
			array(
				'post_title' => 'Minimal Post',
			),
		);

		$result = $this->transport->pull();

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Minimal Post', $result[0]['post_title'] );
		$this->assertEquals( '', $result[0]['post_content'] );
		$this->assertEquals( '', $result[0]['post_excerpt'] );
		$this->assertEquals( 'draft', $result[0]['post_status'] );
		$this->assertEquals( 'post', $result[0]['post_type'] );
		$this->assertEquals( 0, $result[0]['remote_id'] );
		$this->assertEquals( '', $result[0]['post_guid'] );
	}

	/**
	 * Test pull returns empty array when no posts.
	 */
	public function test_pull_returns_empty_array_when_no_posts(): void {
		$this->transport->posts_to_return = array();

		$result = $this->transport->pull();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test pull passes arguments to do_pull.
	 */
	public function test_pull_passes_arguments_to_do_pull(): void {
		$args = array(
			'limit'  => 10,
			'offset' => 5,
		);

		$this->transport->pull( $args );

		$this->assertEquals( $args, $this->transport->last_pull_args );
	}

	/**
	 * Test get_post returns normalised post data.
	 */
	public function test_get_post_returns_normalised_data(): void {
		$this->transport->post_to_return = array(
			'post_title'   => 'Single Post',
			'post_content' => 'Single content',
			'remote_id'    => 999,
		);

		$result = $this->transport->get_post( 999 );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Single Post', $result['post_title'] );
		$this->assertEquals( 'Single content', $result['post_content'] );
		$this->assertEquals( 'draft', $result['post_status'] );
	}

	/**
	 * Test get_post returns null when not found.
	 */
	public function test_get_post_returns_null_when_not_found(): void {
		$this->transport->post_to_return = null;

		$result = $this->transport->get_post( 404 );

		$this->assertNull( $result );
	}

	/**
	 * Test pull applies args filter.
	 */
	public function test_pull_applies_args_filter(): void {
		$this->transport->modify_args = true;

		$this->transport->pull( array( 'original' => true ) );

		$this->assertEquals( 'modified', $this->transport->last_pull_args['filtered'] );
	}

	/**
	 * Test pull applies posts filter.
	 */
	public function test_pull_applies_posts_filter(): void {
		$this->transport->posts_to_return = array(
			array( 'post_title' => 'Original' ),
		);
		$this->transport->modify_posts = true;

		$result = $this->transport->pull();

		$this->assertEquals( 'Filtered', $result[0]['post_title'] );
	}
}

/**
 * Concrete test implementation of AbstractPullTransport.
 */
class TestPullTransport extends AbstractPullTransport {

	/**
	 * Posts to return from do_pull.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $posts_to_return = array();

	/**
	 * Post to return from do_get_post.
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $post_to_return = null;

	/**
	 * Last pull arguments received.
	 *
	 * @var array<string, mixed>
	 */
	public array $last_pull_args = array();

	/**
	 * Whether to modify args in filter.
	 *
	 * @var bool
	 */
	public bool $modify_args = false;

	/**
	 * Whether to modify posts in filter.
	 *
	 * @var bool
	 */
	public bool $modify_posts = false;

	/**
	 * Get client data.
	 *
	 * @return array{id: string, modes: array<string>, name: string}
	 */
	public static function get_client_data(): array {
		return array(
			'id'    => 'TEST_PULL',
			'modes' => array( 'pull' ),
			'name'  => 'Test Pull Transport',
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
		return null !== $this->post_to_return;
	}

	/**
	 * Apply pull args filter.
	 *
	 * @param array<string, mixed> $args The pull arguments.
	 * @return array<string, mixed>
	 */
	protected function apply_pull_args_filter( array $args ): array {
		if ( $this->modify_args ) {
			$args['filtered'] = 'modified';
		}
		return $args;
	}

	/**
	 * Apply pulled posts filter.
	 *
	 * @param array<int, array<string, mixed>> $posts The pulled posts.
	 * @return array<int, array<string, mixed>>
	 */
	protected function apply_pulled_posts_filter( array $posts ): array {
		if ( $this->modify_posts && ! empty( $posts ) ) {
			$posts[0]['post_title'] = 'Filtered';
		}
		return $posts;
	}

	/**
	 * Perform pull.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array<int, array<string, mixed>>
	 */
	protected function do_pull( array $args ): array {
		$this->last_pull_args = $args;
		return $this->posts_to_return;
	}

	/**
	 * Perform get post.
	 *
	 * @param int $remote_id Remote ID.
	 * @return array<string, mixed>|null
	 */
	protected function do_get_post( int $remote_id ): ?array {
		return $this->post_to_return;
	}
}
