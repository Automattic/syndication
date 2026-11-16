# Pushing posts

Pushing copies a post from this site to other sites, and keeps those copies up to date. You choose where a post goes by ticking site groups in its **Syndicate** box; the plugin pushes to every enabled push site in those site groups.

To push, you need at least one [WordPress XML-RPC or WordPress.com REST site](setting-up-sites.md#transports) in a site group.

## Choose which post types can be pushed

On **Settings → Push Syndication**, under **Post Types**, tick the post types that can be pushed. Posts are ticked by default. The **Syndicate** box appears only on the edit screens of these post types. Unticking a type hides the box, but posts of that type that already have site groups keep being pushed: untick their site groups first.

## Choose where a post goes

When you edit a post, the **Syndicate** box lists every site group. Tick the site groups the post should go to, and save. If the post was pushed to this site from another one over XML-RPC, the box also shows that site's host.

Only users with the syndication capability see the box and can change its site groups. That's `manage_options` by default; see [Security and credentials](security.md#who-can-do-what). Once a post has site groups, edits by any user who can edit the post are pushed.

## When posts are pushed

A post with site groups is pushed whenever it's saved or changes status:

- when you save it in the editor, whatever its status;
- when a scheduled post is published;
- when it's published or updated by code, for example `wp_insert_post()`, `wp_update_post()` or `wp_publish_post()`, or from WP-CLI.

Autosaves and revisions aren't pushed. Trashing is handled by the [delete setting](#deleting-posts), and restoring a post doesn't push it.

The push runs in the background, in a WP-Cron event scheduled for straight away, so it happens on the next cron run after the save rather than during it. If you run WP-Cron from a system cron job, pushes happen on that schedule.

Pushes run one at a time: a push that starts while another is running is put back by a minute. The lock that enforces this lasts five minutes, so a push that runs for longer can overlap with the next one.

To push a post now, or push many posts at once, use [WP-CLI](wp-cli.md#pushing).

### Drafts and other statuses

The post's status is sent as it is. Saving a draft with site groups ticked creates a draft on each remote site, a scheduled post creates a scheduled post, and a private post creates a private post. Tick site groups only when you're happy for the remote sites to have the post in its current status.

## Updates

The plugin remembers the ID of each remote copy, per site. Later pushes update that copy rather than creating another. If the remote copy has been deleted on the other site, the next push creates a new one.

When a remote site can't be reached or refuses a push, the error is recorded and the next push tries again. Errors appear in the [logs](logs-and-failures.md).

## Removing a post from a site group

If you untick a site group and save, the post's copies on that site group's sites are deleted on the next push, except on sites that are also in a site group that's still ticked. Removals are logged like other deletions. If a removal fails, the plugin keeps trying on later pushes until it succeeds.

Copies on disabled sites are left alone, whether you untick a site group or delete the post, and aren't deleted later if the site is enabled again.

## Deleting posts

On **Settings → Push Syndication**, **Delete pushed posts** controls what happens to remote copies when you delete a post here:

- **Ticked:** when the post is trashed, or permanently deleted without being trashed first, its remote copies are deleted too, in the background. Emptying the trash afterwards doesn't delete anything more.
- **Not ticked:** remote copies are left alone.

New installs leave it unticked. Sites upgraded from before 2.3.0 have it ticked, because before 2.3.0 trashing a post always deleted its remote copies, whatever the setting said.

What "deleted" means depends on the other site. An XML-RPC delete moves posts and pages to the trash if the other site has trash enabled, and permanently deletes other post types. WordPress.com decides for itself.

Restoring a post from the trash doesn't recreate its remote copies until it's next pushed.

## What gets sent

| | WordPress XML-RPC | WordPress.com REST |
| --- | --- | --- |
| Title, content, excerpt | Yes | Yes |
| Status | Yes | Yes |
| Password | Yes | Yes |
| Date | Yes | Yes |
| Post type | Yes; the type must exist on the other site | No; WordPress.com's default type is used |
| Categories and tags | Yes, by name; created on the other site if missing (see below) | Yes, by name |
| Other taxonomies | No | No |
| Custom fields | Yes, with exceptions (below) | No |
| Featured image | Yes, if Syndication is active on the other site | No |
| Galleries | `[gallery ids="…"]` shortcodes, if Syndication is active on the other site | No |
| Slug, author, comment and ping status, parent, menu order, sticky, format | No | No |

The remote copy's author is the account the site's credentials belong to.

Over XML-RPC, a category or tag the other site doesn't have is created there, which needs `manage_categories`. If the account can't create it, or a category name matches more than one category there, the whole push to that site fails.

### Custom fields

The XML-RPC transport sends every custom field except:

- `_edit_last`, `_edit_lock` and the featured image fields;
- any key starting with `syn` or `_syn`;
- keys removed with the [`syn_ignored_meta_fields`](hooks.md#syn_ignored_meta_fields) filter.

It also adds `syn_source_url`, holding the post's GUID on this site.

The other site only stores fields its account is allowed to add. WordPress doesn't let XML-RPC add protected fields (keys starting with an underscore), so those are dropped there. On each update, the remote copy's custom fields are replaced with the current ones.

### Featured images

The XML-RPC transport sends the address of the full-size image, and the other site downloads it. The image must be reachable from the other site, and the other site must have Syndication active. The image is sent again only when it changes. Removing a post's featured image removes it from the remote copies. Deleting a post doesn't delete its featured image on the other sites.

To send other image fields too, for example from a multiple featured images plugin, use [`syn_xmlrpc_push_thumbnail_metas`](hooks.md#syn_xmlrpc_push_thumbnail_metas). To stop sending featured images, use [`syn_xmlrpc_push_send_thumbnail`](hooks.md#syn_xmlrpc_push_send_thumbnail).

### Galleries

For each `[gallery ids="…"]` shortcode in the content, the XML-RPC transport uploads each image to the other site and rewrites the IDs in the shortcode to match. Images are uploaded again on every push. A `[gallery]` without `ids`, and galleries made with the Gallery block, are sent as they are. If an image can't be uploaded, the push fails for that site and is retried next time.

## Avoiding loops

Two sites can push to each other. To stop a post going back to the site it came from, each pushed post carries a unique ID in its `post_uniqueid` custom field. Before a post's first push to a site, the plugin checks whether that site already has a post with the same ID and type. If it does, the post came from there, and it isn't pushed back.

- **XML-RPC:** the check pages through the other site's posts of that type, 100 at a time, for up to 100 pages. If the site has more posts than that, the check stops, logs a `loop_guard_incomplete` error and pushes anyway: a possible duplicate is easier to spot and fix than a post that silently never arrives. If the other site can't be asked at all, the post is pushed too. The [`syndication_xmlrpc_loop_guard_max_pages`](hooks.md#syndication_xmlrpc_loop_guard_max_pages) filter changes the limit.

  A post that's recognised as coming from a site is checked again each time it's saved, which can mean up to 100 requests to that site per save.
- **WordPress.com REST:** the check asks WordPress.com for a post with that custom field. The REST transport doesn't send custom fields, so this only recognises posts that reached WordPress.com some other way.

## Limits

- Only categories and tags are sent. Custom taxonomies aren't.
- WordPress.com REST sends no custom fields, featured images or post type.
- A push goes site by site in one background run. With many sites, or slow ones, it can take a while.
- If a site is in two of a post's site groups, the post is pushed to it twice in the same run. The second push updates the copy the first created.
