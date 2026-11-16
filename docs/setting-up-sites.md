# Setting up sites

Every place Syndication pushes to or pulls from is a **site**. Sites live under **Sites** in the admin, and you group them into **sitegroups**. You push a post to sitegroups, not to individual sites, and you schedule pulls by sitegroup.

Managing sites needs the capability set by the `syn_syndicate_cap` filter, which is `manage_options` by default. See [Security and credentials](security.md#who-can-do-what).

## Add a site

1. Go to **Sites → Add Site** and give the site a title. The title is only for you; it's shown in the sites list and in the logs.
2. Under **Select a transport type**, choose how to connect. Changing the transport reloads the form with that transport's fields, so choose it first.
3. Fill in the transport's fields, described below.
4. In the **Site Groups** box, tick the sitegroups the site belongs to. To create a sitegroup, go to **Sites → Site Groups**.
5. In the **Site Status** box, set the status to **Enabled**, then choose **Add Site**.

When you save, the plugin tests the connection:

- If the test passes, you'll see **Connection Successful!** and the site keeps the status you chose.
- If it fails, you'll see why (for an XML-RPC site, for example, a wrong username or password, or XML-RPC switched off on the other site) and the site is saved as **Disabled**. Fix the settings and save again.

Only enabled sites are pushed to or pulled from. The **Sites** list shows each site's transport, sitegroups and status.

## Transports

| Transport | Direction | Use it for |
| --- | --- | --- |
| [WordPress XML-RPC](#wordpress-xml-rpc) | Push | Self-hosted WordPress sites, including other sites on the same multisite network. |
| [WordPress.com REST](#wordpresscom-rest) | Push | Sites hosted on WordPress.com. |
| [RSS](#rss) | Pull | Any RSS or Atom feed. |
| [XML](#xml) | Pull | Any other XML feed, mapped onto posts with XPath. |

What each push transport sends is described in [Pushing posts](pushing.md#what-gets-sent), and how each pull transport turns feed items into posts in [Pulling feeds](pulling.md).

### WordPress XML-RPC

Pushes posts to another WordPress site through its XML-RPC endpoint.

- **Enter a valid site URL:** the other site's address, such as `https://example.com`. The plugin adds `/xmlrpc.php` itself. An invalid URL isn't saved, and the site keeps its previous settings.
- **Enter Username** and **Enter Password:** an account on the other site. Use an [application password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) rather than the account's own password. If the account uses two-factor authentication, an application password is the only kind that works. The password is stored encrypted, and the field is left blank when you edit the site: leave it blank to keep the saved password.

The account needs to be able to create and publish posts of the types you push, and `manage_categories` to create categories and tags that don't exist there yet. To push password-protected posts it needs `publish_posts`, and to push featured images and galleries it needs `upload_files`.

XML-RPC must be switched on at the other site. WordPress has it on by default, but some hosts and security plugins turn it off. Featured images and galleries also need Syndication active on the other site, because they're sent through methods the plugin adds there.

The connection test logs in and asks for the site's post types, so it checks the address, the credentials and that XML-RPC is on.

Requests go through `wp_safe_remote_post()`, so a site on a private network address or an unusual port is refused unless you allow it. See [Security and credentials](security.md#outbound-requests).

### WordPress.com REST

Pushes posts to a WordPress.com site through the WordPress.com REST API (v1).

- **Enter API Token:** an OAuth access token for the site. Stored encrypted, and left blank when you edit the site: leave it blank to keep the saved token.
- **Enter Blog ID:** the WordPress.com site ID.
- **Enter a valid Blog URL:** the site's address. It's stored for reference; the API calls use the blog ID.

To get a token and blog ID:

1. Create an application at [developer.wordpress.com/apps/new](https://developer.wordpress.com/apps/new/). For its redirect URL, use the address shown under **API Token Configuration** on **Settings → Push Syndication**.
2. On that settings screen, enter the application's client ID and client secret, and save. The secret is stored encrypted.
3. Choose **Authorize**, sign in to WordPress.com if asked, and choose the site to authorize.
4. You're returned to the settings screen, which shows the access token, blog ID and blog URL. Copy them into the site's fields. The settings screen doesn't store the token itself.

The connection test checks that the token is valid. It doesn't check that the token can write to the blog ID you entered, so check the blog ID carefully.

### RSS

Pulls items from an RSS or Atom feed.

- **Enter feed URL:** the feed's address.
- **Select post type:** the post type pulled items become. Only post types an editor could create are offered: posts, pages and custom post types with an admin screen.
- **Select post status**, **Select comment status** and **Select ping status:** applied to every pulled post.
- **Select category status:** whether to import the feed's categories as categories.

The connection test fetches and parses the feed, as a pull would.

### XML

Pulls items from any XML feed. You describe where each post is in the feed, and which element holds each field, with XPath. The fields are described in [Pulling feeds](pulling.md#mapping-an-xml-feed).

The connection test fetches the feed and checks that it parses as XML. It doesn't check your XPath mappings: run a pull and check the [logs](logs-and-failures.md) for that.

## Sitegroups

A sitegroup is a named set of sites. A site can be in several sitegroups, and sitegroups can be nested: a sitegroup includes the sites in its child sitegroups, so pushing to or pulling a parent sitegroup also covers its children.

- When you edit a post, the **Syndicate** box lists every sitegroup. The post is pushed to every enabled site in the sitegroups you tick. See [Pushing posts](pushing.md).
- On **Settings → Push Syndication**, the **Site Groups** section chooses which sitegroups are pulled on a schedule. See [Pulling feeds](pulling.md).

Sitegroups can mix transports, but keep push sites and pull sites in separate sitegroups: pushing to a feed site logs an error for every push, and pulling from an XML-RPC site finds nothing.

Creating, editing and deleting sitegroups needs `manage_categories`, as for categories.

## Turn a site off

To stop pushing to or pulling from a site without deleting it, edit it and set **Status** to **Disabled**. Its remote copies and pulled posts are left as they are.

A pull site can also be disabled automatically after repeated failures. See [Logs and failures](logs-and-failures.md#disabling-failing-sites).

Copies of your posts on a disabled push site aren't deleted when you delete the posts or untick the site's sitegroup.

Deleting a site doesn't delete anything on the other site, or any posts already pulled from it.
