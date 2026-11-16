# Changelog for Syndication

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.0] - 2026-11-16

**Upgrade note:** The upgrade runs on the first request after updating, including cron, so it no longer waits for someone to open wp-admin. It keeps two behaviors that change in this release. Existing XML sites have their post, comment and ping status settings set to what their pulled posts were already getting (until now those settings had no effect), and "Delete pushed posts" is ticked on existing installs, because trashing a post always deleted its remote copies before. Review both after updating. Site logs move to protected meta keys, and a site that is deleted and added again for the same feed now pulls its items as new posts. Admin text now says "site group" instead of "sitegroup", so some translations need updating. A `syn_use_php_error_logging` callback that declares its parameter as `bool` will now receive `null` and must accept it.

### Added

* Add the `syndication_pull_post_types` filter, which sets the post types pulled posts can be created as by @GaryJones in <https://github.com/Automattic/syndication/pull/247>
* Add the `syndication_xmlrpc_loop_guard_max_pages` filter, which limits how far the XML-RPC loop guard searches a remote site by @GaryJones in <https://github.com/Automattic/syndication/pull/239>
* Add the `syndication_cli_push_all_posts_pause` filter, which sets the pause between batches in `wp syndication push-all-posts` by @GaryJones in <https://github.com/Automattic/syndication/pull/279>

### Changed

* Run the upgrade routine on the first request of any kind, including cron, WP-CLI and REST, rather than only when an admin opens wp-admin by @GaryJones in <https://github.com/Automattic/syndication/pull/264>
* Apply an XML site's post, comment and ping status settings to the posts it pulls. Existing XML sites are upgraded to `publish` and the comment and ping status their posts were already getting, so they keep pulling as before by @GaryJones in <https://github.com/Automattic/syndication/pull/251>
* Make the "Delete pushed posts" setting control whether trashing or deleting a post deletes its remote copies. It is ticked on existing installs, because trashing a post always deleted them before by @GaryJones in <https://github.com/Automattic/syndication/pull/260>
* Push posts published by WP-Cron, WP-CLI or code, such as scheduled posts, and push edits by users without the syndicate capability to posts that already have site groups selected by @GaryJones in <https://github.com/Automattic/syndication/pull/263>
* Match pulled items only to posts pulled from the same site. A site that is deleted and added again for the same feed now pulls its items as new posts; the `syn_pre_find_post_by_guid` filter can match them to the old ones by @GaryJones in <https://github.com/Automattic/syndication/pull/278>
* Require the matching custom field capability in the XML-RPC thumbnail methods for fields other than `_thumbnail_id`, and check the OAuth `state` when authorizing WordPress.com by @GaryJones in <https://github.com/Automattic/syndication/pull/278>
* Move site logs to the protected meta keys `_syn_log`, `_syn_log_errors` and `_syn_log_errors_overall`. The upgrade moves existing logs, so code that reads the old keys needs updating by @GaryJones in <https://github.com/Automattic/syndication/pull/255>
* Stop XML pulls writing to underscore-prefixed meta keys, setting `ID` or `import_id`, or creating posts of internal types. Core's `is_protected_meta` filter can allow a specific key by @GaryJones in <https://github.com/Automattic/syndication/pull/247>
* Disable a site when saving it if its RSS feed can't be fetched and parsed, or its XML URL doesn't return XML, rather than reporting the connection as working by @GaryJones in <https://github.com/Automattic/syndication/pull/254>
* Let `syn_use_php_error_logging` return `false` to turn PHP error logging off when `WP_DEBUG` is on. The filter's default is now `null`, which follows `WP_DEBUG` by @GaryJones in <https://github.com/Automattic/syndication/pull/246>
* Let users with the syndicate capability save the settings screen they can already open by @GaryJones in <https://github.com/Automattic/syndication/pull/270>
* Make `wp syndication pull_sitegroup` exit with an error for an unknown site group, rather than pulling every scheduled site, and report disabled sites and failed pulls by @GaryJones in <https://github.com/Automattic/syndication/pull/269>
* Make the WP-CLI push commands warn about posts with no site groups and report failed pushes. Missing arguments are now WP-CLI usage errors, and the output wording has changed by @GaryJones in <https://github.com/Automattic/syndication/pull/242>
* Say "site group" instead of "sitegroup" throughout the admin and WP-CLI output, title the settings screen "Push Syndication Settings", and reword several messages so they translate properly. The `pull_sitegroup` command and `--sitegroup` option are unchanged by @GaryJones in <https://github.com/Automattic/syndication/pull/275>
* Show the Site Status box's Enabled/Disabled select directly, add a "Change transport" button instead of saving the site when the transport changes, and lay out the XPath mappings as a table with Delete and Add new buttons, as part of fixes for keyboard and screen reader users by @GaryJones in <https://github.com/Automattic/syndication/pull/277>

### Fixed

* Keep pushed posts password-protected on XML-RPC destinations by @GaryJones in <https://github.com/Automattic/syndication/pull/238>
* Stop the loop guard skipping edits to XML-RPC destinations, and let it find older posts and posts of other types by @GaryJones in <https://github.com/Automattic/syndication/pull/239>
* Return a site's own log when the logger is asked for a single site, and keep backslashes in logged messages by @GaryJones in <https://github.com/Automattic/syndication/pull/240>
* Send REST push dates in UTC so posts on sites outside UTC arrive at the right time, report failed REST responses as errors, and send term lists correctly by @GaryJones in <https://github.com/Automattic/syndication/pull/241>
* Attach categories created during an RSS pull to the first post that uses them, and store RSS dates correctly on sites outside UTC by @GaryJones in <https://github.com/Automattic/syndication/pull/243>
* Stop XML pulls crashing on unparseable feeds or unmatched XPath, stop invalid XPath creating posts with empty fields, and apply feed-level post field mappings by @GaryJones in <https://github.com/Automattic/syndication/pull/244>
* Push posts containing a `[gallery]` without `ids` over XML-RPC, and report gallery image failures instead of pushing the error as the post content by @GaryJones in <https://github.com/Automattic/syndication/pull/245>
* Apply the logger's `syn_pre_debug_level` and `syn_use_php_error_logging` filters when added from a theme or a later plugin by @GaryJones in <https://github.com/Automattic/syndication/pull/246>
* Fetch RSS feeds through the WordPress HTTP API, so pulls respect proxy settings, user agent filters and HTTP API filters by @GaryJones in <https://github.com/Automattic/syndication/pull/249>
* Show what went wrong when WordPress.com authorization fails, rather than a fatal error by @GaryJones in <https://github.com/Automattic/syndication/pull/250>
* Stop one client's pull callbacks running on another client's posts, which force-published RSS posts in runs that also pulled an XML site by @GaryJones in <https://github.com/Automattic/syndication/pull/251>
* Keep an XML-RPC site's saved settings when an invalid URL is submitted, and keep tracking a remote thumbnail whose deletion failed by @GaryJones in <https://github.com/Automattic/syndication/pull/252>
* Fix the Logs screen's column widths on PHP 8, the month filter skipping months, the search box, and access for users with the syndicate capability by @GaryJones in <https://github.com/Automattic/syndication/pull/253>
* Show the real error when a site's connection test fails by @GaryJones in <https://github.com/Automattic/syndication/pull/254>
* Show only sites' logs on the Logs screen, and the newest entries for each site by @GaryJones in <https://github.com/Automattic/syndication/pull/255>
* Disable a site only after consecutive pull failures, rather than after a lifetime total, and count empty or broken XML feeds as failures by @GaryJones in <https://github.com/Automattic/syndication/pull/256>
* Fix PHP 8.1 deprecations and a warning in the admin notices by @GaryJones in <https://github.com/Automattic/syndication/pull/257>
* Push to and pull from every site in a site group, not just the first 100 by @GaryJones in <https://github.com/Automattic/syndication/pull/258>
* Stop recording a push skipped by a filter, or a push to a pull-only site, as a remote post by @GaryJones in <https://github.com/Automattic/syndication/pull/259>
* Record remote delete failures, and stop permanently deleted posts leaving their remote copies behind by @GaryJones in <https://github.com/Automattic/syndication/pull/260>
* Stop one pull creating duplicate categories by @GaryJones in <https://github.com/Automattic/syndication/pull/261>
* Stop pull jobs multiplying each time settings or sites change, and stop pushes being dropped by @GaryJones in <https://github.com/Automattic/syndication/pull/262>
* Fetch a fresh RSS feed on every pull, rather than a copy cached for up to an hour by @GaryJones in <https://github.com/Automattic/syndication/pull/265>
* Send each XML-RPC push's featured image only to its own site by @GaryJones in <https://github.com/Automattic/syndication/pull/266>
* Log a push skipped by a filter as skipped, not as a success by @GaryJones in <https://github.com/Automattic/syndication/pull/267>
* Make the XML namespace setting usable in XPath, with the `syn:` prefix by @GaryJones in <https://github.com/Automattic/syndication/pull/268>
* Keep the copy on a site that is still in a ticked site group when another of its site groups is unticked, and fire the delete hooks when copies are removed by @GaryJones in <https://github.com/Automattic/syndication/pull/271>
* Keep XML node mappings that share an XPath when a site is saved by @GaryJones in <https://github.com/Automattic/syndication/pull/274>
* Fix admin text that couldn't be translated, or was split into fragments translators couldn't reorder by @GaryJones in <https://github.com/Automattic/syndication/pull/275>
* Fix keyboard focus order, focus loss, labels and contrast on the Edit Site and settings screens by @GaryJones in <https://github.com/Automattic/syndication/pull/277>
* Stop RSS items with no title, content or excerpt failing on PHP 7.4, stop a site without a feed URL raising a deprecation, and show the site's Move to Trash link only to users who can delete it by @GaryJones in <https://github.com/Automattic/syndication/pull/279>

### Maintenance

* Move the guides into `docs/`, correct the readme, and use "site group" throughout by @GaryJones in <https://github.com/Automattic/syndication/pull/272> and <https://github.com/Automattic/syndication/pull/276>
* Make the integration test scripts work in any checkout directory by @GaryJones in <https://github.com/Automattic/syndication/pull/248>
* Fix the RSS connection test by @GaryJones in <https://github.com/Automattic/syndication/pull/273>
* Number the release 2.3.0 in the docs and code by @GaryJones in <https://github.com/Automattic/syndication/pull/281>
* Run the integration tests on each supported PHP version, report notices and deprecations, and fix tests that couldn't fail by @GaryJones in <https://github.com/Automattic/syndication/pull/279>

## [2.2.2] - 2026-11-16

### Fixed

* Stop the logger raising PHP warnings when a post is pushed or deleted, and record push deletions in the site's syndication log, which it had never done by @GaryJones in <https://github.com/Automattic/syndication/pull/234>

### Maintenance

* Add a contributing guide and a security policy pointing to HackerOne by @GaryJones in <https://github.com/Automattic/syndication/pull/231> and <https://github.com/Automattic/syndication/pull/232>
* Document why `phpcs-changed` must stay at 3.1 or later by @GaryJones in <https://github.com/Automattic/syndication/pull/235>
* Update development dependencies by @dependabot

## [2.2.1] - 2026-09-12

Security release. Sites running 2.2.0 or earlier should update.

**Upgrade note:** The `syndication.addThumbnail` and `syndication.postGalleryImage` XML-RPC methods now fetch thumbnail URLs with `wp_safe_remote_get()`, which blocks loopback and reserved IP ranges. The thumbnail URL is built from the source site's own attachment URL, so a site that syndicates from a host on a private address will find that posts continue to syndicate while thumbnails silently stop. If that applies to you, allow the source host through the core `http_request_host_is_external` filter.

### Security

* Require edit_post capability in syndication.addThumbnail by @GaryJones in <https://github.com/Automattic/syndication/pull/209>
* Harden the syn_log meta read in the log viewer by @GaryJones in <https://github.com/Automattic/syndication/pull/210>
* Escape Syndication log viewer output to fix stored XSS by @GaryJones in <https://github.com/Automattic/syndication/pull/211>
* Deny capability-checked writes to the syndication log meta keys by @GaryJones in <https://github.com/Automattic/syndication/pull/211>
* Authenticate XML-RPC thumbnail methods before fetching remote URLs by @GaryJones in <https://github.com/Automattic/syndication/pull/212>
* Verify TLS certificates on WordPress.com API requests by @GaryJones in <https://github.com/Automattic/syndication/pull/216>
* Reject XML feeds that declare a doctype by @GaryJones in <https://github.com/Automattic/syndication/pull/219>
* Authorise site settings saves on their own nonce by @GaryJones in <https://github.com/Automattic/syndication/pull/220>
* Make stored credential fields write-only in the admin by @GaryJones in <https://github.com/Automattic/syndication/pull/221>
* Validate the syndication settings and encrypt the stored client secret by @GaryJones in <https://github.com/Automattic/syndication/pull/222>

### Fixed

* fix: resolve filtering and pagination issues in Syndication Logs screen by @GaryJones in <https://github.com/Automattic/syndication/pull/193>
* Correct the log ID filter dropdown, which could never match the selected log by @GaryJones in <https://github.com/Automattic/syndication/pull/211>
* Clean up the plugin's internationalisation by @GaryJones in <https://github.com/Automattic/syndication/pull/223>
* Fix deprecation notices and label accessibility in the admin UI by @GaryJones in <https://github.com/Automattic/syndication/pull/224>
* Stop the RSS client emitting a SimplePie deprecation notice by @GaryJones in <https://github.com/Automattic/syndication/pull/225>

### Maintenance

* chore: exclude dev files from release archives by @GaryJones in <https://github.com/Automattic/syndication/pull/191>
* chore: PHPCS coding standards fixes by @GaryJones in <https://github.com/Automattic/syndication/pull/194>
* Stop unit tests exiting non-zero when a coverage driver is installed by @GaryJones in <https://github.com/Automattic/syndication/pull/213>
* Enforce coding standards on the lines a pull request changes by @GaryJones in <https://github.com/Automattic/syndication/pull/214>
* Automate releases and reconnect the plugin to WordPress.org by @GaryJones in <https://github.com/Automattic/syndication/pull/226>

## [2.2.0] - 2026-01-06

**Note on versioning:** This release follows 2.0.1 from the `develop` branch. A `2.1` branch existed with experimental features but was never formally released. To avoid confusion with that unreleased work and establish a clean baseline, we're releasing as 2.2.0. The `3.0.0` release will incorporate architectural improvements and any valuable features from the experimental branch, bringing all development back to a single, unified codebase.

### Security

* security: escape output in admin interfaces by @GaryJones in <https://github.com/Automattic/syndication/pull/181>

### Fixed

* fix: defer pull jobs refresh to prevent timeout with many sites by @GaryJones in <https://github.com/Automattic/syndication/pull/185>
* fix: add validation before queuing auto-retry cron jobs by @GaryJones in <https://github.com/Automattic/syndication/pull/186>
* fix: use wp_strip_all_tags for token/password sanitization by @GaryJones in <https://github.com/Automattic/syndication/pull/187>
* fix: format dates as ISO 8601 for WordPress.com REST API by @GaryJones in <https://github.com/Automattic/syndication/pull/188>
* fix: prevent PHP warning when settings are null by @GaryJones in <https://github.com/Automattic/syndication/pull/182>
* fix: prevent syndication loops through unique post identification by @GaryJones in <https://github.com/Automattic/syndication/pull/184>

### Added

* feat: pass site ID to syn_rss_pull_filter_post filter by @chetansatasiya in <https://github.com/Automattic/syndication/pull/128>

### Maintenance

* test: add comprehensive unit tests for cron pull time interval by @GaryJones in <https://github.com/Automattic/syndication/pull/183>

## [2.0.1] - 2017-12-18

### Security

* security: add nonces for notification dismissal by @betzster
* security: add missing sanitization for message data by @betzster

### Fixed

* fix: declare Syndication globally in a more explicit way by @nickdaugherty in <https://github.com/Automattic/syndication/pull/139>

### Added

* feat: add auto-retry for failed syndication connections by @tott in <https://github.com/Automattic/syndication/pull/77>
* feat: add two new filters for the post body sent to the REST API by @betzster in <https://github.com/Automattic/syndication/pull/78>
* feat: add admin notifications for syndication status by @betzster
* feat: display message when site status is disabled due to failed connection by @betzster

### Changed

* chore: add Travis CI configuration by @trepmal in <https://github.com/Automattic/syndication/pull/122>
* chore: add icon and banner assets by @kraftbj in <https://github.com/Automattic/syndication/pull/79>

## [2.0.0] - 2012-08-20

### Changed

* Refactored codebase to follow WordPress coding standards
* Renamed plugin files and classes for consistency
* Added user role selection for syndication permissions
* Improved CSS styling for admin interfaces

## 1.0.0 - 2012-07-25

Initial release.

[2.3.0]: https://github.com/Automattic/syndication/compare/2.2.2...2.3.0
[2.2.2]: https://github.com/Automattic/syndication/compare/2.2.1...2.2.2
[2.2.1]: https://github.com/Automattic/syndication/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/Automattic/syndication/compare/2.0.1...2.2.0
[2.0.1]: https://github.com/Automattic/syndication/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/Automattic/syndication/compare/1.0...2.0.0
