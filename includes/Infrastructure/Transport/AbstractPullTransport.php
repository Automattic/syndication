<?php
/**
 * Abstract pull transport base class.
 *
 * @package Automattic\Syndication\Infrastructure\Transport
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Transport;

use Automattic\Syndication\Domain\Contracts\PullTransportInterface;

/**
 * Abstract base class for pull transport implementations.
 *
 * Provides common functionality for pulling content from remote sites,
 * including filter hook handling and post data normalisation.
 */
abstract class AbstractPullTransport implements PullTransportInterface {

	/**
	 * The site post ID.
	 *
	 * @var int
	 */
	protected readonly int $site_id;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	protected readonly int $timeout;

	/**
	 * User agent string.
	 *
	 * @var string
	 */
	protected readonly string $user_agent;

	/**
	 * Constructor.
	 *
	 * @param int $site_id The site post ID.
	 * @param int $timeout Request timeout in seconds.
	 */
	public function __construct( int $site_id, int $timeout = 45 ) {
		$this->site_id    = $site_id;
		$this->timeout    = $timeout;
		$this->user_agent = 'push-syndication-plugin';
	}

	/**
	 * Pull posts from the remote site.
	 *
	 * @param array<string, mixed> $args Optional arguments for filtering posts.
	 * @return array<int, array<string, mixed>> Array of post data from the remote site.
	 */
	public function pull( array $args = array() ): array {
		$args = $this->apply_pull_args_filter( $args );

		$posts = $this->do_pull( $args );

		$posts = $this->normalise_posts( $posts );

		return $this->apply_pulled_posts_filter( $posts );
	}

	/**
	 * Retrieve a single post from the remote site.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return array<string, mixed>|null Post data array or null if not found.
	 */
	public function get_post( int $remote_id ): ?array {
		$post = $this->do_get_post( $remote_id );

		if ( null === $post ) {
			return null;
		}

		return $this->normalise_post( $post );
	}

	/**
	 * Normalise multiple posts to consistent format.
	 *
	 * @param array<int, array<string, mixed>> $posts Raw posts from remote.
	 * @return array<int, array<string, mixed>> Normalised posts.
	 */
	protected function normalise_posts( array $posts ): array {
		return array_map( array( $this, 'normalise_post' ), $posts );
	}

	/**
	 * Normalise a single post to consistent format.
	 *
	 * Ensures all required fields exist with sensible defaults.
	 *
	 * @param array<string, mixed> $post Raw post data.
	 * @return array<string, mixed> Normalised post data.
	 */
	protected function normalise_post( array $post ): array {
		$defaults = array(
			'post_title'    => '',
			'post_content'  => '',
			'post_excerpt'  => '',
			'post_status'   => 'draft',
			'post_date'     => '',
			'post_date_gmt' => '',
			'post_type'     => 'post',
			'remote_id'     => 0,
			'post_guid'     => '',
		);

		return array_merge( $defaults, $post );
	}

	/**
	 * Apply filter to pull arguments.
	 *
	 * Override in subclasses to provide transport-specific filter names.
	 *
	 * @param array<string, mixed> $args The pull arguments.
	 * @return array<string, mixed> Filtered arguments.
	 */
	protected function apply_pull_args_filter( array $args ): array {
		return $args;
	}

	/**
	 * Apply filter to pulled posts.
	 *
	 * Override in subclasses to provide transport-specific filter names.
	 *
	 * @param array<int, array<string, mixed>> $posts The pulled posts.
	 * @return array<int, array<string, mixed>> Filtered posts.
	 */
	protected function apply_pulled_posts_filter( array $posts ): array {
		return $posts;
	}

	/**
	 * Perform the actual pull operation.
	 *
	 * @param array<string, mixed> $args Arguments for filtering posts.
	 * @return array<int, array<string, mixed>> Array of raw post data.
	 */
	abstract protected function do_pull( array $args ): array;

	/**
	 * Perform the actual single post retrieval.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return array<string, mixed>|null Raw post data or null if not found.
	 */
	abstract protected function do_get_post( int $remote_id ): ?array;
}
