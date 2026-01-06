# Changelog for Syndication

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2026-01-06

**Note on versioning:** This release follows 2.0.1 from the `develop` branch. A `2.1` branch existed with experimental features but was never formally released. To avoid confusion with that unreleased work and establish a clean baseline, we're releasing as 2.2.0. The `3.0.0` release will incorporate architectural improvements and any valuable features from the experimental branch, bringing all development back to a single, unified codebase.

### Fixed

* fix: defer pull jobs refresh to prevent timeout with many sites by @GaryJones in <https://github.com/Automattic/syndication/pull/185>
* fix: add validation before queuing auto-retry cron jobs by @GaryJones in <https://github.com/Automattic/syndication/pull/186>
* fix: use wp_strip_all_tags for token/password sanitization by @GaryJones in <https://github.com/Automattic/syndication/pull/187>
* fix: format dates as ISO 8601 for WordPress.com REST API by @GaryJones in <https://github.com/Automattic/syndication/pull/188>
* fix: prevent PHP warning when settings are null by @GaryJones in <https://github.com/Automattic/syndication/pull/182>
* fix: prevent syndication loops through unique post identification by @GaryJones in <https://github.com/Automattic/syndication/pull/184>
* security: escape output in admin interfaces by @GaryJones in <https://github.com/Automattic/syndication/pull/181>

### Added

* feat: pass site ID to syn_rss_pull_filter_post filter by @chetansatasiya in <https://github.com/Automattic/syndication/pull/128>

### Maintenance

* test: add comprehensive unit tests for cron pull time interval by @GaryJones in <https://github.com/Automattic/syndication/pull/183>

## [2.0.1] - 2017-12-18

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

### Maintenance

* security: add nonces for notification dismissal by @betzster
* security: add missing sanitization for message data by @betzster

## [2.0.0] - 2012-08-20

### Changed

* Refactored codebase to follow WordPress coding standards
* Renamed plugin files and classes for consistency
* Added user role selection for syndication permissions
* Improved CSS styling for admin interfaces

## 1.0.0 - 2012-07-25

Initial release.

[2.2.0]: https://github.com/Automattic/syndication/compare/2.0.1...2.2.0
[2.0.1]: https://github.com/Automattic/syndication/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/Automattic/syndication/compare/1.0...2.0.0
