# Syndication

Syndicate content to and from your sites.

Here's how it works:

1. Configure which post types are "pushable", as well as whether you'd like syndicated posts to be deleted when the master post is deleted.
2. Register and group your sites into "Sitegroups" in settings.
3. In the WordPress posting interface, you'll see a new "Syndication" metabox, with all of your sitegroups listed. Select the sitegroups you want to push to and the post will be automatically syndicated to your other sites when it's published and updated.

### WordPress.com REST API

To push content using the WordPress.com REST API you need to create an [application](https://developer.wordpress.com/apps/new/) from the [WordPress.com Developer Resources](https://developer.wordpress.com/) site - and you can also generate API tokens directly from the plugin's settings page.

Fill in the client ID and client secret as displayed on the app page, and click the authorize button to get directed to the authorization page on WordPress.com. Select which site you'd like to push to and click "Authorize", at which point you'll be redirected back to your settings page-which will now display the API token, Blog ID, and Blog URL. You can now use this information to register your WordPress.com site.

### Security

To store passwords securely, we recommend defining an encryption key, which will be used to encrypt credentials when saved to the database.

    define('PUSH_SYNDICATE_KEY', 'this-is-a-random-key')

### Pulling from RSS Feeds

Push Syndication can ingest RSS feeds into your site for you. It's as simple as adding a site, setting the transport type to "RSS (pull)" and entering an RSS URL and title.

To add a site, go to the WordPress admin and find the "Sites" menu item, below Settings and choose "Add New". In the settings, you'll be able to customize the post's type, status, comment settings, pingback settings and category.

### Using WordPress XMLRPC

Note that, if you use the XMLRPC push syndication method and you have two-factor authentication enabled on your account, you will need to create an application password to use when adding a new site. Using your regular password will not work.

## Contributing ##

If you are interested in contributing, we need help with two main areas:

1. Fixing bugs in v1
2. Feature development in v2

Issues have been created for each of the planned feature developments. Help with documentation for both versions is also greatly appreciated.