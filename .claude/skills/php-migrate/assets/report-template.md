# PHP migration report

## Versions
- Current: <from> (signals: <detect.sh signals used>)
- Target: <target> (image `php:<exact>-cli`, PHP <PHP_VERSION>)
- New minimum: <floor>
- PHPUnit: <old> → <new>
- Not tested: PHP versions between <from> and <target> (single jump)

## Tools
| Tool | Version |
|---|---|
| Docker images | php-migrate:<target>, php-migrate:<floor>, php-migrate:<from> |
| Composer | <version> |
| Rector | <version> |
| PHP_CodeSniffer + PHPCompatibility | <version> + <version> |
| PHPStan (+ deprecation rules) | <version> (level <n>) |

## Baseline (before)
- Tests on <from>: <passed/failed counts, or "no baseline: reason">
- Inventory scan: PHPCompatibility <n>, PHPStan <n>

## Changes
### Automated (Rector)
- <rule>: <count> — one line each, highest first

### By hand
- <file:line> — <what changed> — <why (php.net item / tool finding)>

### Dependencies and config
- composer.json: <require.php, platform, PHPUnit, ext-*>
- composer.lock: regenerated
- phpunit.xml: <migrated / rewritten>
- CI: <changed / not approved: what it needs>
- README: <changed / not approved>

## Skipped on purpose
- Risky Rector rules not applied: <list> — available with `rector.sh --allow-risky`
- Waivers: <tool id file:line — reason>
- Latent bugs found, not version-related, left alone: <list>

## Result
- `verify.sh --floor <floor> --target <target>`: <green / not green + what remains>
- Tests on <target>: <counts>; on <floor>: <counts>
- Remaining scan findings: <0 / list>

## Next steps
- Version: the minimum PHP rose from <from> to <floor> — release as a new major version (e.g. <x>.0.0).
- <anything else>
