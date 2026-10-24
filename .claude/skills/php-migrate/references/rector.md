# Rector in this skill

Read this file before the rewrite phase, or when Rector output looks wrong.

## What `scripts/rector.sh` generates
`<state>/rector.php`, regenerated on every run:
```php
return RectorConfig::configure()
    ->withPaths(/* composer autoload + autoload-dev + tests, from scripts/lib/paths.php */)
    ->withPhpVersion(80500)          // never introduce syntax above the new minimum
    ->withPhpSets(php85: true)       // every PHP set up to the new minimum
    ->withComposerBased(phpunit: true)   // PHPUnit 6 → 13 rules, chosen by the INSTALLED PHPUnit
    ->withAttributesSets(phpunit: true)  // PHPUnit annotations → attributes
    ->withCache('/state/rector-cache')
    ->withSkip([/* risky list below */, '*/vendor/*']);
```
- `withPhpVersion()` matters: without it Rector reads `composer.json` `require.php` (lower bound), then `config.platform.php`, then the PHP it runs on.
- `withPhpSets()` takes exactly one version flag; it applies all sets up to that version.
- PHPUnit per-version sets (`PHPUNIT_60` … `PHPUNIT_130`) were **removed** in Rector 2.6.2. Only the composer-based set exists, and it reads `vendor/composer/installed.json` — so upgrade and install PHPUnit **before** running Rector.

## Risky rules skipped by default
`--allow-risky` removes the skip list. Use it only when the user explicitly asks; otherwise list these in the report as "available, not applied".

| Rule (set) | Why it is skipped |
|---|---|
| `RandomFunctionRector` (php70) | `rand()` → `random_int()`: a CSPRNG that throws on invalid ranges; changes seeded behaviour. |
| `RemoveExtraParametersRector` (php71) | Deletes call arguments beyond declared parameters, including ones the callee reads with `func_get_args()`. |
| `ChangeSwitchToMatchRector` (php80) | `match` compares strictly and throws `UnhandledMatchError`; `switch` is loose. |
| `ClassPropertyAssignToConstructorPromotionRector` (php80) | Changes constructor signatures and property declarations (public API). |
| `OptionalParametersAfterRequiredRector` (php80) | Adds defaults/reorders, changing signatures. |
| `ReadOnlyPropertyRector` (php81), `ReadOnlyClassRector` (php82), `ReadOnlyAnonymousClassRector` (php83) | Break later writes, hydrators, `clone` and `unserialize` patterns. |
| `AddTypeToConstRector` (php83) | Restricts how subclasses may override constants. |
| `SleepToSerializeRector`, `WakeupToUnserializeRector` (php85) | Change the serialized format of stored sessions and caches. |

The skill never adds `declare(strict_types=1)` (no PHP set does; do not add it by hand either).

## Known gaps
- No `mysql_*` → `mysqli`/PDO migration. Rewrite by hand.
- Rector 2 uses nikic/php-parser 5, which has limited PHP 5 support: `$$foo[0]` is always parsed with PHP 7 semantics, `global $$var[0]` is a parse error. Check these spots by hand first.
- Not covered by any set (fix by hand, see the "Tools miss" sections): `null` passed to internal functions, implicit float → int, return types on internal-interface methods (`ArrayAccess`, `Iterator`, `Countable`, `JsonSerializable`), dynamic properties, relative callables, string ↔ number comparisons, division by zero, empty `explode()` separators, `null` array offsets in `offsetSet()`.
- `ChrArgModuloRector` (php85) rewrites `chr($x)` to `chr($x % 256)`; when `$x` is a float, `%` still converts it implicitly — cast to `int` first.
- Rector does not format code. Keep the project's style; review `git diff` after applying.

## Workflow details
- Dry run first: `rector.sh --floor X` (exit 1 = changes pending). Show the user the `rules` summary.
- Apply: `rector.sh --floor X --apply` repeats passes (max 5) until one reports no changes. **Several passes are required:** a file that still contains syntax PHP 8 cannot analyse (e.g. `$s{0}`) gets no class scope, so scope-based rules such as `Php4ConstructorRector` silently skip it until a first pass has removed that syntax.
- One rule only: `rector.sh --floor X --only ExplicitNullableParamTypeRector --apply`.
- Full report: `<state>/reports/rector.json` (diffs per file). Errors: `<state>/reports/rector.stderr`.
