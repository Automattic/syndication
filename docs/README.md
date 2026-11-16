# Syndication documentation

What Syndication does, and how to install it, is covered in the [README](../README.md). These pages go further.

## Using Syndication

- [Setting up sites](setting-up-sites.md): adding destination and source sites, the four transports, connection tests, and sitegroups.
- [Pushing posts](pushing.md): choosing where a post goes, when it's pushed, what each transport sends, updates, deletions, and loop prevention.
- [Pulling feeds](pulling.md): scheduling pulls, how RSS and XML feed items become posts, mapping XML with XPath, and updating pulled posts.
- [Logs and failures](logs-and-failures.md): the Logs screen, what's recorded, retries, and how failing sites are disabled.
- [WP-CLI commands](wp-cli.md): every `wp syndication` command.

## Security and administration

- [Security and credentials](security.md): who can do what, how stored passwords and tokens are protected, `PUSH_SYNDICATE_KEY`, and the outbound requests the plugin makes.

## Extending Syndication

- [Customizing Syndication](customizing.md): recipes for the most common changes.
- [Filters and actions](hooks.md): every hook the plugin provides.

## Contributing

- [How Syndication works](architecture.md): where sites, settings and state are stored, the scheduled events, and how the code is organized, including how to add a transport.
- [Contributing guide](../CONTRIBUTING.md): local environment, tests, coding standards, and pull requests.

## Concepts

- **Site:** another place content is pushed to or pulled from: a WordPress site, a WordPress.com site, or a feed. Each site is stored as a `syn_site` post, which isn't public, managed under **Sites** in the admin.
- **Transport:** how the plugin talks to a site. There are four: WordPress XML-RPC and WordPress.com REST for pushing, and RSS and XML for pulling.
- **Sitegroup:** a named group of sites. Posts are pushed to sitegroups, not to individual sites, and pulls are scheduled by sitegroup.
- **Push:** copying a post from this site to every enabled site in the sitegroups chosen for it, and keeping those copies up to date.
- **Pull:** importing items from a feed site into this site as posts, on a schedule.
- **Remote copy:** the post a push creates on another site. The plugin remembers each copy's ID, so later pushes update it rather than creating another.
