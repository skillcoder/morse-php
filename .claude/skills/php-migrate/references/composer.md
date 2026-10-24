# Composer during the migration

Read this file in the dependencies and housekeeping phases.

## Commands (run through `scripts/php.sh <target> -- …`)
- `composer prohibits php <target>` (alias `why-not`) — packages that block the target PHP. Add `-r` for the full tree. A **runtime** (`require`) blocker needs the user's decision (upgrade, replace, or stop); a dev blocker is upgraded with the rest of `require-dev`.
- `composer outdated --direct --format=json` — what can move.
- `composer update -W --no-interaction` — re-resolve everything; `-W` lets transitive dependencies move.
- `composer validate --strict --no-check-publish` — warnings fail too.
- `composer audit --format=json` — known advisories in the new lock.
- `composer check-platform-reqs --lock` — confirms the lock installs on the image's real PHP.

## Editing `composer.json`
Allowed without asking: `require.php`, `config.platform.php`, the PHPUnit constraint in `require-dev`, `ext-*` entries. Anything else (removing `version`, other packages, scripts, metadata) only with the user's approval from the preflight or a later question.
- `require.php`: keep the project's style. `">=5.6.0"` becomes `">=8.5"`; `"^7.4"` becomes `"^8.5"`; a range (`"^7.4 || ^8.0"`) becomes the new supported range.
- `config.platform.php`: if present, set it to the new minimum (e.g. `"8.5.0"`) so the lock resolves for the oldest supported PHP. If absent and the minimum is lower than the target, add it; otherwise leave it out.
- Libraries that commit `composer.lock` get it regenerated (`composer update -W`); never hand-edit the lock.
- `"version"` field: `composer validate --strict` warns about it; Composer recommends removing it and using git tags.

## Extensions (`ext-*`)
Declare every extension the library code calls. Extensions used only by tests go in `require-dev` — unless the tests already skip when the extension is missing (`markTestSkipped()`), which means the author made it optional; then leave it out and say so in the report.

| Code uses | Requirement |
|---|---|
| `mb_*` | `ext-mbstring` |
| `finfo`, `FILEINFO_*`, `mime_content_type()` | `ext-fileinfo` |
| `ctype_*` | `ext-ctype` |
| `iconv*` | `ext-iconv` |
| `json_*` | `ext-json` only when the minimum PHP < 8.0 (always available since 8.0) |
| `Normalizer`, `IntlDateFormatter`, `NumberFormatter`, `grapheme_*`, `idn_*` | `ext-intl` |
| `image*`, `getimagesize()` with GD | `ext-gd` |
| `curl_*` | `ext-curl` |
| `simplexml_*`, `SimpleXMLElement` / `DOMDocument` / `xml_*` | `ext-simplexml` / `ext-dom` / `ext-xml` |
| `PDO`, `pdo_*` drivers | `ext-pdo`, `ext-pdo_mysql`, … |
| `openssl_*` | `ext-openssl` |
| `sodium_*` | `ext-sodium` |
| `bc*` / `gmp_*` | `ext-bcmath` / `ext-gmp` |
| `ZipArchive` | `ext-zip` |
Removed from core over time (now PECL): `mysql` (7.0), `mcrypt` (7.2), `interbase`, `recode`, `wddx` (7.4), `imap`, `oci8`, `pdo_oci`, `pspell` (8.4).

## Security advisory blocking (Composer ≥ 2.9)
`update` refuses versions with known advisories. For the **baseline** on an old lock, `test.sh --no-blocking` is acceptable. The migrated project must resolve without it — if it cannot, report the advisory to the user.
