# Syndication

Stable tag: 2.2.0
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: syndication, xmlrpc, rest-api, content-distribution, multisite
Contributors: automattic, garyj, nprasath002, batmoo, betzster, nickdaugherty

Syndicate content to and from your WordPress sites. Push posts to multiple destinations or pull content from external feeds with a single click.

## Description

Syndication helps users manage posts across multiple sites. It's useful when managing posts on different platforms. With a single click you can push a post to more than 100 sites.

### Features

* **Push syndication** - Publish content to multiple WordPress sites simultaneously
* **Pull syndication** - Import content from RSS/XML feeds automatically
* **Multiple transport methods** - WordPress.com REST API, XML-RPC, and RSS/XML feeds
* **Site groups** - Organise destination sites into groups for easier management
* **Scheduled syndication** - Content syncs automatically when posts are published
* **WP-CLI support** - Manage syndication from the command line

## Installation

1. Upload the `syndication` folder to `/wp-content/plugins/` or install via the WordPress admin.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Visit **Settings > Push Syndication** to configure the plugin.
4. When editing a post, use the "Syndication" metabox to select destination sites/groups.

## Usage

Here's how it works:

1. Configure which post types are "pushable", as well as whether you'd like syndicated posts to be deleted when the master post is deleted.
2. Register and group your sites into "Sitegroups" in settings.
3. In the WordPress posting interface, you'll see a new "Syndication" metabox, with all of your sitegroups listed. Select the sitegroups you want to push to and the post will be automatically syndicated to your other sites when it's published and updated.

### WordPress.com REST API

To push content using the WordPress.com REST API you need to create an [application](https://developer.wordpress.com/apps/new/) from the [WordPress.com Developer Resources](https://developer.wordpress.com/) site - and you can also generate API tokens directly from the plugin's settings page.

Fill in the client ID and client secret as displayed on the app page, and click the authorize button to get directed to the authorization page on WordPress.com. Select which site you'd like to push to and click "Authorize", at which point you'll be redirected back to your settings page-which will now display the API token, Blog ID, and Blog URL. You can now use this information to register your WordPress.com site.

### Using WordPress XML-RPC

To push content using XML-RPC, you'll need to enable XML-RPC on the destination site and provide the site URL, username, and password.

Note that if you have two-factor authentication enabled on the destination site, you will need to create an application password to use when adding a new site. Using your regular password will not work.

### Pulling from RSS Feeds

Push Syndication can ingest RSS feeds into your site. It's as simple as adding a site, setting the transport type to "RSS (pull)" and entering an RSS URL and title.

To add a site, go to the WordPress admin and find the "Sites" menu item, below Settings and choose "Add New". In the settings, you'll be able to customise the post's type, status, comment settings, pingback settings and category.

### Secure Credential Storage

To store passwords securely, define an encryption key in your `wp-config.php`:

```php
define( 'PUSH_SYNDICATE_KEY', 'your-random-encryption-key-here' );
```

This key is used to encrypt credentials when saved to the database.

## Frequently Asked Questions

### What transport methods are supported?

* **WordPress.com REST API** - For sites hosted on WordPress.com or using Jetpack
* **XML-RPC** - For self-hosted WordPress sites with XML-RPC enabled
* **RSS/XML feeds** - For pulling content from any feed source

### Can I syndicate to non-WordPress sites?

The push functionality requires WordPress on the destination site. However, pull syndication can import content from any valid RSS or XML feed.

### Does it work with multisite?

Yes, Syndication works with WordPress multisite installations. You can syndicate content between subsites or to external sites.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full changelog.

## Contributing

Pull requests are welcome on [GitHub](https://github.com/Automattic/syndication).
