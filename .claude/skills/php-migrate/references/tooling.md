# Tooling matrix and pinned versions

Read this file when choosing the target PHP, the PHPUnit version, or when a tool or image fails to install.
Facts verified on 2026-10-24. Refresh them with the commands at the end before trusting them much later.

## Contents
- PHP releases
- Docker images
- Composer
- PHPUnit ↔ PHP
- Third-party tools (pinned in `scripts/env.sh`)
- Which tools are official
- Refreshing the pins

## PHP releases
- Newest stable: **PHP 8.5** (8.5.0 on 2025-11-20; latest patch **8.5.12**, 2026-10-22). Security support until 2029-12-31.
- Other supported lines: 8.4 (8.4.27), 8.3 (8.3.36), 8.2 (8.2.35). PHP 8.6 is at RC stage — never pick an RC as a target unless the user asks.
- Source: https://www.php.net/supported-versions.php

## Docker images
Docker Official Images, maintained by docker-library (official on Docker Hub, not by php.net):
- `php:<version>-cli` — `8.5-cli` = `8.5.12-cli` (Debian trixie). Use an exact patch tag (e.g. `8.5.12`) when the user names one.
- `php:5.6-cli` and `php:7.0-cli` still pull but are **inactive** (Debian stretch, last built 2019). `image.sh` rewrites apt sources to `archive.debian.org` automatically.
- `composer:2` (2.10.x) runs on `php:8-alpine`; `composer:2.2` is the LTS line for PHP 5.3.2–7.2.4. `image.sh` copies the right binary into each `php-migrate:<version>` image, so Composer always runs on the project's PHP.

## Composer
- 2.10.3 needs PHP ≥ 7.2.5; 2.2.30 LTS supports PHP ≥ 5.3.2 (critical fixes only, maintained until at least 2026-12-31).
- Since 2.9, `update`/`require` **block package versions with security advisories** (`config.policy.advisories.block`). Old projects may need `--no-blocking` / `COMPOSER_NO_BLOCKING=1` for the *baseline only*.

## PHPUnit ↔ PHP
Pick the **highest** PHPUnit whose minimum PHP is ≤ the new minimum PHP, and which runs on the target.

| PHPUnit | Needs PHP | Notes |
|---|---|---|
| 13 | ≥ 8.4.1 | Supported until 2028-02-04. Officially names 8.4 and 8.5 as supported. |
| 12 | ≥ 8.3 | Supported until 2027-02-05. Annotations removed (attributes only). |
| 11 | ≥ 8.2 | Bugfix support ended 2026-02-06; 11.5.30+ adapted for PHP 8.5. |
| 10 | ≥ 8.1 | Life support; 10.5.50+ adapted for PHP 8.5. |
| 9.6 | ≥ 7.3 | Life support, still released (9.6.38, 2026-10-05); 9.6.24+ adapted for PHP 8.5. Only choice for a minimum of 7.3–8.0. |
| 8.5 | ≥ 7.2 | EOL. |
| 7.5 | ≥ 7.1 | EOL. |
| 6.5 | ≥ 7.0 | EOL. |
| 5.7 | 5.6–7.1 | EOL. Only for the baseline run of a 5.6 project. |

Source: https://phpunit.de/supported-versions.html and the PHPUnit changelogs.
`phpunit --migrate-configuration` exists from **9.3**. Test-code changes per version: [phpunit.md](phpunit.md).

## Third-party tools (pinned in `scripts/env.sh`)
| Tool | Version | Runs on | Analyses |
|---|---|---|---|
| Rector `rector/rector` | 2.7.0 | PHP ≥ 7.4 (named-argument config needs 8.x) | PHP 5.3 → 8.6 sets; PHPUnit rules bundled |
| PHP_CodeSniffer `squizlabs/php_codesniffer` | 4.0.4 | PHP ≥ 7.2 | — |
| PHPCompatibility `phpcompatibility/php-compatibility` | 10.0.0-alpha2 | PHPCS 4 | PHP 5.0 → 8.5 (**alpha**: 8.x sniffs may give false positives/negatives) |
| PHPStan `phpstan/phpstan` | 2.3.2 | PHP ≥ 7.4 | `phpVersion` 7.1 → 8.6 only |
| `phpstan/phpstan-deprecation-rules` | 2.0.x | — | `@deprecated` symbols, deprecated casts/INI |

All tools run in `php-migrate:8.5` from `~/.cache/php-migrate/tools` and are never added to the project.
The latest **stable** PHPCompatibility (9.3.5, 2019) has no PHP 8 checks, which is why the alpha is pinned.

## Which tools are official
- Official (PHP project): the migration guides (`https://www.php.net/manual/en/migrationNN.php`), php-src `UPGRADING` files, the `php` binary (`php -l`, runtime error reporting).
- Official for their own ecosystem: Composer, PHPUnit (`--migrate-configuration`), Docker Official Images.
- Third-party de-facto standards (no official equivalent exists): Rector, PHPCompatibility, PHPStan.

## Refreshing the pins
```bash
S="${CLAUDE_SKILL_DIR}/scripts"
"$S/php.sh" 8.5 -- composer show --all rector/rector | grep -E '^versions'
"$S/php.sh" 8.5 -- composer show --all phpcompatibility/php-compatibility | grep -E '^versions'
"$S/php.sh" 8.5 -- composer show --all phpstan/phpstan | grep -E '^versions'
"$S/php.sh" 8.5 -- composer show --all phpunit/phpunit | grep -E '^versions'
curl -s 'https://hub.docker.com/v2/repositories/library/php/tags/8.5-cli' | grep -o '"last_updated":"[^"]*"'
```
Then update `scripts/env.sh`, this file, and the "Rector covers" sections in `references/versions/` if Rector's sets changed (`ls ~/.cache/php-migrate/tools/vendor/rector/rector/config/set/`).
