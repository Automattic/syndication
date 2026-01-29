# Behat Test Strategy

## Why Behat Tests Exist

Behat tests verify the **CLI contract** - the actual user experience when running WP-CLI commands.
They are slow (~15s per scenario) because they run against a real WordPress instance via wp-env.

Unit tests mock everything; Behat tests are full end-to-end. There's value in having a small number
of Behat tests that verify the complete workflow works, while keeping the majority of test coverage
in faster PHPUnit tests.

## Testing Pyramid for CLI Commands

```
        /\
       /  \     Few Behat tests (smoke tests, CLI contract)
      /----\
     /      \   More PHPUnit integration tests (scenarios)
    /--------\
   /          \ Many unit tests (services, domain logic)
  --------------
```

- **Unit tests**: Test individual classes in isolation with mocked dependencies
- **Integration tests**: Test commands with real WordPress database, but invoke programmatically
- **Behat tests**: Test full CLI execution via wp-env shell

## What Behat Tests Should Cover

Behat tests provide unique value for:

1. **CLI argument parsing** - WP-CLI's own parsing behaviour (missing required args, invalid values)
2. **Output format verification** - Exact text users see (table format, JSON output)
3. **Environment validation** - Plugin activation, command registration
4. **File I/O operations** - Import/export functionality with real files (if applicable)

## Current Scenarios (and Rationale)

| Feature | Scenario | Rationale |
|---------|----------|-----------|
| pull-site.feature | Missing --site_id parameter | Tests WP-CLI argument parsing |
| pull-site.feature | Invalid site_id | Tests error output format |
| pull-site.feature | Non-site post type | Tests validation error message |
| push-post.feature | Missing --post_id parameter | Tests WP-CLI argument parsing |
| push-post.feature | Invalid post_id | Tests error output format |
| push-post.feature | Post with no sitegroups | Tests error output format |
| sites-list.feature | List sites in table format | Tests output formatting |
| sites-list.feature | List sites in JSON format | Tests JSON output structure |
| sitegroups-list.feature | List sitegroups | Tests output formatting |

## What NOT to Test in Behat

These are better covered by PHPUnit integration tests:

- **Individual edge cases** - e.g., site with missing configuration
- **Domain validation logic** - e.g., URL validation, transport type checking
- **Flag combinations** - e.g., `--verbose`, `--format`
- **Multiple sitegroups** - e.g., deduplication logic
- **Success paths** - Full push/pull success (requires remote setup)

These scenarios can execute ~100x faster in PHPUnit than in Behat.

## Adding New Behat Tests

Before adding a Behat scenario, ask:

1. **Does this test something only Behat can verify?** (CLI parsing, output format)
2. **Is this a critical happy path worth the ~15s execution cost?**
3. **Can this be tested faster with PHPUnit?**

If the answer to #1 or #2 is "no", write a PHPUnit integration test instead.

### Good Candidates for Behat

- A new command that needs smoke testing
- Output format verification (table, JSON, CSV)
- WP-CLI argument parsing edge cases
- Multi-command workflows

### Poor Candidates for Behat

- Error handling for every invalid input
- Internal business logic
- Flag combinations
- Edge cases that don't affect CLI contract

## Running Behat Tests

```bash
# Run all Behat tests
composer behat

# Run a specific feature
composer behat -- features/push-post.feature

# Run a specific scenario by line number
composer behat -- features/push-post.feature:10
```

## Running PHPUnit Integration Tests

```bash
# Run CLI integration tests only
composer test:integration -- --group=cli

# Run a specific test class
composer test:integration -- tests/Integration/CLI/PullSiteCommandTest.php
```

## Test Distribution Guidelines

| Test Type | Purpose | Speed | Count per Command |
|-----------|---------|-------|-------------------|
| **Behat** | CLI contract verification | ~15s/scenario | 2-5 scenarios |
| **PHPUnit Integration** | Scenario coverage | ~0.5-2s/test | 10-20 tests |
| **PHPUnit Unit** | Service/domain logic | ~5-50ms/test | As needed |

A well-tested CLI command should have:
- 2-5 Behat scenarios (smoke tests, output format)
- 10-20 PHPUnit integration tests (all scenarios)
- 0 unit tests for the command itself (it's a thin adapter)
- Comprehensive unit tests for the services the command calls
