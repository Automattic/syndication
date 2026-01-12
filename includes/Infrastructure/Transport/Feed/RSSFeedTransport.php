<?php
/**
 * RSS feed transport for pulling content.
 *
 * @package Automattic\Syndication\Infrastructure\Transport\Feed
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Transport\Feed;

use Automattic\Syndication\Domain\Contracts\SettingsRendererInterface;
use Automattic\Syndication\Infrastructure\Transport\AbstractPullTransport;
use SimplePie;
use SimplePie_Item;
use WP_Post;

/**
 * RSS feed transport for pulling content from RSS/Atom feeds.
 *
 * This transport uses WordPress's fetch_feed (SimplePie) for parsing feeds.
 */
final class RSSFeedTransport extends AbstractPullTransport implements SettingsRendererInterface {

	/**
	 * Feed URL.
	 *
	 * @var string
	 */
	private readonly string $feed_url;

	/**
	 * Default post type.
	 *
	 * @var string
	 */
	private readonly string $default_post_type;

	/**
	 * Default post status.
	 *
	 * @var string
	 */
	private readonly string $default_post_status;

	/**
	 * Default comment status.
	 *
	 * @var string
	 */
	private readonly string $default_comment_status;

	/**
	 * Default ping status.
	 *
	 * @var string
	 */
	private readonly string $default_ping_status;

	/**
	 * Whether to import categories.
	 *
	 * @var bool
	 */
	private readonly bool $import_categories;

	/**
	 * Constructor.
	 *
	 * @param int    $site_id                The site post ID.
	 * @param string $feed_url               The feed URL.
	 * @param string $default_post_type      Default post type.
	 * @param string $default_post_status    Default post status.
	 * @param string $default_comment_status Default comment status.
	 * @param string $default_ping_status    Default ping status.
	 * @param bool   $import_categories      Whether to import categories.
	 * @param int    $timeout                Request timeout in seconds.
	 */
	public function __construct(
		int $site_id,
		string $feed_url,
		string $default_post_type = 'post',
		string $default_post_status = 'draft',
		string $default_comment_status = 'closed',
		string $default_ping_status = 'closed',
		bool $import_categories = false,
		int $timeout = 45
	) {
		parent::__construct( $site_id, $timeout );

		$this->feed_url               = $feed_url;
		$this->default_post_type      = $default_post_type;
		$this->default_post_status    = $default_post_status;
		$this->default_comment_status = $default_comment_status;
		$this->default_ping_status    = $default_ping_status;
		$this->import_categories      = $import_categories;
	}

	/**
	 * Get client metadata.
	 *
	 * @return array{id: string, modes: array<string>, name: string} Client metadata.
	 */
	public static function get_client_data(): array {
		return array(
			'id'    => 'WP_RSS',
			'modes' => array( 'pull' ),
			'name'  => 'RSS Feed',
		);
	}

	/**
	 * Test the connection by fetching the feed.
	 *
	 * @return bool True if connection is successful.
	 */
	public function test_connection(): bool {
		$feed = $this->fetch_feed();
		return ! is_wp_error( $feed );
	}

	/**
	 * Check if a post exists (not supported for RSS feeds).
	 *
	 * @param int $remote_id The remote post ID.
	 * @return bool Always false for RSS feeds.
	 */
	public function is_post_exists( int $remote_id ): bool {
		return false;
	}

	/**
	 * Apply pull args filter for backward compatibility.
	 *
	 * @param array<string, mixed> $args The pull arguments.
	 * @return array<string, mixed> Filtered arguments.
	 */
	protected function apply_pull_args_filter( array $args ): array {
		return $args;
	}

	/**
	 * Apply pulled posts filter for backward compatibility.
	 *
	 * @param array<int, array<string, mixed>> $posts The pulled posts.
	 * @return array<int, array<string, mixed>> Filtered posts.
	 */
	protected function apply_pulled_posts_filter( array $posts ): array {
		return $posts;
	}

	/**
	 * Perform the pull operation.
	 *
	 * @param array<string, mixed> $args Arguments for filtering posts.
	 * @return array<int, array<string, mixed>> Array of post data.
	 */
	protected function do_pull( array $args ): array {
		$feed = $this->fetch_feed();

		if ( is_wp_error( $feed ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
			do_action( 'push_syndication_event', 'pull_failure', $this->site_id );
			return array();
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		do_action( 'push_syndication_event', 'pull_success', $this->site_id );

		$items = $feed->get_items();
		$posts = array();

		foreach ( $items as $item ) {
			$post = $this->convert_item_to_post( $item, $args );

			if ( false === $post ) {
				continue;
			}

			$posts[] = $post;
		}

		return $posts;
	}

	/**
	 * Retrieve a single post (not supported for RSS feeds).
	 *
	 * @param int $remote_id The remote post ID.
	 * @return null Always null for RSS feeds.
	 */
	protected function do_get_post( int $remote_id ): ?array {
		return null;
	}

	/**
	 * Fetch the RSS feed.
	 *
	 * @return SimplePie|\WP_Error The SimplePie object or WP_Error on failure.
	 */
	private function fetch_feed(): SimplePie|\WP_Error {
		return fetch_feed( $this->feed_url );
	}

	/**
	 * Convert a SimplePie item to post data.
	 *
	 * @param SimplePie_Item       $item The feed item.
	 * @param array<string, mixed> $args Pull arguments.
	 * @return array<string, mixed>|false Post data array or false to skip.
	 */
	private function convert_item_to_post( SimplePie_Item $item, array $args ): array|false {
		$taxonomy = $this->import_categories ? $this->extract_taxonomy( $item ) : array(
			'cats' => array(),
			'tags' => array(),
		);

		$date = $item->get_date( 'Y-m-d H:i:s' );

		$post = array(
			'post_title'     => $item->get_title() ?? '',
			'post_content'   => $item->get_content() ?? '',
			'post_excerpt'   => $item->get_description() ?? '',
			'post_type'      => $this->default_post_type,
			'post_status'    => $this->default_post_status,
			'post_date'      => ! empty( $date ) ? $date : '',
			'comment_status' => $this->default_comment_status,
			'ping_status'    => $this->default_ping_status,
			'post_guid'      => $item->get_id() ?? '',
			'remote_id'      => 0,
			'post_category'  => $taxonomy['cats'],
			'tags_input'     => $taxonomy['tags'],
		);

		/**
		 * Filter RSS posts during pull.
		 *
		 * Return false to exclude the post.
		 *
		 * @param array<string, mixed> $post    Post data.
		 * @param array<string, mixed> $args    Pull arguments.
		 * @param SimplePie_Item       $item    The SimplePie item.
		 * @param int                  $site_id The site post ID.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		$filtered = apply_filters( 'syn_rss_pull_filter_post', $post, $args, $item, $this->site_id );

		return false === $filtered ? false : (array) $filtered;
	}

	/**
	 * Extract taxonomy terms from a feed item.
	 *
	 * @param SimplePie_Item $item The feed item.
	 * @return array{cats: array<int>, tags: array<int>} Extracted taxonomy IDs.
	 */
	private function extract_taxonomy( SimplePie_Item $item ): array {
		$ids = array(
			'cats' => array(),
			'tags' => array(),
		);

		$categories = $item->get_categories();
		if ( empty( $categories ) ) {
			return $ids;
		}

		foreach ( $categories as $cat ) {
			$term_name = $cat->get_term();
			if ( empty( $term_name ) ) {
				continue;
			}

			// Check if term exists as category.
			$result = get_term_by( 'name', $term_name, 'category' );
			if ( $result instanceof \WP_Term ) {
				$ids['cats'][] = $result->term_id;
				continue;
			}

			// Check if term exists as tag.
			$result = get_term_by( 'name', $term_name, 'post_tag' );
			if ( $result instanceof \WP_Term ) {
				$ids['tags'][] = $result->term_id;
				continue;
			}

			// Create as category if it doesn't exist.
			$result = wp_insert_term( $term_name, 'category' );
			if ( is_array( $result ) && isset( $result['term_id'] ) ) {
				$ids['cats'][] = $result['term_id'];
			}
		}

		return $ids;
	}

	/**
	 * Display settings form fields.
	 *
	 * @param WP_Post $site The site post object.
	 */
	public static function display_settings( WP_Post $site ): void {
		$feed_url               = get_post_meta( $site->ID, 'syn_feed_url', true );
		$default_post_type      = get_post_meta( $site->ID, 'syn_default_post_type', true );
		$default_post_status    = get_post_meta( $site->ID, 'syn_default_post_status', true );
		$default_comment_status = get_post_meta( $site->ID, 'syn_default_comment_status', true );
		$default_ping_status    = get_post_meta( $site->ID, 'syn_default_ping_status', true );
		$default_cat_status     = get_post_meta( $site->ID, 'syn_default_cat_status', true );

		?>
		<p>
			<label for="feed_url"><?php esc_html_e( 'Feed URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="feed_url" id="feed_url" value="<?php echo esc_attr( (string) $feed_url ); ?>" />
		</p>
		<p>
			<label for="default_post_type"><?php esc_html_e( 'Post Type', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_type" id="default_post_type">
				<?php
				$post_types = get_post_types( array(), 'names' );
				foreach ( $post_types as $post_type ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( (string) $post_type ),
						selected( $post_type, $default_post_type, false ),
						esc_html( (string) $post_type )
					);
				}
				?>
			</select>
		</p>
		<p>
			<label for="default_post_status"><?php esc_html_e( 'Post Status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_status" id="default_post_status">
				<?php
				$post_statuses = get_post_statuses();
				foreach ( $post_statuses as $key => $value ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( (string) $key ),
						selected( $key, $default_post_status, false ),
						esc_html( (string) $key )
					);
				}
				?>
			</select>
		</p>
		<p>
			<label for="default_comment_status"><?php esc_html_e( 'Comment Status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_comment_status" id="default_comment_status">
				<option value="open" <?php selected( 'open', $default_comment_status ); ?>><?php esc_html_e( 'Open', 'push-syndication' ); ?></option>
				<option value="closed" <?php selected( 'closed', $default_comment_status ); ?>><?php esc_html_e( 'Closed', 'push-syndication' ); ?></option>
			</select>
		</p>
		<p>
			<label for="default_ping_status"><?php esc_html_e( 'Ping Status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_ping_status" id="default_ping_status">
				<option value="open" <?php selected( 'open', $default_ping_status ); ?>><?php esc_html_e( 'Open', 'push-syndication' ); ?></option>
				<option value="closed" <?php selected( 'closed', $default_ping_status ); ?>><?php esc_html_e( 'Closed', 'push-syndication' ); ?></option>
			</select>
		</p>
		<p>
			<label for="default_cat_status"><?php esc_html_e( 'Category Import', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_cat_status" id="default_cat_status">
				<option value="yes" <?php selected( 'yes', $default_cat_status ); ?>><?php esc_html_e( 'Import categories', 'push-syndication' ); ?></option>
				<option value="no" <?php selected( 'no', $default_cat_status ); ?>><?php esc_html_e( 'Ignore categories', 'push-syndication' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Save settings from POST data.
	 *
	 * @param int $site_id The site post ID.
	 * @return bool True on success.
	 */
	public static function save_settings( int $site_id ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by caller.
		$feed_url = isset( $_POST['feed_url'] ) ? esc_url_raw( wp_unslash( $_POST['feed_url'] ) ) : '';
		update_post_meta( $site_id, 'syn_feed_url', $feed_url );

		$post_type = isset( $_POST['default_post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['default_post_type'] ) ) : 'post';
		update_post_meta( $site_id, 'syn_default_post_type', $post_type );

		$post_status = isset( $_POST['default_post_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_post_status'] ) ) : 'draft';
		update_post_meta( $site_id, 'syn_default_post_status', $post_status );

		$comment_status = isset( $_POST['default_comment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_comment_status'] ) ) : 'closed';
		update_post_meta( $site_id, 'syn_default_comment_status', $comment_status );

		$ping_status = isset( $_POST['default_ping_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_ping_status'] ) ) : 'closed';
		update_post_meta( $site_id, 'syn_default_ping_status', $ping_status );

		$cat_status = isset( $_POST['default_cat_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_cat_status'] ) ) : 'no';
		update_post_meta( $site_id, 'syn_default_cat_status', $cat_status );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return true;
	}
}
