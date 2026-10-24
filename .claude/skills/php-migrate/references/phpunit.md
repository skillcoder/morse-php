# PHPUnit upgrade notes

Read this file when PHPUnit changes major version during the migration, or when the PHPUnit config does not migrate.

## Order of work
1. Pick the PHPUnit major from [tooling.md](tooling.md) (highest that supports the new minimum PHP).
2. Set it in `composer.json` `require-dev` and run `composer update -W` in the target image.
3. Run `rector.sh` — `withComposerBased(phpunit: true)` and `withAttributesSets(phpunit: true)` rewrite test code for the **installed** version.
4. Migrate the XML config (below).
5. Fix what is left by hand, using the list below.

## Test-code changes by version
- **6**: `PHPUnit_Framework_TestCase` → `PHPUnit\Framework\TestCase` (namespaced classes only); `setExpectedException()` → `expectException()`; `getMock()` → `createMock()`.
- **7**: needs PHP 7.1; `assertArraySubset` deprecated in 8.
- **8**: fixture methods need `: void` — `setUp(): void`, `tearDown(): void`, `setUpBeforeClass(): void`, `tearDownAfterClass(): void`, `assertPreConditions(): void`, `assertPostConditions(): void`, `onNotSuccessfulTest(Throwable $t): void`. `assertInternalType()` → `assertIsArray()`/`assertIsString()`/…; `assertContains()` on strings → `assertStringContainsString()`; `@expectedException*` annotations deprecated → `expectException*()`.
- **9**: `assertRegExp()` → `assertMatchesRegularExpression()`, `assertNotRegExp()` → `assertDoesNotMatchRegularExpression()`, `assertFileNotExists()` → `assertFileDoesNotExist()`, `assertDirectoryNotExists()` → `assertDirectoryDoesNotExist()`; `at()` matcher and `withConsecutive()` deprecated.
- **10**: data providers must be `public static`; `withConsecutive()` removed; `<coverage>`/`<filter>` config replaced by `<source>`; risky-test and issue reporting reworked (deprecations/notices/warnings are counted, not failures, unless `failOn*` is set).
- **11**: doc-comment annotations (`@test`, `@dataProvider`, `@covers`, `@group`) deprecated → attributes (`#[Test]`, `#[DataProvider]`, `#[CoversClass]`, `#[Group]`).
- **12**: annotations no longer read; mocks without configured expectations are reported — use `createStub()` for test doubles you only stub.
- **13**: needs PHP 8.4.1. `expectExceptionMessage()` is deprecated (https://github.com/sebastianbergmann/phpunit/issues/6560): rename to `expectExceptionMessageIsOrContains()` (identical semantics — the old method delegates to it) or use `expectExceptionMessageIs()` for an exact match in new tests. It is a doc-comment deprecation, so tests do not report it; PHPStan's deprecation rules (`scan.sh`) do. Check https://phpunit.de/announcements/phpunit-13.html for other removals.

## Config migration
- PHPUnit ≥ 9.3: `vendor/bin/phpunit --migrate-configuration` rewrites `phpunit.xml(.dist)` to the installed version's schema. It writes `<file>.bak` — delete it after review. It may add `cacheDirectory=".phpunit.cache"`: add `.phpunit.cache` to `.gitignore`.
- PHPUnit 13 migrated a plain PHPUnit 5 config (`<filter><whitelist>` → `<source>`) without trouble. Configs with `<logging><log type="…" yui= highlight= charset=>` can still be refused: then rewrite by hand with this template and copy over the testsuites, bootstrap and php/ini settings.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnDeprecation="true"
         failOnNotice="true"
         failOnWarning="true"
         displayDetailsOnTestsThatTriggerDeprecations="true"
         displayDetailsOnTestsThatTriggerNotices="true"
         displayDetailsOnTestsThatTriggerWarnings="true"
         displayDetailsOnPhpunitDeprecations="true">
  <testsuites>
    <testsuite name="default">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
  <source>
    <include>
      <directory>src</directory>
    </include>
  </source>
</phpunit>
```
- `<source>` tells PHPUnit 10+ which code is first-party: deprecations from `vendor/` are then not counted against the project. Always set it to the library directories.
- Old `<logging><log type="junit|testdox-html|testdox-text">` entries become `<logging><junit outputFile="…"/><testdoxHtml outputFile="…"/><testdoxText outputFile="…"/></logging>`. Drop yui/highlight/charset/title options.
- **Do not put coverage reports in the default config** unless the project's CI has Xdebug or PCOV: with `<coverage><report>` configured and no driver, PHPUnit 13 prints "No code coverage driver available" and **runs no tests** ("No tests executed!"). PHPUnit 5 silently skipped the report instead. Generate coverage on demand: `vendor/bin/phpunit --coverage-html build/coverage` (with a driver). `test.sh` always passes `--no-coverage`.
- Put `cacheDirectory` under an already-ignored directory (e.g. `build/.phpunit.cache`) or ignore `.phpunit.cache`.
- Remove `assert.*` `<ini>` entries (deprecated in PHP 8.3); use `zend.assertions`.
