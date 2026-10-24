#!/usr/bin/env bash
# Install dependencies and run PHPUnit in the image for a PHP version with every error level on.

if [[ "${1:-}" == "--help" || $# -lt 1 ]]; then
    cat >&2 <<'EOF'
Usage: test.sh <php-version> [--label NAME] [--no-install] [--no-blocking]

  --label        report name (default: the PHP version), e.g. "baseline"
  --no-install   skip `composer install`
  --no-blocking  allow package versions with security advisories (baseline runs on old locks only;
                 never use it for the migrated project)
Runs: php -d error_reporting=-1 -d display_errors=1 -d zend.assertions=1 vendor/bin/phpunit
and, when PHPUnit supports them, --fail-on-deprecation --fail-on-warning --fail-on-notice.
Prints a JSON summary. Full output: <state>/reports/phpunit-<label>.txt and junit-<label>.xml.
Exit 0 when the suite passes, 1 when it fails, 2 when dependencies or PHPUnit cannot run, 3 without Docker.
EOF
    exit 2
fi

source "$(dirname "$0")/env.sh"

version="$1"; shift
label="$version"
install=1
blocking_env=()
while [[ $# -gt 0 ]]; do
    case "$1" in
        --label) label="$2"; shift 2 ;;
        --no-install) install=0; shift ;;
        --no-blocking) blocking_env=(env COMPOSER_NO_BLOCKING=1); shift ;;
        *) phpm_die "$PHPM_EXIT_TOOL_ERROR" "unknown option: $1" ;;
    esac
done

phpm_require_docker
phpm_ensure_image "$version"
phpm_ensure_image "$PHPM_TOOLS_PHP"
reports="$PHPM_STATE_DIR/reports"
console="$reports/phpunit-$label.txt"

if [[ $install -eq 1 ]]; then
    if [[ -f "$PHPM_PROJECT_DIR/composer.lock" ]]; then
        composer_cmd=(composer install --no-interaction --no-progress --prefer-dist)
    else
        composer_cmd=(composer update --no-interaction --no-progress --prefer-dist)
    fi
    # ${a[@]+"${a[@]}"} keeps an empty array safe under `set -u` in bash 3.2 (macOS /bin/bash).
    if ! phpm_run "$version" ${blocking_env[@]+"${blocking_env[@]}"} "${composer_cmd[@]}" \
        >"$reports/composer-$label.txt" 2>&1; then
        tail -n 25 "$reports/composer-$label.txt" >&2
        phpm_die "$PHPM_EXIT_TOOL_ERROR" "composer could not install dependencies on PHP $version (see $reports/composer-$label.txt)"
    fi
fi

[[ -f "$PHPM_PROJECT_DIR/vendor/bin/phpunit" ]] || phpm_die "$PHPM_EXIT_TOOL_ERROR" "vendor/bin/phpunit not found"

phpunit_version="$(phpm_run "$version" php vendor/bin/phpunit --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -n1)"
flags=(--colors=never --log-junit "/state/reports/junit-$label.xml")
phpunit_help="$(phpm_run "$version" php vendor/bin/phpunit --help 2>/dev/null)"
# Coverage reports configured without Xdebug/PCOV make PHPUnit >= 10 run no tests at all.
if grep -q -- '--no-coverage' <<<"$phpunit_help"; then
    flags+=(--no-coverage)
fi
if grep -q -- '--fail-on-deprecation' <<<"$phpunit_help"; then
    flags+=(--fail-on-deprecation --fail-on-warning --fail-on-notice
            --display-deprecations --display-warnings --display-notices)
fi

set +e
phpm_run "$version" php -d error_reporting=-1 -d display_errors=1 -d zend.assertions=1 \
    vendor/bin/phpunit "${flags[@]}" >"$console" 2>&1
code=$?
php_version="$(phpm_run "$version" php -r 'echo PHP_VERSION;')"

phpm_run "$PHPM_TOOLS_PHP" php /skill/scripts/lib/summarize-tests.php \
    "/state/reports/junit-$label.xml" "/state/reports/phpunit-$label.txt" "$code" "$php_version" "$phpunit_version"
if [[ $code -ne 0 ]]; then
    tail -n 40 "$console" >&2
    exit "$PHPM_EXIT_FINDINGS"
fi
exit "$PHPM_EXIT_OK"
