# Security and credentials

## Who can do what

| Task | Needs |
| --- | --- |
| Add, edit and delete sites | The syndication capability |
| Change **Settings → Push Syndication** | The syndication capability |
| See the **Syndicate** box and choose a post's sitegroups | The syndication capability |
| Read **Sites → Logs**, and see and hide Syndication's admin notices | The syndication capability |
| Create, edit and delete sitegroups | `manage_categories` |
| Push changes to a post that already has sitegroups | Being able to edit the post |
| Delete a post's remote copies, when **Delete pushed posts** is ticked | Being able to trash or delete the post |

The syndication capability is `manage_options` by default. To use another capability, return it from the `syn_syndicate_cap` filter, early, before `init`:

```php
add_filter( 'syn_syndicate_cap', fn () => 'edit_others_posts' );
```

Choose a capability you'd trust with the credentials of every site you push to, and with adding feeds that can create posts on this site.

WP-CLI commands don't check capabilities.

## Stored credentials

These are stored encrypted:

- XML-RPC site passwords, in each site's `syn_site_password` custom field;
- WordPress.com access tokens, in each site's `syn_site_token` custom field;
- the WordPress.com application's client secret, in the `push_syndicate_settings` option.

Usernames, site addresses, blog IDs and the client ID are stored as plain text.

Password, token and client secret fields are never shown again once saved. When you edit a site or the settings, leave them blank to keep the saved value.

### PUSH_SYNDICATE_KEY

The encryption key comes from the `PUSH_SYNDICATE_KEY` constant. Define it in `wp-config.php` with a long random value, **before** anyone enters a password or token:

~~~php
define( 'PUSH_SYNDICATE_KEY', 'a-long-random-string' );
~~~

If you don't define it, the plugin uses a fixed default that's the same for every site. Credentials are then only obscured, not protected: anyone with a copy of your database and this plugin's code can read them.

Changing the key, or defining it after credentials have been saved, makes every saved password, token and client secret unreadable, without any warning: pushes and authorization simply start failing. There's no way to re-encrypt them under the new key, so after changing it, re-enter the password or token for every site, and the client secret.

The encryption is AES-256-CBC, with the key and a fixed initialization vector both derived from `PUSH_SYNDICATE_KEY`, so two identical secrets are stored identically. It protects credentials in database exports and backups, as long as the key isn't stored alongside them; it isn't a substitute for keeping the database private. If PHP's OpenSSL extension doesn't offer AES-256-CBC, credentials are stored unencrypted.

## Pulled content

Feed content is treated as untrusted:

- Feed values can't be written to protected custom fields (keys starting with an underscore) or to the plugin's own `syn_` and `_syn_` fields.
- A feed can't choose which existing post to overwrite: `ID` and `import_id` are removed from every pulled post.
- Pulled posts can only be posts, pages or custom post types with an admin screen, not WordPress's internal types such as templates, global styles and reusable blocks. Other plugins' post types with an admin screen are allowed. The [`syndication_pull_post_types`](hooks.md#syndication_pull_post_types) filter changes the list.
- XML feeds are parsed without network access, and feeds containing a `DOCTYPE` or entity declarations are refused.
- Pulled posts are saved like any other post, so WordPress filters their HTML according to the user running the pull. A scheduled pull runs with no user, so the HTML is filtered as for a user without `unfiltered_html`.

## Outbound requests

| Request | Function | Private network addresses |
| --- | --- | --- |
| XML-RPC pushes | `wp_safe_remote_post()` | Refused |
| Featured images and gallery images, downloaded by the destination site | `wp_safe_remote_get()` | Refused |
| WordPress.com API and authorization | `wp_remote_get()`, `wp_remote_post()` | Only `public-api.wordpress.com` is contacted, over HTTPS |
| RSS and XML feeds | WordPress HTTP API | Allowed |

To push to a site on a private network, or to let a destination fetch images from one, allow the host with WordPress's `http_request_host_is_external` filter, and any unusual port with `http_allowed_safe_ports`.

Feeds on private addresses are allowed so that intranet feeds work. That means anyone who can add a feed site can make this site request internal addresses, which is another reason to keep the syndication capability to trusted users.

## XML-RPC methods

Syndication adds three XML-RPC methods to every site it's active on, so that sites pushing to it can send featured images and gallery images: `syndication.addThumbnail`, `syndication.deleteThumbnail` and `syndication.postGalleryImage`. Each needs a username and password. Adding a featured image needs `upload_files` and permission to edit the post, removing one needs permission to edit the post, and uploading a gallery image needs `upload_files`.

If nothing pushes to a site, you can remove them:

```php
add_filter(
	'xmlrpc_methods',
	function ( array $methods ): array {
		unset( $methods['syndication.addThumbnail'], $methods['syndication.deleteThumbnail'], $methods['syndication.postGalleryImage'] );
		return $methods;
	},
	20
);
```

## Reporting a vulnerability

See the [security policy](../.github/SECURITY.md).
