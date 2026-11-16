# Customizing Syndication

Recipes for the most common changes. Add the snippets to a small plugin, or to your theme's `functions.php`. Every hook is listed in [Filters and actions](hooks.md).

## Let editors manage syndication

By default only administrators (users with `manage_options`) can manage sites, change the settings and choose where posts go. To let editors do it:

```php
add_filter( 'syn_syndicate_cap', fn () => 'edit_others_posts' );
```

Anyone with this capability can see and change the credentials of every site, and can add feeds that create posts. See [Security and credentials](security.md#who-can-do-what).

## Only push published posts

Posts are pushed in whatever status they have, so a draft with sitegroups ticked creates drafts on the other sites. To wait until a post is published before its first push:

```php
add_filter(
	'syn_pre_push_new_post_shortcircuit',
	function ( $skip, $post_id ) {
		return 'publish' === get_post_status( $post_id ) ? $skip : true;
	},
	10,
	2
);
```

Skipped pushes aren't recorded, so the post is pushed when it's published. Remote copies that already exist are still updated whatever the status.

## Keep custom fields to yourself

The XML-RPC transport sends every custom field except a few. To keep others back:

```php
add_filter(
	'syn_ignored_meta_fields',
	function ( array $keys ): array {
		$keys[] = 'internal_notes';
		$keys[] = 'sponsor_contract_id';
		return $keys;
	}
);
```

## Send another image field as a featured image

To send images stored in other custom fields the way featured images are sent, for example from a plugin that adds a second featured image:

```php
add_filter(
	'syn_xmlrpc_push_thumbnail_metas',
	function ( array $keys ): array {
		$keys[] = 'post_secondary-image_thumbnail_id';
		return $keys;
	}
);
```

Each field must hold an attachment ID, and Syndication must be active on the other site.

## Send more to WordPress.com

The WordPress.com REST transport sends a limited set of fields. To add more, filter the request body. This sends the post type and the featured image's address:

```php
$syn_wpcom_body = function ( array $body, int $post_id ): array {
	$body['type'] = get_post_type( $post_id );

	$image = get_the_post_thumbnail_url( $post_id, 'full' );
	if ( $image ) {
		$body['featured_image'] = $image;
	}

	return $body;
};
add_filter( 'syn_rest_push_filter_new_post_body', $syn_wpcom_body, 10, 2 );
add_filter( 'syn_rest_push_filter_edit_post_body', $syn_wpcom_body, 10, 2 );
```

Check the fields against the WordPress.com API documentation; WordPress.com ignores fields it doesn't recognize.

## Give pulled posts an author

Pulled posts have no author unless an XML feed supplies one. To credit every new pulled post to one user:

```php
add_filter(
	'syn_pull_new_post',
	function ( array $post ): array {
		$user = get_user_by( 'login', 'newsdesk' );
		if ( $user && empty( $post['post_author'] ) ) {
			$post['post_author'] = $user->ID;
		}
		return $post;
	}
);
```

## Keep a link to the original article

RSS items are imported with only their title, content, excerpt, date and categories. To store the item's link in a custom field:

```php
add_filter(
	'syn_rss_pull_filter_post',
	function ( $post, $args, $item ) {
		$post['postmeta']['source_url'] = esc_url_raw( $item->get_permalink() );
		return $post;
	},
	10,
	3
);
```

Custom field names starting with an underscore, `syn_` or `_syn_`, or containing characters outside printable ASCII, aren't saved.

## Skip some feed items

To leave out items, return `true` from `syn_pre_pull_new_post_shortcircuit`. This skips new items whose title starts with "Advertisement":

```php
add_filter(
	'syn_pre_pull_new_post_shortcircuit',
	function ( $skip, array $post ) {
		return 0 === strpos( $post['post_title'] ?? '', 'Advertisement' ) ? true : $skip;
	},
	10,
	2
);
```

For items pulled before, use `syn_pre_pull_edit_post_shortcircuit` in the same way.

## Pull into a post type without an admin screen

Pulled posts can only be posts, pages or custom post types with an admin screen. To pull into a hidden post type too:

```php
add_filter(
	'syndication_pull_post_types',
	function ( array $post_types ): array {
		$post_types[] = 'wire_item';
		return $post_types;
	}
);
```

Don't add WordPress's internal types, such as `wp_template`: feed content would then be able to change how your site looks.

## Add a token to an XML feed's address

Some feeds need a key in the address that you'd rather not store in the site's settings. The `syn_feed_url` filter applies to XML feeds only. The address, key included, appears in the site's log when the feed can't be fetched or parsed, so keep the Logs screen to trusted users:

```php
add_filter(
	'syn_feed_url',
	function ( string $url ): string {
		if ( 0 === strpos( $url, 'https://feeds.example.com/' ) ) {
			$url = add_query_arg( 'key', WIRE_FEED_KEY, $url );
		}
		return $url;
	}
);
```

## Push to a site on a private network

XML-RPC pushes refuse private network addresses. To allow one host:

```php
add_filter(
	'http_request_host_is_external',
	function ( $external, $host ) {
		return 'intranet.example.internal' === $host ? true : $external;
	},
	10,
	2
);
```

The other site needs the same filter, for the host your images come from, to receive featured images and gallery images.

## Keep more detail in the logs

Information entries, such as the start of each pull and skipped items, are only kept when `WP_DEBUG` is on. To keep them anyway:

```php
add_filter( 'syn_pre_debug_level', fn () => 'info' );
```

To stop entries being written to the PHP error log while `WP_DEBUG` is on:

```php
add_filter( 'syn_use_php_error_logging', '__return_false' );
```

## Retry failed pulls differently

When **Maximum pull attempts** is above 0, a failed pull is retried a minute later, up to three times. To retry five times, five minutes apart (retries stop once the site reaches **Maximum pull attempts**, so set that above 5):

```php
add_filter( 'pull_syndication_failure_auto_retry_limit', fn () => 5 );
add_filter( 'syndication_failure_auto_retry_interval', fn () => time() + 5 * MINUTE_IN_SECONDS );
```

The interval filter takes a timestamp, not a number of seconds.
