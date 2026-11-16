# Syndication

Stable tag: 2.2.2  
Requires at least: 6.4  
Tested up to: 7.1  
Requires PHP: 7.4  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  
Tags: syndication, xmlrpc, rest-api, content-distribution, multisite  
Contributors: automattic, garyj, nprasath002, batmoo, betzster, nickdaugherty

Push posts from one WordPress site to many, and pull RSS and XML feeds in as posts.

## Description

Syndication copies posts between sites. Choose the groups of sites a post should go to, and it's pushed to every one of them, then kept up to date as you edit it. In the other direction, it pulls RSS and XML feeds into your site as posts, on a schedule.

- **Push to WordPress sites** over XML-RPC, including other sites on the same multisite network, and to WordPress.com sites over the WordPress.com REST API.
- **Pull feeds:** any RSS or Atom feed, or any XML feed, mapped onto posts with XPath.
- **Site groups:** organize sites into groups, and push a post to whole groups at once.
- **Kept up to date:** edits are pushed to the copies, scheduled posts are pushed when they publish, and copies can be deleted when the original is.
- **Loop prevention:** two sites can push to each other without posts bouncing back and forth.
- **Logs and failure handling:** every push and pull is logged under **Sites → Logs**, failed pulls are retried, and feeds that keep failing can be disabled automatically.
- **WP-CLI:** push and pull from the shell with the `wp syndication` commands.

Stored passwords and tokens are encrypted. Define `PUSH_SYNDICATE_KEY` before adding sites; see the installation steps.

### Documentation

Guides for site owners and developers live in the [`docs/` directory on GitHub](https://github.com/Automattic/syndication/blob/main/docs/README.md):

- [Setting up sites](https://github.com/Automattic/syndication/blob/main/docs/setting-up-sites.md): the four transports, connection tests and site groups
- [Pushing posts](https://github.com/Automattic/syndication/blob/main/docs/pushing.md): when posts are pushed, what's sent, updates and deletions
- [Pulling feeds](https://github.com/Automattic/syndication/blob/main/docs/pulling.md): scheduling pulls, and mapping RSS and XML feeds onto posts
- [Logs and failures](https://github.com/Automattic/syndication/blob/main/docs/logs-and-failures.md), and [security and credentials](https://github.com/Automattic/syndication/blob/main/docs/security.md)
- [WP-CLI commands](https://github.com/Automattic/syndication/blob/main/docs/wp-cli.md)
- [Customizing Syndication](https://github.com/Automattic/syndication/blob/main/docs/customizing.md) and the full list of [filters and actions](https://github.com/Automattic/syndication/blob/main/docs/hooks.md)

## Installation

1. Install and activate Syndication.
2. In `wp-config.php`, define an encryption key for the passwords and tokens the plugin stores, using a long random value. Do this before adding any sites: changing it later makes every saved password and token unreadable.

   ~~~php
   define( 'PUSH_SYNDICATE_KEY', 'a-long-random-string' );
   ~~~

3. Go to **Sites → Site Groups** and create a site group.
4. Go to **Sites → Add Site**, choose a transport, fill in its settings, tick the site group and set the status to **Enabled**. Saving tests the connection.

To push posts:

1. On **Settings → Push Syndication**, under **Post Types**, tick the post types to push.
2. When you edit a post, tick site groups in the **Syndicate** box. The post is pushed when you save it, in whatever status it has.

To pull feeds:

1. On **Settings → Push Syndication**, under **Site Groups**, tick the site groups to pull, and set the interval under **Pull Options**.
2. Choose **Pull Now**, or wait for the first scheduled pull.

Pushes and pulls run in WP-Cron, so make sure it's running.

## Frequently Asked Questions

### Which sites can I push to?

WordPress sites with XML-RPC switched on, using a username and an application password, and WordPress.com sites, using an OAuth token. Featured images and galleries need Syndication active on the receiving WordPress site. See [setting up sites](https://github.com/Automattic/syndication/blob/main/docs/setting-up-sites.md).

### Can I push to sites that aren't WordPress?

Not with the built-in transports. Developers can add their own; see [how Syndication works](https://github.com/Automattic/syndication/blob/main/docs/architecture.md#adding-a-transport). Pulling works with any RSS, Atom or XML feed.

### What's sent with a pushed post?

Over XML-RPC: the title, content, excerpt, status, password, date, post type, categories, tags, custom fields, featured image and gallery images. Over WordPress.com REST: the title, content, excerpt, status, password, date, categories and tags. Authors, slugs and other taxonomies aren't sent. See [what gets sent](https://github.com/Automattic/syndication/blob/main/docs/pushing.md#what-gets-sent).

### Why are my drafts appearing on other sites?

A post is pushed whenever it's saved with site groups ticked, in whatever status it has, so a draft becomes a draft on the other sites. Tick site groups when the post is ready, or [only push published posts](https://github.com/Automattic/syndication/blob/main/docs/customizing.md#only-push-published-posts).

### Why isn't my feed being pulled?

Scheduled pulls only cover the site groups ticked under **Site Groups** on **Settings → Push Syndication**, and only enabled sites. Check **Sites → Logs** for errors, or run `wp syndication pull-site --site_id=<id>` to pull it now.

### What happens when I delete a post?

If **Delete pushed posts** is ticked on **Settings → Push Syndication**, its copies on other sites are deleted too, when it's trashed or deleted. Otherwise they're left alone.

### Does it work with multisite?

Yes. Each site on the network has its own sites, site groups and settings. To syndicate between sites on the network, add them as WordPress XML-RPC sites.

## Screenshots

1. [The settings screen, covering pull site groups, pull scheduling, pushable post types and WordPress.com application credentials](.wordpress-org/screenshot-1.png)
2. [The Sites list, showing each destination's transport, site group and status](.wordpress-org/screenshot-2.png)
3. [Registering a standalone WordPress install over XML-RPC](.wordpress-org/screenshot-3.png)
4. [Registering a WordPress.com site over the REST API](.wordpress-org/screenshot-4.png)
5. [The Syndicate panel in the post editor, for choosing which site groups a post goes to](.wordpress-org/screenshot-5.png)

## Changelog

The highlights of recent releases are below. Every change, including older releases, is listed in [CHANGELOG.md](https://github.com/Automattic/syndication/blob/main/CHANGELOG.md).

### 2.2.3

- **Changed:** Existing XML sites keep publishing pulled posts, and "Delete pushed posts" is ticked on existing installs, so nothing changes on update. Both settings now work, so review them afterwards.
- **Changed:** Scheduled posts, and posts published by WP-CLI or code, are now pushed.
- **Changed:** A pull only updates posts pulled from the same site.
- **Changed:** The admin says "site group" instead of "sitegroup", and the Edit Site screen works with a keyboard and screen reader.
- **Fixed:** Pushes and pulls now reach every site in a site group, not just the first 100.
- **Fixed:** Password-protected posts stay protected on XML-RPC sites, and edits reach XML-RPC sites after the first push.
- **Fixed:** REST and RSS dates are correct on sites outside UTC, and RSS pulls fetch a fresh feed each time.
- **Fixed:** Sites are disabled only after consecutive pull failures, and pull jobs no longer pile up.
- **Fixed:** Many smaller problems with XML pulls, the Logs screen, WP-CLI output and WordPress.com authorization.

### 2.2.2

- **Fixed:** PHP warnings logged when a post is pushed or deleted.
- **Fixed:** Push deletions are now recorded in the site's syndication log.

### 2.2.1

- **Security:** Hardened the XML-RPC thumbnail methods, the log viewer, site and plugin settings, stored credentials, WordPress.com API requests and XML feed parsing.
- **Changed:** XML-RPC thumbnails are fetched with `wp_safe_remote_get()`, so thumbnails from a source on a private address need that host allowed through the `http_request_host_is_external` filter.
- **Fixed:** Filtering and pagination on the Syndication Logs screen.

## Upgrade Notice

### 2.2.3

Fixes many push and pull problems. Existing XML sites keep publishing pulled posts and "Delete pushed posts" is ticked, to keep current behavior; review both after updating. Admin text now says "site group".

### 2.2.2

Fixes PHP warnings when pushing or deleting syndicated posts, and records push deletions in the site's syndication log.

### 2.2.1

Security release. If you syndicate from a host on a private address, thumbnails will stop until you allow that host through the http_request_host_is_external filter.

## Contributing

Bug reports and pull requests are welcome on [GitHub](https://github.com/Automattic/syndication). See the [contributing guide](https://github.com/Automattic/syndication/blob/main/CONTRIBUTING.md) to get started.

## Support

- [GitHub issues](https://github.com/Automattic/syndication/issues), for bug reports and feature requests.
- [WordPress VIP Support](https://wpvip.com/wordpress-vip-enterprise-support/), for WordPress VIP customers.

## License

GPLv2 or later. See [LICENSE](https://github.com/Automattic/syndication/blob/main/LICENSE) for details.
