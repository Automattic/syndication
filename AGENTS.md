# Syndication

Cross-site post syndication for WordPress multisite and external sites.

## Project Knowledge

| Property | Value |
|----------|-------|
| **Main file** | `push-syndication.php` |
| **Text domain** | `push-syndication` |
| **Namespace** | `Automattic\Syndication` |
| **Source directory** | `includes/` |
| **Version** | 2.2.0 |
| **Requires PHP** | 8.2+ |
| **Requires WP** | 6.4+ |

### Directory Structure

```
syndication/
├── includes/
│   ├── Domain/             # Value objects, contracts (SiteConfig, SiteCredentials)
│   ├── Application/        # Services, DTOs, contracts (PullService, PushService)
│   └── Infrastructure/     # WordPress integration, transports, CLI, repositories
│       ├── Transport/      # REST, XML-RPC, Feed transport implementations
│       ├── CLI/            # WP-CLI commands (11 commands)
│       └── WordPress/      # Hooks, admin UI, encryption
├── tests/
│   ├── Unit/               # Unit tests (Brain Monkey)
│   ├── Integration/        # Integration tests (wp-env)
│   ├── Stubs/              # Test stubs/doubles
│   └── Behat/              # Behat BDD context classes
├── features/               # Behat feature files (.feature)
├── behat.yml               # Behat configuration
├── .github/workflows/      # CI: cs-lint, integration, unit
└── .phpcs.xml.dist         # PHPCS configuration
```

### Key Classes

- **Domain**: `SiteConfig`, `SiteCredentials` (value objects); syndication contracts
- **Application**: `PullService`, `PushService` (orchestrate syndication); DTOs for data transfer
- **Infrastructure**: `TransportFactory` (creates REST/XML-RPC/Feed transports), `HookManager`, `Container` (DI), `EncryptionService`
- **CLI**: 11 commands for pull/push site/sitegroup management and listing

### Dependencies

- **Dev**: `automattic/vipwpcs`, `yoast/wp-test-utils`, `behat/behat`

## Commands

```bash
composer cs                # Check code standards (PHPCS)
composer cs-fix            # Auto-fix code standard violations
composer lint              # PHP syntax lint
composer test:unit         # Run unit tests
composer test:integration  # Run integration tests (requires wp-env)
composer test:integration-ms  # Run multisite integration tests
composer coverage          # Run tests with HTML coverage report
composer behat             # Run Behat BDD scenarios (requires wp-env)
composer behat-rerun       # Re-run failed Behat scenarios
composer prepare-behat     # Prepare environment for Behat tests
```

## Conventions

Follow the standards documented in `~/code/plugin-standards/` for full details. Key points:

- **Commits**: Use the `/commit` skill. Favour explaining "why" over "what".
- **PRs**: Use the `/pr` skill. Squash and merge by default.
- **Branch naming**: `feature/description`, `fix/description` from `develop`.
- **Testing**: Three test types here:
  - **Unit tests**: For isolated domain/application logic. Use `Yoast\WPTestUtils\BrainMonkey\YoastTestCase`.
  - **Integration tests**: For WordPress-dependent behaviour. Use `Yoast\WPTestUtils\WPIntegration\TestCase`.
  - **Behat tests**: For CLI contract verification and critical happy paths only. Keep Behat scenarios minimal (2-5 per command).
- **Code style**: WordPress coding standards via PHPCS. Tabs for indentation.
- **i18n**: All user-facing strings must use the `push-syndication` text domain.
- **DDD layering**: Domain classes must not depend on Infrastructure or Application.

## Architectural Decisions

- **Domain-Driven Design (DDD)**: Three-layer architecture (Domain/Application/Infrastructure). Do not bypass layers.
- **Transport pattern**: Syndication supports multiple transport protocols (REST API, XML-RPC, RSS Feed). New transport types should implement the transport interface and be registered via `TransportFactory`.
- **Push-based model**: The primary syndication direction is push (from source to targets). Pull is also supported but push is the primary flow.
- **Encryption**: Site credentials are encrypted at rest using `EncryptionService`. Never store credentials in plain text.
- **DI container**: Services are wired via a DI container. Register new services there rather than instantiating directly.
- **Behat for CLI testing**: WP-CLI commands are tested with Behat for contract verification. Keep Behat scenarios focused on the CLI interface, not internal logic.
- **Multisite awareness**: This plugin operates across sites. Many operations require multisite context — always consider which site context code runs in.

## Common Pitfalls

- Do not edit WordPress core files or bundled dependencies in `vendor/`.
- Run `composer cs` before committing. CI will reject code standard violations.
- Integration tests require `npx wp-env start` running first.
- **Do not violate DDD layer boundaries**: Domain must not `use` anything from Infrastructure or Application.
- **Site context matters**: When syndicating across sites in multisite, always be explicit about which site context you're operating in. Use `switch_to_blog()` / `restore_current_blog()` correctly and always restore.
- **Credentials are encrypted**: Never log, expose, or store credentials in plain text. Use `EncryptionService`.
- Behat tests are slow (10-20 seconds per scenario). Do not write Behat tests for edge cases — use integration tests instead.
- The text domain is `push-syndication` (not `syndication`). Get this right in all i18n calls.
- Transport errors from remote sites can be unpredictable. Always handle transport exceptions gracefully and provide meaningful error messages.
- Do not add new transports without considering authentication, error handling, and retry logic.
