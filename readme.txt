=== Plugin Name ===
Contributors: automattic, nprasath002, batmoo, betzster, nickdaugherty
Tags: XMLRPC, WordPress.com REST
Requires at least: 3.4
Tested up to: 4.0
Stable tag: 2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Syndication helps users manage posts across multiple sites. It's useful when managing posts on different platforms. With a single click you can push a post to more than 100 sites.

Documentation: https://vip.wordpress.com/plugins/syndication/

== Installation ==
Enable push syndication plugin through the plugins page in the WordPress admin area. You also need to define an encryption key which will be used to encrypt user credentials and save to the database securely.

```
define('PUSH_SYNDICATION_KEY', 'this-is-a-randon-key')
```

== Changelog ==

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
