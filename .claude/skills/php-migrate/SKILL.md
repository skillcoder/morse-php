---
name: php-migrate
description: Migrates a PHP project from an older PHP version (5.6 or later) to a newer one (up to 8.5) in a single jump. It detects the current version, asks the user to confirm the current, target and minimum versions, upgrades Composer dependencies and PHPUnit, rewrites code with Rector, detects what is left with PHPCompatibility, PHPStan and php -l, and fixes every deprecation until the test suite passes on the target PHP in Docker. Use when the user asks to upgrade, migrate or bump PHP, support a newer PHP version, raise the composer.json php constraint, or fix PHP deprecations.
argument-hint: "[from-version] [to-version]"
arguments: [from, to]
disable-model-invocation: true
allowed-tools: Bash(${CLAUDE_SKILL_DIR}/scripts/*), Read, Grep, Glob, AskUserQuestion
---

# PHP migration (single jump)

Upgrade the project in the current directory from its current PHP version to a target version in one pass, then fix until the target PHP runs everything clean.
Arguments: from=`$from`, to=`$to` (either may be empty).

## Rules — follow these for the whole run
1. Work on a branch, never on the default branch.
2. Run PHP, Composer and every tool only through `${CLAUDE_SKILL_DIR}/scripts/` (Docker). Never install tools into the project.
3. Never apply the risky Rector rules (`--allow-risky`) or add `declare(strict_types=1)` unless the user explicitly asked.
4. Edit `composer.json` only for `require.php`, `config.platform.php`, the PHPUnit constraint and `ext-*` entries. Anything else needs the user's approval.
5. Ask before editing CI files (they are outward-facing); README edits need the preflight approval.
6. Move to the next phase only when that phase's check passes. After every code edit, re-run the check that failed.
7. Never leave `--no-blocking`, `--ignore-platform-reqs` or waivers without a written reason in the final state.
8. Deprecations raised inside `vendor/` are not project failures; deprecations in project code are.
9. Do not commit unless the user asked to; at the end, ask.
10. Record each finished phase with `state.sh set phase.<name> done`; on a re-run, skip finished phases.

## Scripts
Run from the project root. `S="${CLAUDE_SKILL_DIR}/scripts"`. Every script has `--help`, prints JSON on stdout, and exits 0 ok · 1 findings/changes · 2 tool error · 3 Docker unavailable.

| Script | Purpose |
|---|---|
| `detect.sh` | Docker/git status and every current-version signal |
| `image.sh <ver>` | build `php-migrate:<ver>` (official `php:<ver>-cli` + Composer) |
| `php.sh <ver> -- <cmd>` | run php/composer/vendor binaries on that PHP |
| `tools.sh` | install pinned Rector, PHPCompatibility, PHPStan into the cache |
| `rector.sh --floor <min> [--apply]` | Rector dry run / apply |
| `lint.sh <ver>` | `php -l` with all error levels |
| `test.sh <ver> [--label L]` | `composer install` + PHPUnit with all errors on |
| `scan.sh --floor <min> --target <max>` | PHPCompatibility + PHPStan, merged |
| `verify.sh --floor <min> --target <max>` | the gate: lint + validate + tests + scan |
| `state.sh get/set/reset/path` | resume state in `<git dir>/php-migrate/` |

Reports land in `$("$S/state.sh" path)/reports/`. False positives go in `<state>/waivers.json` with a reason (format in `scripts/lib/summarize-scan.php`).

## Checklist
Copy this into your reply and keep it updated:
```
- [ ] 1 Preflight: detect, branch, confirm versions with the user
- [ ] 2 Baseline: tests on the current PHP, inventory scan
- [ ] 3 Dependencies: require.php, platform, PHPUnit, composer update
- [ ] 4 Rewrite: Rector, PHPUnit config migration
- [ ] 5 Detect and review: lint, scan, version checklists
- [ ] 6 Fix loop: verify.sh green
- [ ] 7 Housekeeping: composer validate/audit, ext-*, CI, README
- [ ] 8 Report and commit question
```

## 1. Preflight
1. Run `"$S/detect.sh"`. Stop and tell the user if `docker` is not `ok`, the directory is not a git repo, or `git.dirty` is true.
2. If the current branch is the default branch, create `php-migrate/<from>-to-<target>` after step 3.
3. Ask **one** `AskUserQuestion` with these four questions, recommended option first:
   - **Current PHP** — `detected.suggested_current` (say which signals it came from), then `$from` if given and different, then the next detected version.
   - **Target PHP** — the newest stable from [references/tooling.md](references/tooling.md) (currently 8.5), then `$to` if given, then the previous minor. Mention the exact patch image that will be used.
   - **New minimum PHP** — "Same as target (Recommended)", "Keep a lower minimum" (the user types it), or the previous minor.
   - **Extra edits** (multiSelect) — "Update CI config", "Update README", "Fix composer.json metadata for `validate --strict`".
   Ask even when arguments were given. If current ≥ target, report that there is nothing to do and stop.
4. Save the answers: `state.sh set from …`, `target …`, `floor …`, `edits …`; then `state.sh set phase.preflight done`. Use the exact patch version (e.g. `8.5.12`) for `<target>`, and the identical string for `<floor>` when they are the same line, so the scripts test it once.
5. Build images: `image.sh <from>`, `image.sh <target>`, `image.sh <floor>`; run `tools.sh`.

## 2. Baseline
1. `test.sh <from> --label baseline` (add `--no-blocking` only if Composer blocks the old lock). If the old image or dependencies cannot install, record "no baseline" and continue — do not spend more than two attempts.
2. `scan.sh --floor <from> --target <target> --label baseline` — keep the counts for the report (PHPStan clamps to 7.1).
3. Read the reference file for every step between current and target in `references/versions/` (see the index below) **now**, before changing code.

## 3. Dependencies
Read [references/composer.md](references/composer.md).
1. `php.sh <target> -- composer prohibits php <target>`. A runtime blocker → ask the user; dev blockers → upgrade.
2. Set `require.php` (keep the constraint style) and `config.platform.php` per composer.md.
3. Set PHPUnit to the major chosen from [references/tooling.md](references/tooling.md); store it with `state.sh set phpunit <major>`.
4. `php.sh <target> -- composer update -W --no-interaction`. Fix resolution errors before going on.

## 4. Rewrite
Read [references/rector.md](references/rector.md).
1. `rector.sh --floor <floor>` (dry run). Show the user the per-rule summary in two or three lines.
2. `rector.sh --floor <floor> --apply` — it repeats passes until one changes nothing (`"converged": true`); later passes catch files the first pass could not analyse. Review `git diff` of the result.
3. PHPUnit config: `php.sh <target> -- vendor/bin/phpunit --migrate-configuration`. If it refuses the old file, rewrite it from the template in [references/phpunit.md](references/phpunit.md). Always set `<source>` to the library directories.
4. Fix remaining test-code changes listed in phpunit.md that Rector did not do.

## 5. Detect and review
1. `lint.sh <target>` and `lint.sh <floor>` (if different). Fix parse errors first.
2. `scan.sh --floor <floor> --target <target>`. Treat PHPCompatibility 10 (alpha) findings as candidates: confirm with lint, tests or the php.net page before changing code; waive confirmed false positives with a reason.
3. For each version file between current and target, Grep every pattern in its "Grep patterns" list and review each hit against its "Tools miss — review manually" list. Fix real problems. This is where most behaviour changes are found (string↔number comparison, division by zero, `null` to internal functions, float → int, `ArrayAccess` return types, dynamic properties, `null` array offsets, `chr()`/`ord()` ranges).

## 6. Fix loop
Run `verify.sh --floor <floor> --target <target>`. Read the failing step, fix the code, re-run. Repeat until `"green": true`.
- Compare test failures with the baseline: a test that failed before the migration is reported, not silently "fixed" by changing its assertion.
- Prefer fixes that keep behaviour: declare properties, add return types that match the interface, validate inputs that now throw (`0` divisors, empty separators, `null` offsets), cast floats to `int` before `chr()`/`%`/keys.
- Raise the PHPStan level (`--level 1…5`) only if the user wants more than the migration.

## 7. Housekeeping
1. `php.sh <target> -- composer validate --strict --no-check-publish` and `php.sh <target> -- composer audit`.
2. Check `ext-*`: Grep the source for the functions in the composer.md extension table; add missing requirements.
3. CI (only if approved in preflight, otherwise list what CI needs): replace dead or old matrices with jobs on the floor and target PHP; prefer the CI system the project already uses.
4. README (only if approved): the PHP requirement and install line.
5. If the minimum PHP rose, recommend a new **major** version tag in the report (do not tag).
6. Final `verify.sh --floor <floor> --target <target>` must be green.

## 8. Report
Fill [assets/report-template.md](assets/report-template.md) in your reply: versions, tool versions (`tools.sh` output), Rector rules applied, risky rules skipped, issues fixed by hand, waivers with reasons, things left alone (latent bugs that are not version-related), and the final `verify.sh` result. Then ask whether to commit; commit only on a yes, on the migration branch.

## Gotchas
- `withPhpVersion()` is always set by `rector.sh`; a hand-written Rector config without it reads `composer.json` and may target the old version.
- The `composer` Docker image runs PHP 8 internally; always run Composer through `php.sh <version>` so it resolves for the right PHP.
- `php:5.6-cli` and `php:7.0-cli` are inactive Debian stretch images; `image.sh` handles the archived apt sources.
- PHPStan cannot target below PHP 7.1; PHPCompatibility 8.x sniffs exist only in the 10.0 alpha.
- `vendor/` is shared between PHP versions: `test.sh` re-runs `composer install` for the version it tests, so the baseline install is overwritten later. That is expected.
- PHPUnit ≥ 10 counts deprecations only from `<source>` directories; without `<source>` vendor noise fails the run.
- `--migrate-configuration` needs PHPUnit ≥ 9.3 and refuses pre-9 `<log type=…>` configs; `: void` fixtures need PHP ≥ 7.1.
- Coverage reports in `phpunit.xml` without Xdebug/PCOV make PHPUnit ≥ 10 run **no tests** — keep them out of the default config.
- Rector's PHPUnit rules depend on the *installed* PHPUnit: run Rector after `composer update`. They do not cover every PHPUnit deprecation (e.g. PHPUnit 13's `expectExceptionMessage()`); the scan's `method.deprecated` findings list the rest.
- Keep behaviour for callers: when a PHP 8 change turns a former notice into an error, reproduce the old result explicitly (`$offset ?? ''`, `?: []`, `(string)` casts) rather than adding new exceptions, unless the old result was already broken (e.g. a `0` divisor). Add a regression test for each such change.
- A single jump never tests the PHP versions between current and target. That is fine only because the minimum and target are both tested; say so in the report.

## Reference files
Read a version file when the range includes its target version; read the others when their phase starts.
- [references/versions/5.6-to-7.0.md](references/versions/5.6-to-7.0.md) — `Error` exceptions, removed `mysql_*`/`ereg`, PHP 4 constructors
- [references/versions/7.0-to-7.1.md](references/versions/7.0-to-7.1.md) — `ArgumentCountError`, non-numeric arithmetic warnings
- [references/versions/7.1-to-7.2.md](references/versions/7.1-to-7.2.md) — `count()` warnings, `each()`/`create_function()` deprecated
- [references/versions/7.2-to-7.3.md](references/versions/7.2-to-7.3.md) — `continue` in `switch`, heredoc markers, PCRE2
- [references/versions/7.3-to-7.4.md](references/versions/7.3-to-7.4.md) — `$s{0}`, nested ternaries, array access on `null`
- [references/versions/7.4-to-8.0.md](references/versions/7.4-to-8.0.md) — the big one: comparisons, `TypeError`s, removed functions
- [references/versions/8.0-to-8.1.md](references/versions/8.0-to-8.1.md) — `null` to internal functions, float → int, return types
- [references/versions/8.1-to-8.2.md](references/versions/8.1-to-8.2.md) — dynamic properties, `${}` interpolation
- [references/versions/8.2-to-8.3.md](references/versions/8.2-to-8.3.md) — `range()`, string increment, `assert.*`
- [references/versions/8.3-to-8.4.md](references/versions/8.3-to-8.4.md) — implicit nullable types, `E_USER_ERROR`, `round()`
- [references/versions/8.4-to-8.5.md](references/versions/8.4-to-8.5.md) — `chr()`/`ord()`, casts, `null` offsets, float → int warnings
- [references/tooling.md](references/tooling.md) — target choice, PHPUnit ↔ PHP, images, pinned tools; read in preflight and phase 3
- [references/composer.md](references/composer.md) — phases 3 and 7
- [references/rector.md](references/rector.md) — phase 4
- [references/phpunit.md](references/phpunit.md) — phases 3–4 when PHPUnit changes major
