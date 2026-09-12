# Syndication

Push and pull post syndication between WordPress sites and external endpoints.

> **Branch note:** this file describes `develop`, the 2.x line. A separate
> long-lived `3.x` branch holds an unmerged DDD rewrite (namespaced
> `Automattic\Syndication`, `Domain`/`Application`/`Infrastructure` layers, a DI
> container). PR #197 targets `3.x`, not `develop`. Nothing below applies to
> that branch.

## Project Knowledge

| Property | Value |
|----------|-------|
| **Main file** | `push-syndication.php` |
| **Text domain** | `push-syndication` |
| **Namespace** | None — classes use the `Syndication_` prefix |
| **Source directory** | `includes/` |
| **Version** | 2.2.0 |
| **Requires PHP** | 7.4+ (may be raised later) |
| **Requires WP** | 6.4+ |

### Directory Structure

```
syndication/
├── includes/           # Flat directory of class-*.php and interface-*.php files
│   └── css/            # Admin styles for the site editor and site list
├── languages/          # push-syndication.pot
├── tests/
│   ├── Unit/           # Brain Monkey, no WordPress bootstrap
│   └── Integration/    # Real WordPress via wp-env
├── .github/workflows/  # cs-lint, unit, integration
├── .phpcs.xml.dist     # PHPCS configuration
└── .wp-env.json        # wp-env configuration
```

There is no PSR-4 autoloading. Everything is `require_once`d from
`push-syndication.php`, so a new class file must be wired in there explicitly.

### Key Classes

- **`WP_Push_Syndication_Server`** — the main controller. Registers the `syn_site`
  post type and `syn_sitegroup` taxonomy, admin UI, settings, and the push/pull
  cron jobs. Instantiated into `$GLOBALS['push_syndication_server']`.
- **`Syndication_Client_Factory`** — maps a transport type to a class by string
  concatenation: `Syndication_{$transport_type}_Client`. Also proxies
  `display_settings` and `save_settings` to that class.
- **`Syndication_Client`** (interface) — implemented by `Syndication_WP_REST_Client`,
  `Syndication_WP_RSS_Client`, `Syndication_WP_XML_Client` and
  `Syndication_WP_XMLRPC_Client`.
- **`Syndication_Encryption`** — wraps a `Syndication_Encryptor` implementation.
  `Syndication_Encryptor_OpenSSL` is used on PHP 7.1+;
  `Syndication_Encryptor_MCrypt` only below that. Instantiated into
  `$GLOBALS['push_syndication_encryption']`.
- **`Syndication_Logger`** — logging, with `Syndication_Logger_Viewer`,
  `Syndication_Logger_List_Table` and `Syndication_Logger_Admin_Notice` for display.
- **`Syndication_Event_Counter`**, **`Syndication_Site_Failure_Monitor`**,
  **`Failed_Syndication_Auto_Retry`** — failure tracking and retry, each
  instantiated at load time.
- **`Syndication_CLI_Command`** — registered as `wp syndication`, loaded only when
  `WP_CLI` is defined. Four subcommands: `push_all_posts`, `push_post`,
  `pull_site`, `pull_sitegroup`.

### Dependencies

Runtime requires only `composer/installers`. Dev: `automattic/vipwpcs`,
`yoast/wp-test-utils`, `phpunit/phpunit` ^9, `php-parallel-lint/php-parallel-lint`,
`phpcompatibility/phpcompatibility-wp`, and `sirbrillig/phpcs-changed` (pinned to
`^2.13`; see the comment in `cs-lint.yml` before bumping it).

## Commands

```bash
composer cs                   # Check code standards (PHPCS)
composer cs-fix               # Auto-fix code standard violations (PHPCBF)
composer lint                 # PHP syntax lint
composer lint-ci              # Syntax lint with checkstyle output
composer i18n                 # Regenerate languages/push-syndication.pot
composer test:unit            # Unit tests (no WordPress needed)
composer test:integration     # Integration tests (requires wp-env running)
composer test:integration-ms  # Integration tests under WP_MULTISITE=1
composer coverage             # HTML coverage report into .phpunit.cache/
composer coverage-ci          # Coverage via wp-env
```

### Running integration tests

`wp-env start` must be running first. The `test:integration*` scripts hardcode
`--env-cwd=wp-content/plugins/syndication`, but wp-env mounts the plugin under
the *checkout directory name*. In a worktree or differently named clone, run
phpunit directly instead:

```bash
wp-env run tests-cli --env-cwd=wp-content/plugins/<dir-name> ./vendor/bin/phpunit --testsuite integration
```

## Conventions

Follow the standards documented in `~/code/plugin-standards/` for full details.
Key points:

- **Commits**: Use the `/commit` skill. Favour explaining "why" over "what".
- **PRs**: Use the `/pr` skill. Squash and merge by default.
- **Branch naming**: `feature/description`, `fix/description` from `develop`.
- **Code style**: WordPress coding standards via PHPCS — `WordPress-Extra`,
  `WordPress-Docs`, `WordPress-VIP-Go` and `PHPCompatibilityWP`, with
  `testVersion` at `7.4-`. Tabs for indentation. `tests/` is excluded from PHPCS.
- **Naming**: New global functions, classes and constants need the `syndication`
  prefix, enforced by `WordPress.NamingConventions.PrefixAllGlobals`.
- **i18n**: All user-facing strings must use the `push-syndication` text domain.
- **Testing**: two suites, and the names are case-sensitive and inconsistent —
  `--testsuite Unit` but `--testsuite integration`.
  - **Unit**: extend `tests/Unit/TestCase.php` (which extends
    `Yoast\WPTestUtils\BrainMonkey\TestCase`). No WordPress loaded, so mock it.
  - **Integration**: extend `Yoast\WPTestUtils\WPIntegration\TestCase`.

## CI

- **cs-lint.yml** — `composer validate`, PHP parse-error lint, an XML lint of
  `phpunit.xml.dist`, and a code style gate. The gate runs `phpcs-changed` on
  pull requests only, reporting just the violations a change itself introduces
  rather than the whole backlog. Errors fail the build and appear as inline
  annotations on the diff; warnings annotate without failing.
- **unit.yml** — unit tests on PHP 7.4, 8.1, 8.2 and 8.3.
- **integration.yml** — wp-env integration tests, single site and multisite, on
  WP 6.4 with PHP 7.4 and WP `master` with PHP latest. Installs `@wordpress/env`
  globally at its latest version, so CI can drift from a developer's pinned
  local install.

## Architectural Decisions

- **Sites are posts**: each syndication endpoint is a `syn_site` post, grouped by
  the `syn_sitegroup` taxonomy. Site settings live in post meta on that post.
- **Transport by naming convention**: `Syndication_Client_Factory` resolves a
  class name by string concatenation, so a new transport must be named
  `Syndication_{Type}_Client`, implement `Syndication_Client`, expose static
  `display_settings`/`save_settings`, and be `require_once`d in the main file.
- **Push is the primary direction**; pull is supported and runs on cron.
- **Credentials are encrypted at rest** via `Syndication_Encryption`. Never store
  or log them in plain text.
- **Globals, not injection**: the server and encryption objects are passed around
  as `$GLOBALS`. This is legacy, and the main file carries a `@TODO` about it, but
  match the existing pattern rather than introducing a container on `develop`.

## Common Pitfalls

- Do not edit WordPress core files or bundled dependencies in `vendor/`.
- Run `composer cs` before committing, but expect a wall of pre-existing
  violations: the backlog is 511 errors and 106 warnings across 17 files, and
  `phpcbf` fixes none of them. CI only holds you to the lines you actually
  changed, so `composer cs` output is mostly other people's debt — read it for
  your own files, not as a pass/fail signal.
- Adding a class file is not enough — add the `require_once` to
  `push-syndication.php`, as there is no autoloader.
- **Do not re-add `processUncoveredFiles` to `phpunit.xml.dist`.** It makes
  PHPUnit statically include every file under `includes/`, three of which
  `require_once ABSPATH . ...` at the top level. The unit suite runs without
  WordPress, so that crashes coverage generation and makes `composer test:unit`
  exit non-zero for anyone with Xdebug or PCOV installed. CI does not catch it,
  as it runs with `coverage: none`.
- **mcrypt is absent from modern PHP**, so the `Syndication_Encryptor_MCrypt`
  tests skip locally and in CI. Skipped encryption tests are expected, not a
  regression.
- **Site context matters**: when syndicating across sites in multisite, be
  explicit about which site you are operating in. Use `switch_to_blog()` /
  `restore_current_blog()` and always restore.
- Transport errors from remote sites are unpredictable. Handle exceptions
  gracefully and give meaningful messages.
- The text domain is `push-syndication`, not `syndication`. Get this right in all
  i18n calls.
