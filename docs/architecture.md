# How Syndication works

For contributors, and for anyone extending or debugging the plugin. Using it is covered in the [other guides](README.md).

## Code layout

There's no autoloader. `push-syndication.php` loads everything from `includes/`:

| File | What it holds |
| --- | --- |
| `class-wp-push-syndication-server.php` | `WP_Push_Syndication_Server`: the post type, taxonomy, settings screen, site editing, and the push, delete and pull runs. Available as `$GLOBALS['push_syndication_server']`. |
| `interface-syndication-client.php` | `Syndication_Client`, which every transport implements. |
| `class-syndication-client-factory.php` | `Syndication_Client_Factory`, which builds a site's client from its transport ID. |
| `class-syndication-wp-xmlrpc-client.php`, `class-syndication-wp-rest-client.php`, `class-syndication-wp-rss-client.php`, `class-syndication-wp-xml-client.php` | The four transports. The XML-RPC client also registers the `syndication.*` XML-RPC methods that receive featured images and gallery images. |
| `class-syndication-logger.php`, `class-syndication-logger-viewer.php`, `class-syndication-admin-notices.php` | Logging, the Logs screen and admin notices. |
| `class-syndication-event-counter.php`, `class-syndication-site-failure-monitor.php`, `class-syndication-site-auto-retry.php` | Counting pull failures, disabling failing sites, and retrying failed pulls. |
| `class-syndication-encryption.php`, `interface-syndication-encryptor.php`, `class-syndication-encryptor-openssl.php`, `class-syndication-encryptor-mcrypt.php`, `push-syndicate-encryption.php` | Credential encryption, and the `push_syndicate_encrypt()` and `push_syndicate_decrypt()` helpers. The encryptor is available as `$GLOBALS['push_syndication_encryption']`. The MCrypt encryptor is only used below PHP 7.1, so it never runs on supported versions. |
| `class-wp-cli.php` | The `wp syndication` commands, loaded only under WP-CLI. |

## Data

### Sites and sitegroups

Each site is a `syn_site` post, which isn't public. Its title is the site's name, and its settings are custom fields on it. Sitegroups are terms in the hierarchical `syn_sitegroup` taxonomy, attached to `syn_site` posts.

Custom fields on a `syn_site` post:

| Key | Holds |
| --- | --- |
| `syn_transport_type` | The transport ID: `WP_XMLRPC`, `WP_REST`, `WP_RSS` or `WP_XML`. |
| `syn_site_enabled` | `on` when enabled; anything else is disabled. |
| `syn_site_url`, `syn_site_username`, `syn_site_password` | XML-RPC address, username and encrypted password. `syn_site_url` is also the WordPress.com blog URL. |
| `syn_site_token`, `syn_site_id` | WordPress.com encrypted token and blog ID. |
| `syn_feed_url` | RSS or XML feed address. |
| `syn_default_post_type`, `syn_default_post_status`, `syn_default_comment_status`, `syn_default_ping_status`, `syn_default_cat_status` | Feed settings. |
| `syn_node_config`, `syn_id_field`, `syn_enc_field`, `syn_enc_is_photo` | XML feed mapping. |
| `syn_last_pull_time` | Timestamp of the last pull, used to order pulls. |
| `syn_failed_auto_retry_attempts` | Automatic retries used since the last success. |
| `_syn_log`, `_syn_log_errors`, `_syn_log_errors_overall` | The site's last 150 log entries, failures since the last success, and failures overall. Before 2.2.3 these were `syn_log`, `syn_log_errors` and `syn_log_errors_overall`; the 2.2.3 upgrade renames them. |

### Pushed and pulled posts

Custom fields on posts on this site:

| Key | On | Holds |
| --- | --- | --- |
| `_syn_selected_sitegroups` | Pushed posts | Slugs of the sitegroups ticked in the **Syndicate** box. |
| `_syn_old_sitegroups` | Pushed posts | The sitegroups at the last push, to work out which were unticked. |
| `_syn_slave_post_states` | Pushed posts | The post's state on each site (below), with each remote copy's ID. |
| `post_uniqueid` | Pushed posts | A unique ID used to [avoid loops](pushing.md#avoiding-loops). XML-RPC copies it to the remote copy. |
| `_syn_push_thumb_{key}` | Pushed posts | For each site, the remote attachment ID of each featured image sent. |
| `syn_source_url` | Remote copies made over XML-RPC | The source post's GUID. |
| `syn_post_guid` | Pulled posts | The feed item's unique ID, used to find the post on later pulls. |
| `syn_source_site_id` | Pulled posts | The ID of the site it was pulled from. |

A pushed post's state on each site is one of:

- **new:** never pushed there, or its remote copy no longer exists. The next push creates a copy.
- **success:** the copy exists and is up to date.
- **new-error:** creating a copy failed. The next push tries to create it again.
- **edit-error:** updating the copy failed. The next push tries to update it again.
- **remove-error:** deleting the copy failed. The next push tries to delete it again.

### Options

| Option | Holds |
| --- | --- |
| `push_syndicate_settings` | The settings screen: pull sitegroups, push post types, delete pushed posts, pull interval, update pulled posts, WordPress.com client ID, and the encrypted client secret. |
| `push_syndication_max_pull_attempts` | **Maximum pull attempts**. |
| `syn_version` | The version the data was last upgraded to. |
| `syn_delete_error_sites` | Remote copies whose deletion failed, by site. Recorded but not shown anywhere. |
| `push_syndication_event_counter_{hash}` | Event counts, one option per event and site. |
| `syn_notices`, `syn_notice_bundles` | Admin notices waiting to be shown. |
| `syn_upgrade.lock` | Held while an upgrade runs, so only one request upgrades. |

Nothing is removed when the plugin is deleted: there's no uninstall routine.

### Transients

| Transient | Holds |
| --- | --- |
| `syn_syndicate_lock` | Set while a push runs, for up to five minutes. |
| `syn_pull_jobs_refresh_pending` | Set for two minutes after a site or sitegroup changes, so that a burst of changes reschedules the pull jobs only once. |
| `syn_rejected_meta_keys_{site ID}` | XML mapping rows refused on save, shown once on the site's settings screen. |

## Scheduled events

| Event | When | Arguments |
| --- | --- | --- |
| `syn_push_content` | Single, straight after a post with sitegroups is saved, or a minute later if a push is already running. | One array: `post_ID`, and the sites to push to and remove from as `WP_Post` objects. |
| `syn_delete_content` | Single, straight after a post is trashed or deleted, when **Delete pushed posts** is ticked. | The post ID, and the remote copies to delete, by site. |
| `syn_pull_content` | Repeating, one per site in the sitegroups pulled on a schedule, on the `syn_pull_time_interval` schedule (**Specify time interval in seconds**). Also single, for automatic retries. | The site's ID. Automatic retries, and jobs scheduled before 2.2.3, carry the whole site post instead; both work. |
| `syn_refresh_pull_jobs` | Single, a minute after a site or sitegroup changes. | None. |

Saving the settings, or `syn_refresh_pull_jobs` running, clears every `syn_pull_content` job and schedules one per site again.

## Upgrades

`WP_Push_Syndication_Server::maybe_upgrade()` runs on `init`, on every kind of request, including cron, WP-CLI and REST. When `syn_version` is older than the plugin, it takes a lock so only one request upgrades, runs the steps in `upgrade()`, and stores the new version. On multisite, each site upgrades on its own first request. The 2.2.3 steps:

- set **Delete pushed posts** on for existing installs, matching how trashing behaved before;
- set each XML site's post status to `publish`, and its comment and ping status to what its posts were getting, matching how XML pulls behaved before;
- move pull jobs to site IDs, and remove the `syn_old_pull_sites` option;
- rename the log custom fields to their protected `_syn_` names.

## Adding a transport

A transport is a class named `Syndication_{ID}_Client`, where `{ID}` is the transport ID stored in `syn_transport_type`. It implements `Syndication_Client`:

| Method | Returns |
| --- | --- |
| `__construct( $site_ID )` | |
| `static get_client_data()` | `array( 'id' => ID, 'name' => label, 'modes' => array( 'push' ) or array( 'pull' ) )`. |
| `static display_settings( $site )` | Prints the transport's fields in the site's settings. |
| `static save_settings( $site_ID )` | Saves them from `$_POST`. Return a truthy value, or the connection test is skipped. |
| `test_connection()` | `true` if the site can be reached. On `false`, the site is disabled when saved. |
| `new_post( $post_ID )` | The remote post's ID, or a `WP_Error`. Anything else isn't recorded as a push. |
| `edit_post( $post_ID, $ext_ID )` | `true` or a `WP_Error`. |
| `delete_post( $ext_ID )` | `true` or a `WP_Error`. |
| `is_post_exists( $ext_ID )` | Whether the remote copy still exists. Return `false` only when you know it's gone: `false` makes the push create a new copy. |
| `get_post( $ext_ID )` | The remote post, if your transport can fetch one. |
| `get_posts( $args = array() )` | For pull transports, an array of post arrays for `wp_insert_post()`, each with `post_type` and a unique `post_guid`. |

Only the four built-in transports are found automatically: the plugin scans its own `includes/` directory. Load your class yourself, and add it to the list with [`syn_transports`](hooks.md#syn_transports):

```php
add_action(
	'plugins_loaded',
	function () {
		// Syndication_Client must exist first, and plugins load alphabetically.
		require_once __DIR__ . '/class-syndication-acme-client.php';
	}
);

add_filter(
	'syn_transports',
	function ( array $transports ): array {
		$transports['ACME'] = array(
			'name'  => 'Acme CMS',
			'modes' => array( 'push' ),
		);
		return $transports;
	}
);
```

The loop check only runs for the built-in push transports, so a new push transport doesn't get loop prevention.

## Tests

Unit tests in `tests/Unit` run without WordPress, using Brain Monkey. Integration tests in `tests/Integration` run in WordPress through wp-env, on single site and multisite. See the [contributing guide](../CONTRIBUTING.md).
