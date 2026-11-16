# WP-CLI commands

Syndication registers its commands under `wp syndication`. Run `wp help syndication` for the list, or `wp help syndication <command>` for one command's options.

| Command | Also available as | What it does |
| --- | --- | --- |
| [`push-all-posts`](#push-all-posts) | | Pushes every post of one post type to the site groups chosen for it. |
| [`push_post`](#push_post) | `push-post` | Pushes one post to the site groups chosen for it. |
| [`pull_site`](#pull_site) | `pull-site` | Pulls one site's feed now. |
| [`pull_sitegroup`](#pull_sitegroup) | `pull-sitegroup` | Pulls every site in one site group now. |

The underscored names are the originals and keep working; the hyphenated ones match WP-CLI's usual style.

None of the commands has a dry run. They create, update and delete posts on other sites, or on this one, so try them on a staging copy first.

The commands are only registered when the plugin is active. On a multisite network, use WP-CLI's `--url=<url>` option to choose the site. Commands run without a logged-in user unless you pass `--user=<login>`, which affects how pulled content is filtered: see [Security and credentials](security.md#pulled-content).

## Pushing

Pushes from the command line run straight away, in the command, rather than in WP-Cron. If another push is already running, the push is put back by a minute and runs in WP-Cron instead.

### push-all-posts

Pushes every post of one post type, 150 posts at a time, pausing for two seconds between batches. The [`syndication_cli_push_all_posts_pause`](hooks.md#syndication_cli_push_all_posts_pause) filter changes the pause.

```sh
wp syndication push-all-posts [--post_type=<post-type>] [--paged=<page>]
```

- `--post_type`: the post type to push. Default `post`.
- `--paged`: the batch to start from, counting from 1. Use it to resume a run that stopped part way. Default `1`.

Each post is pushed as [`push_post`](#push_post) would push it. Posts with no site groups, or none with enabled sites, are skipped with a warning, and the run carries on. On a large site that can mean many warnings.

The command doesn't filter by post status, so it uses `WP_Query`'s default: published posts, plus the private posts the `--user` can read, if you pass one.

### push_post

Pushes one post.

```sh
wp syndication push_post --post_id=<id>
```

- `--post_id`: the post to push. Required.

The post goes to every enabled site in the site groups chosen in its **Syndicate** box. A site that already has a copy gets it updated. If a site group has been unticked since the post was last pushed, the copies on that site group's sites are deleted.

The command prints how many sites it's pushing to and deleting from, then a line for each site:

```
-- Added remote post #501 for post #42 (Partner site)
-- Updated remote post #318 for post #42 (Other partner)
```

A site that refuses the push is reported as a warning, with the error. A post with no site groups, or none with enabled sites, is skipped with a warning.

## Pulling

### pull_site

Pulls one site's feed now, rather than waiting for its next scheduled pull.

```sh
wp syndication pull_site --site_id=<id>
```

- `--site_id`: the ID of the site, the number in its edit link under **Sites**. Required.

The site doesn't need to be in a site group that's pulled on a schedule, but it must be enabled: a disabled site is skipped with a warning.

The command prints the number of items in the feed, then a line for each post created or updated, and a warning for each item that couldn't be saved. New items become new posts. Items pulled before are updated only if **Update pulled posts** is ticked on **Settings → Push Syndication**.

### pull_sitegroup

Pulls every site in one site group now, including the sites in its child site groups.

```sh
wp syndication pull_sitegroup --sitegroup=<slug>
```

- `--sitegroup`: the site group's slug. Required.

As with `pull_site`, disabled sites are skipped, and the site group doesn't need to be pulled on a schedule. An unknown slug is an error, and a site group with no sites pulls nothing.
