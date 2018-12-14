=== Plugin Name ===
Contributors: automattic, nprasath002, batmoo, betzster, nickdaugherty
Tags: XMLRPC, WordPress.com REST
Requires at least: 3.4
Tested up to: 5.0
Stable tag: 2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Syndication helps users manage posts across multiple sites. It's useful when managing posts on different platforms. With a single click you can push a post to more than 100 sites.

Documentation: https://vip.wordpress.com/plugins/syndication/

== Installation ==
1. Install & activate the plugin through the WordPress 'Plugins' dashboard.
1. Visit Settings > Push Syndicate Settings to configure the plugin. Full documentation: https://vip.wordpress.com/plugins/syndication/
1. When editing a post, you’ll see a new “Syndication” metabox. The post will be automatically syndicated to selected sites/sitegroups on publish/update.


To store passwords securely, we recommend defining an encryption key, which will be used to encrypt credentials when saved to the database.

```
define('PUSH_SYNDICATE_KEY', 'this-is-a-random-key')
```

== Changelog ==

= 2.0 =
* Miscellaneous bug fixes and improvements

= 1.0 =
* Initial release

== Frequently Asked Questions ==

== Screenshots ==
1. Push Syndication Settings Page
2. Registering an Application
3. WordPress.com Authorization Page
4. WordPress.com API credentials
5. Registering Standalone WordPress Install
6. Registering a WordPress.com Site
7. Sitegroups Metabox
