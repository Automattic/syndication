# Contributing to Syndication

Thank you for your interest in contributing. This document covers setting up a local environment, running the checks, and proposing a change.

## Code of Conduct

This project follows the [Automattic Code of Conduct](https://automattic.com/code-of-conduct/).

## Development Setup

### Prerequisites

- [Node.js](https://nodejs.org/) LTS or later
- [Docker Desktop](https://www.docker.com/products/docker-desktop)
- [Composer](https://getcomposer.org/)

### Setup

1. Clone the repository.
2. Install dependencies:
   ```bash
   composer install
   npm -g install @wordpress/env
   ```
3. Start the WordPress environment:
   ```bash
   wp-env start
   ```
4. Visit http://localhost:8888 and log in with `admin` / `password`.

### Checks

```bash
composer lint                # PHP syntax
composer cs                  # Coding standards
composer test:unit           # Unit tests (no WordPress needed)
composer test:integration    # Integration tests (single site, needs wp-env)
composer test:integration-ms # Integration tests (multisite, needs wp-env)
```

## Workflow

1. Create a branch from `develop`:
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b fix/short-description
   ```
2. Make your change, with tests.
3. Run the checks locally.
4. Push your branch and open a pull request against `develop`.

## Code Standards

We follow the [WordPress VIP Coding Standards](https://github.com/Automattic/VIP-Coding-Standards), configured in `.phpcs.xml.dist`. Run `composer cs-fix` to fix what PHPCS can fix automatically.

## Tests

Unit tests live in `tests/Unit/` and run without WordPress. Integration tests live in `tests/Integration/` and run inside wp-env.

- New behaviour should come with tests.
- A bug fix should come with a test that fails without the fix.

## Pull Requests

- Keep each pull request to one change, and explain what it does and why.
- Reference related issues, for example `Fixes #123`.
- Make sure CI passes before requesting a review.
- **Sign your commits.** `develop` and `main` only accept commits with a verified signature, so a pull request with an unsigned commit cannot be merged until it is re-signed and force-pushed. See GitHub's guide to [signing commits](https://docs.github.com/en/authentication/managing-commit-signature-verification/signing-commits).

## Releases

Maintainers cut releases from a `release/x.y.z` branch into `main`. Pushing the version tag publishes a GitHub release with an installable ZIP attached, and stable releases (tags without a hyphen) are deployed to WordPress.org.
