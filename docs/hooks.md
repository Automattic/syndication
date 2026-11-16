# Filters and actions

Every hook Syndication provides. For worked examples, see [Customizing Syndication](customizing.md).

Most hooks are prefixed `syn_`. Others use `push_syndication_`, `pull_syndication_` or `syndication_`; new hooks use `syndication_`.

| Hook | Type | What it controls |
| --- | --- | --- |
| **Access** | | |
| [`syn_syndicate_cap`](#syn_syndicate_cap) | Filter | Who can manage sites, settings and syndication |
| **Sites and transports** | | |
| [`syn_transports`](#syn_transports) | Filter | The transports offered for a site |
| [`syn_after_site_form`](#syn_after_site_form) | Action | Adding fields to a site's settings |
| **Pushing** | | |
| [`syn_schedule_push_content`](#syn_schedule_push_content) | Action | A post's push is being scheduled |
| [`syn_pre_push_post_sites`](#syn_pre_push_post_sites) | Filter | The sites a post is pushed to and removed from |
| [`syn_pre_push_new_post_shortcircuit`](#syn_pre_push_new_post_shortcircuit) | Filter | Skipping a post's first push to a site |
| [`syn_pre_push_edit_post_shortcircuit`](#syn_pre_push_edit_post_shortcircuit) | Filter | Skipping an update to a remote copy |
| [`syn_post_push_new_post`](#syn_post_push_new_post) | Action | A post was pushed to a site for the first time |
| [`syn_post_push_edit_post`](#syn_post_push_edit_post) | Action | A remote copy was updated |
| [`syn_schedule_delete_content`](#syn_schedule_delete_content) | Action | A post's remote copies are being scheduled for deletion |
| [`syn_pre_push_delete_post_shortcircuit`](#syn_pre_push_delete_post_shortcircuit) | Filter | Skipping the deletion of a remote copy |
| [`syn_post_push_delete_post`](#syn_post_push_delete_post) | Action | A remote copy was deleted |
| [`syndication_cli_push_all_posts_pause`](#syndication_cli_push_all_posts_pause) | Filter | The pause between batches of `wp syndication push-all-posts` |
| **WordPress XML-RPC pushes** | | |
| [`syn_xmlrpc_push_filter_new_post`](#syn_xmlrpc_push_filter_new_post) | Filter | A post before its first push, or skipping it |
| [`syn_xmlrpc_push_new_post_args`](#syn_xmlrpc_push_new_post_args) | Filter | The data sent for a new post |
| [`syn_xmlrpc_push_new_post_success`](#syn_xmlrpc_push_new_post_success) | Action | A new post was created |
| [`syn_xmlrpc_push_filter_edit_post`](#syn_xmlrpc_push_filter_edit_post) | Filter | A post before an update, or skipping it |
| [`syn_xmlrpc_push_edit_post_args`](#syn_xmlrpc_push_edit_post_args) | Filter | The data sent for an update |
| [`syn_xmlrpc_push_edit_post_success`](#syn_xmlrpc_push_edit_post_success) | Action | A remote copy was updated |
| [`syn_ignored_meta_fields`](#syn_ignored_meta_fields) | Filter | Custom fields that aren't sent |
| [`syn_xmlrpc_push_send_thumbnail`](#syn_xmlrpc_push_send_thumbnail) | Filter | Whether featured images are sent |
| [`syn_xmlrpc_push_thumbnail_metas`](#syn_xmlrpc_push_thumbnail_metas) | Filter | Which image fields are sent as featured images |
| [`syndication_xmlrpc_loop_guard_max_pages`](#syndication_xmlrpc_loop_guard_max_pages) | Filter | How far the loop check looks |
| **WordPress.com REST pushes** | | |
| [`syn_rest_push_filter_new_post`](#syn_rest_push_filter_new_post) | Filter | A post before its first push, or skipping it |
| [`syn_rest_push_filter_new_post_body`](#syn_rest_push_filter_new_post_body) | Filter | The data sent for a new post |
| [`syn_rest_push_filter_edit_post`](#syn_rest_push_filter_edit_post) | Filter | A post before an update, or skipping it |
| [`syn_rest_push_filter_edit_post_body`](#syn_rest_push_filter_edit_post_body) | Filter | The data sent for an update |
| **Pulling** | | |
| [`syn_pre_pull_posts`](#syn_pre_pull_posts) | Filter | The items read from a feed |
| [`syn_pre_find_post_by_guid`](#syn_pre_find_post_by_guid) | Filter | Matching an item to an existing post |
| [`syn_pre_pull_new_post_shortcircuit`](#syn_pre_pull_new_post_shortcircuit) | Filter | Skipping a new item |
| [`syn_pull_new_post`](#syn_pull_new_post) | Filter | A new item before it's inserted |
| [`syn_post_pull_new_post`](#syn_post_pull_new_post) | Action | A post was created from an item |
| [`syn_pre_pull_edit_post_shortcircuit`](#syn_pre_pull_edit_post_shortcircuit) | Filter | Skipping an item pulled before |
| [`syn_pull_edit_post`](#syn_pull_edit_post) | Filter | An item before it updates its post |
| [`syn_post_pull_edit_post`](#syn_post_pull_edit_post) | Action | A post was updated from an item |
| [`syndication_pull_post_types`](#syndication_pull_post_types) | Filter | The post types pulled posts can be |
| [`syn_pull_user_agent`](#syn_pull_user_agent) | Filter | The user agent for feed requests |
| [`syn_rss_pull_filter_post`](#syn_rss_pull_filter_post) | Filter | An RSS item's post data |
| [`syn_feed_url`](#syn_feed_url) | Filter | An XML feed's address |
| **Failure handling** | | |
| [`push_syndication_max_pull_attempts_upper_limit`](#push_syndication_max_pull_attempts_upper_limit) | Filter | The highest **Maximum pull attempts** allowed |
| [`pull_syndication_failure_auto_retry_limit`](#pull_syndication_failure_auto_retry_limit) | Filter | How many automatic retries |
| [`syndication_failure_auto_retry_interval`](#syndication_failure_auto_retry_interval) | Filter | When an automatic retry runs |
| [`push_syndication_event`](#push_syndication_event) | Action | Counting an event |
| [`push_syndication_trigger_event`](#push_syndication_trigger_event) | Action | Counting an event (alias) |
| [`push_syndication_reset_event`](#push_syndication_reset_event) | Action | Resetting an event's count |
| [`push_syndication_after_event`](#push_syndication_after_event) | Action | An event was counted |
| [`push_syndication_after_event_{$event}`](#push_syndication_after_event_event) | Action | A particular event was counted |
| [`push_syndication_site_disabled`](#push_syndication_site_disabled) | Action | A site was disabled after repeated failures |
| **Logging and notices** | | |
| [`syn_pre_debug_level`](#syn_pre_debug_level) | Filter | Whether information entries are kept |
| [`syn_use_php_error_logging`](#syn_use_php_error_logging) | Filter | Whether entries go to the PHP error log |
| [`syn_error_log_message_format`](#syn_error_log_message_format) | Filter | The PHP error log line |
| [`syn_message_text`](#syn_message_text) | Filter | An admin notice's text |
| [`syn_message_text_multiple`](#syn_message_text_multiple) | Filter | The text of a summary notice |
| **Lifecycle** | | |
| [`syn_after_setup_server`](#syn_after_setup_server) | Action | The plugin has loaded |
| [`syn_after_init_server`](#syn_after_init_server) | Action | The plugin has initialized on `init` |

In the descriptions below:

- `$site` is a site's `syn_site` post, as a `WP_Post`, and `$site_ID` its ID.
- `$transport_type` is the site's transport: `WP_XMLRPC`, `WP_REST`, `WP_RSS` or `WP_XML`.
- `$client` is the transport's client object, which implements `Syndication_Client`.
- `$post_ID` is the ID of the post on this site, and `$ext_ID` the ID of its copy on the other site.

## Access

### syn_syndicate_cap

Filters the capability needed to manage sites, change the settings, choose a post's site groups, read the logs and see admin notices. The `syn_site` post type's capabilities are set from it on `init`, so add the filter before then.

- `string $capability` Default `manage_options`.

## Sites and transports

### syn_transports

Filters the transports offered when editing a site. The built-in transports are found by scanning the plugin's own `includes/` directory. To add your own, see [How Syndication works](architecture.md#adding-a-transport).

- `array $transports` Transport ID mapped to `array( 'name' => string, 'modes' => string[] )`.

### syn_after_site_form

Fires at the end of a site's settings form, for every transport. Use it to add fields, and save them on `save_post_syn_site`.

- `WP_Post $site`

## Pushing

### syn_schedule_push_content

Fires when a post's push is scheduled. The plugin's own callback schedules the `syn_push_content` cron event.

- `int $post_ID`
- `array $sites` `post_ID`, `selected_sites` (sites to push to) and `removed_sites` (sites to delete the copy from), as `WP_Post` arrays.

### syn_pre_push_post_sites

Filters the sites a post is about to be pushed to, and removed from, when the push runs.

- `array $sites` As for `syn_schedule_push_content`.
- `int $post_ID`
- `array $slave_post_states` The post's push state for each site, keyed by state: `success`, `new-error`, `edit-error` and `remove-error`.

### syn_pre_push_new_post_shortcircuit

Filters whether to skip a post's first push to a site. Return `true` to skip; nothing is recorded, so the next push tries again.

- `bool $skip` Default `false`.
- `int $post_ID`, `WP_Post $site`, `string $transport_type`, `$client`
- `array $info` `state`, the post's state on this site.

### syn_pre_push_edit_post_shortcircuit

Filters whether to skip updating a remote copy. Return `true` to skip.

- `bool $skip` Default `false`.
- `int $post_ID`, `WP_Post $site`, `string $transport_type`, `$client`
- `array $info` `state` and `ext_ID`.

### syn_post_push_new_post

Fires after a post's first push to a site, whether it succeeded or not.

- `mixed $result` The remote post's ID on success, or a `WP_Error`. A client filter that skips the post makes it `true`.
- `int $post_ID`, `WP_Post $site`, `string $transport_type`, `$client`, `array $info`

### syn_post_push_edit_post

Fires after a remote copy is updated, whether it succeeded or not.

- `mixed $result` A truthy value on success (the remote post's ID over XML-RPC, this post's ID over WordPress.com REST, `true` when a client filter skipped the update), or a `WP_Error`.
- `int $post_ID`, `WP_Post $site`, `string $transport_type`, `$client`, `array $info`

### syn_schedule_delete_content

Fires when a post's remote copies are about to be scheduled for deletion, because **Delete pushed posts** is ticked and the post is being trashed or deleted. The plugin's own callback records the remote IDs and schedules the `syn_delete_content` cron event.

- `int $post_ID`

### syn_pre_push_delete_post_shortcircuit

Filters whether to skip deleting one remote copy, whether it's being deleted because the post was trashed or deleted, or because its site group was unticked. Return `true` to skip; the copy's record is kept, so a later push can still update or remove it.

- `bool $skip` Default `false`.
- `int $ext_ID`, `int $post_ID`, `int $site_ID`, `string $transport_type`, `$client`

### syn_post_push_delete_post

Fires after a remote copy is deleted, because the post was trashed or deleted or because its site group was unticked, whether it succeeded or not.

- `true|WP_Error $result`
- `int $ext_ID`, `int $post_ID`, `int $site_ID`, `string $transport_type`, `$client`

### syndication_cli_push_all_posts_pause

Filters how many seconds [`wp syndication push-all-posts`](wp-cli.md#push-all-posts) pauses between batches of 150 posts. Return `0` for no pause.

- `int $seconds` Default `2`.

Since 2.2.3.

## WordPress XML-RPC pushes

### syn_xmlrpc_push_filter_new_post

Filters a post before its first push to an XML-RPC site. Return `false` to skip the post for this site; nothing is recorded, so the next push tries again.

- `array $post` The post, as an array of `WP_Post` fields.
- `int $post_ID`

### syn_xmlrpc_push_new_post_args

Filters the data sent to `wp.newPost`.

- `array $args` `post_title`, `post_content`, `post_excerpt`, `post_status`, `post_type`, `post_password`, `post_date_gmt`, `terms_names` and `custom_fields`.
- `array $post` The post, as for `syn_xmlrpc_push_filter_new_post`.

Before 2.2.3 the password was sent as `wp_password`, which WordPress ignored. It's now `post_password`.

### syn_xmlrpc_push_new_post_success

Fires after a post is created on an XML-RPC site.

- `int $remote_post_id`
- `int $post_ID`

### syn_xmlrpc_push_filter_edit_post

Filters a post before its remote copy on an XML-RPC site is updated. Return `false` to skip the update.

- `array $post`
- `int $post_ID`

### syn_xmlrpc_push_edit_post_args

Filters the data sent to `wp.editPost`. Keys as for `syn_xmlrpc_push_new_post_args`.

- `array $args`
- `array $post`

### syn_xmlrpc_push_edit_post_success

Fires after a remote copy on an XML-RPC site is updated.

- `int $remote_post_id`
- `int $post_ID`

### syn_ignored_meta_fields

Filters the custom fields that aren't sent to XML-RPC sites. Keys starting with `syn` or `_syn` are never sent, whatever this returns.

- `string[] $keys` Default `_edit_last`, `_edit_lock` and the featured image fields.
- `int $post_ID`

### syn_xmlrpc_push_send_thumbnail

Filters whether featured images are sent to an XML-RPC site. Only `true` sends them.

- `bool $send` Default `true`.
- `int $site_ID`
- `Syndication_WP_XMLRPC_Client $client`

### syn_xmlrpc_push_thumbnail_metas

Filters the custom fields holding attachment IDs that are sent as featured images.

- `string[] $keys` Default `array( '_thumbnail_id' )`.
- `int $post_ID`

### syndication_xmlrpc_loop_guard_max_pages

Filters how many pages of 100 posts the [loop check](pushing.md#avoiding-loops) reads from an XML-RPC site before giving up and pushing anyway.

- `int $max_pages` Default `100`.
- `int $site_ID`

Since 2.2.3.

## WordPress.com REST pushes

### syn_rest_push_filter_new_post

Filters a post before its first push to a WordPress.com site. Return `false` to skip the post for this site.

- `array $post` The post, as an array of `WP_Post` fields.
- `int $post_ID`

### syn_rest_push_filter_new_post_body

Filters the data sent to the WordPress.com API for a new post. Add any field the [v1 API](https://developer.wordpress.com/docs/api/) accepts, such as `type` or `featured_image`.

- `array $body` `title`, `content`, `excerpt`, `status`, `password`, `date` (ISO 8601, UTC), `categories` and `tags` (comma-separated names).
- `int $post_ID`

### syn_rest_push_filter_edit_post

Filters a post before its remote copy on WordPress.com is updated. Return `false` to skip the update.

- `array $post`
- `int $post_ID`

### syn_rest_push_filter_edit_post_body

Filters the data sent to the WordPress.com API for an update. Keys as for `syn_rest_push_filter_new_post_body`.

- `array $body`
- `int $post_ID`

## Pulling

In the pull hooks, `$post` is the post array a feed item produced, ready for `wp_insert_post()`. As well as post fields, it has `post_guid` (the item's unique ID) and, from XML feeds, `postmeta` and `tax`.

### syn_pre_pull_posts

Filters the items read from a site's feed, before any are saved.

- `array[] $posts`
- `WP_Post $site`, `$client`

### syn_pre_find_post_by_guid

Filters the post an item updates. Return a post ID to use that post, or `false` to let the plugin look it up by the item's unique ID in `syn_post_guid`.

- `int|false $post_ID` Default `false`.
- `string $guid`, `array $post`, `WP_Post $site`

### syn_pre_pull_new_post_shortcircuit

Filters whether to skip an item that hasn't been pulled before. Return `true` to skip.

- `bool $skip` Default `false`.
- `array $post`, `WP_Post $site`, `string $transport_type`, `$client`

### syn_pull_new_post

Filters a new item before it's inserted. `ID` and `import_id` are removed afterwards, so this filter can't choose an existing post to overwrite. A post type that can't be pulled makes the item be skipped.

- `array $post`
- `WP_Post $site`, `$client`

### syn_post_pull_new_post

Fires after an item is inserted, whether it succeeded or not.

- `int|WP_Error $result` The new post's ID, or the error.
- `array $post`, `WP_Post $site`, `string $transport_type`, `$client`

### syn_pre_pull_edit_post_shortcircuit

Filters whether to skip an item that was pulled before. Return `true` to skip. Runs before the **Update pulled posts** setting is checked.

- `bool $skip` Default `false`.
- `array $post`, `WP_Post $site`, `string $transport_type`, `$client`

### syn_pull_edit_post

Filters an item before it updates its post. `$post['ID']` is the post being updated.

- `array $post`
- `WP_Post $site`, `$client`

### syn_post_pull_edit_post

Fires after an item updates its post, whether it succeeded or not.

- `int|WP_Error $result`
- `array $post`, `WP_Post $site`, `string $transport_type`, `$client`

### syndication_pull_post_types

Filters the post types pulled posts can be. Items of any other type are skipped and logged. The default is `post`, `page`, and every post type with an admin screen that isn't built into WordPress, except `syn_site`.

- `string[] $post_types`

Since 2.2.3.

### syn_pull_user_agent

Filters the user agent sent when fetching RSS and XML feeds.

- `string $user_agent` Default `WordPress/Syndication Plugin`.

### syn_rss_pull_filter_post

Filters the post data built from an RSS item. Return `false` to skip the item. Add `postmeta` (custom field name to value) or `tax` (taxonomy name to one term name, as a string) to save custom fields or a term with the post.

- `array $post`
- `array $args` The arguments passed to the client's `get_posts()`; empty when the plugin pulls.
- `SimplePie_Item $item`
- `int $site_ID`

### syn_feed_url

Filters an XML feed's address before it's fetched. It isn't applied to RSS feeds.

- `string $url`

## Failure handling

### push_syndication_max_pull_attempts_upper_limit

Filters the highest value **Maximum pull attempts** can be saved with. It only applies when the setting is saved.

- `int $limit` Default `100`.

### pull_syndication_failure_auto_retry_limit

Filters how many times a failed pull is retried automatically.

- `int $limit` Default `3`.

### syndication_failure_auto_retry_interval

Filters when an automatic retry runs. Despite the name, return a Unix timestamp, not an interval.

- `int $timestamp` Default one minute from now.

### push_syndication_event

Counts an event against an object. The plugin fires it with `pull_failure` and `pull_success` for each RSS and XML pull; you can fire your own.

- `string $event` For example `pull_failure`.
- `string|int $object_id` For the plugin's events, the site ID.

### push_syndication_trigger_event

An alias of `push_syndication_event`, with the same arguments.

### push_syndication_reset_event

Resets an event's count.

- `string $event`
- `string|int $object_id`

### push_syndication_after_event

Fires after an event is counted.

- `string $event`, `string $object_id`
- `int $count` How many times it's been counted since it was last reset.

### push_syndication_after_event_{$event}

Fires after a particular event is counted, for example `push_syndication_after_event_pull_failure`.

- `string $object_id`
- `int $count`

### push_syndication_site_disabled

Fires after a site is disabled because it reached **Maximum pull attempts**.

- `int $site_ID`
- `int $count`

## Logging and notices

### syn_pre_debug_level

Filters which entries are kept: return `info` to keep information entries as well as errors and successes. Read each time something is logged.

- `string|false $level` Default `false`, which means `info` when `WP_DEBUG` is on.

### syn_use_php_error_logging

Filters whether entries are also written to the PHP error log. Read each time something is logged.

- `bool|null $enabled` Default `null`, which follows `WP_DEBUG`. Return `true` or `false` to override it.

Before 2.2.3 the default was `false`, and returning `false` couldn't turn logging off when `WP_DEBUG` was on.

### syn_error_log_message_format

Filters the line written to the PHP error log.

- `string $line` Default `SYN_{TYPE}:{object type},{object ID},{status},{message}`.
- `string $type` `error`, `success` or `info`.
- `array $entry` The log entry: `object_type`, `object_id`, `status`, `message` and more.

### syn_message_text

Filters the text of an admin notice.

- `string $text`
- `array $notice`

### syn_message_text_multiple

Filters the text of a notice that summarizes several. The plugin's own callback returns "There have been multiple errors. Please validate your syndication logs", though the plugin doesn't currently raise summary notices itself.

- `string $text`
- `array $notice`

## Lifecycle

### syn_after_setup_server

Fires when the plugin has loaded and registered its hooks.

### syn_after_init_server

Fires on `init`, after the plugin has registered its post type and taxonomy and read its settings.
