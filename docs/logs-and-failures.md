# Logs and failures

Syndication records what happens to each site in a log, shows the logs under **Sites → Logs**, and can retry or disable a feed that keeps failing.

## The Logs screen

**Sites → Logs** lists log entries for every site, newest first, 20 to a page (change this under **Screen Options**). It needs the syndication capability, `manage_options` by default.

Each entry shows:

- **Object ID:** the ID of the site the entry belongs to. It's the number in the site's edit link under **Sites**.
- **Log ID:** an ID shared by every entry from the same request, so you can follow one pull or push through the list.
- **Time:** in the site's timezone.
- **Type:** success, error or information.
- **Status:** a short code, such as `new`, `update`, `delete`, `start_import` or `invalid_post_type`.
- **Message:** what happened.

You can filter by log ID, month and type, and search the messages. The search matches plain text, ignoring case.

The screen shows the logs of up to 100 sites, fewer if some sites' logs haven't yet been moved to their 2.2.3 storage.

### What's logged

- **Pushes:** each post created, updated or deleted on each site, or the error if it failed.
- **Pulls:**
  - **The run:** the start of each site's pull, with the number of items in the feed, and feeds with no items.
  - **Posts:** each post created or updated.
  - **Skipped items:** items with no unique ID, items of a post type that can't be pulled, items skipped because **Update pulled posts** is off, and items skipped by a filter.
  - **Errors:** feeds that couldn't be fetched or parsed, XPath expressions that aren't valid, and XML feeds where the post root matched nothing.
- **Failure handling:** each automatic retry, and sites disabled after repeated failures.
- **Loop prevention:** when a check for a post's source site had to stop early (`loop_guard_incomplete`). See [Pushing posts](pushing.md#avoiding-loops).

Information entries are only kept when `WP_DEBUG` is on, or when the [`syn_pre_debug_level`](hooks.md#syn_pre_debug_level) filter returns `info`. They include the start of each pull, feeds with no items, items skipped because of **Update pulled posts** or a filter, and automatic retries. Errors and successes are always kept, including items skipped for having no unique ID or a post type that can't be pulled.

### How long entries are kept

Each site keeps its latest 150 entries; older ones are dropped as new ones arrive. Permanently deleting a site deletes its log. Entries are stored on the site, in its `_syn_log` custom field.

### Sending entries to the PHP error log

When `WP_DEBUG` is on, each entry is also written to the PHP error log, as a line starting `SYN_ERROR:`, `SYN_SUCCESS:` or `SYN_INFO:`. To control this regardless of `WP_DEBUG`, return `true` or `false` from [`syn_use_php_error_logging`](hooks.md#syn_use_php_error_logging). To change the line's format, use [`syn_error_log_message_format`](hooks.md#syn_error_log_message_format).

## Failed pushes

When a site can't be reached, or refuses a push, the error is logged and the post's copy on that site is marked as failed. The next push of that post tries again; there's no automatic retry. Failed pushes don't count towards disabling a site.

To push again straight away, save the post, or run [`wp syndication push_post`](wp-cli.md#push_post).

## Failing feeds

A pull counts as failed when an RSS or XML feed can't be fetched or can't be parsed. A feed that parses but has no items doesn't count as a failure, nor does an XML feed whose post root matches nothing or whose mapping has an XPath that isn't valid; those two are logged as errors.

### Disabling failing sites

On **Settings → Push Syndication**, **Maximum pull attempts** sets how many pulls in a row can fail before the site is disabled. The default, 0, never disables a site. The highest you can set is 100, which the [`push_syndication_max_pull_attempts_upper_limit`](hooks.md#push_syndication_max_pull_attempts_upper_limit) filter changes.

Only failures in a row count: a successful pull resets the count. Failed automatic retries count too, so with a low limit a short outage can disable a site within minutes: with **Maximum pull attempts** at 3, a feed that's down for three minutes is disabled.

When a site reaches the limit:

- it's set to **Disabled**, so it's no longer pulled;
- an error is logged;
- users with the syndication capability see an admin notice, "Site N disabled after N pull failure(s).", on every admin screen until someone chooses **Hide Notice**. Hiding it hides it for everyone.

Fix the feed or the site's settings, then edit the site and set its status back to **Enabled**.

### Automatic retries

When **Maximum pull attempts** is above 0 and a pull fails, the plugin doesn't wait for the next scheduled pull. It retries a minute later, up to three times, while the site is below **Maximum pull attempts**. A successful retry clears the count of retries. If the retries are used up, the site waits for its next scheduled pull.

The [`pull_syndication_failure_auto_retry_limit`](hooks.md#pull_syndication_failure_auto_retry_limit) and [`syndication_failure_auto_retry_interval`](hooks.md#syndication_failure_auto_retry_interval) filters change the number of retries and when they run.

## Troubleshooting

**Nothing is pushed.**

- Check that the post type is ticked under **Post Types** on **Settings → Push Syndication**.
- Check that the post has sitegroups ticked in its **Syndicate** box.
- Check that the sites in those sitegroups are enabled push sites.
- Pushes run in WP-Cron, so check that WP-Cron is running: with `DISABLE_WP_CRON` set, a system cron job must call it.

**A feed is never pulled.**

- Check that its sitegroup is ticked under **Site Groups** on **Settings → Push Syndication**.
- Check that the site is enabled.
- Check that WP-Cron is running.
- Run [`wp syndication pull_site`](wp-cli.md#pull_site) to pull it now and see what happens.

**Pulled posts aren't updated when the feed changes.** Tick **Update pulled posts** under **Pull Options**.

**A site keeps being disabled.** Look at its entries on the Logs screen for the error, and check its feed URL with the site's connection test by saving the site.
