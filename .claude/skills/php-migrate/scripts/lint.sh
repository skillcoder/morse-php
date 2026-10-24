#!/usr/bin/env bash
# `php -l` every project file in the image for a PHP version, with all error levels on,
# so compile-time deprecations show up too.

if [[ "${1:-}" == "--help" || $# -lt 1 ]]; then
    cat >&2 <<'EOF'
Usage: lint.sh <php-version>

Lints the project paths (composer autoload + tests) with php -l in php-migrate:<php-version>.
Prints JSON {php, files, problem_files, problems[]}; a copy goes to <state>/reports/lint-<version>.json.
Exit 0 clean, 1 problems (syntax errors or compile-time deprecations), 3 without Docker.
EOF
    exit 2
fi

source "$(dirname "$0")/env.sh"

version="$1"
phpm_require_docker
phpm_ensure_image "$version"

set +e
phpm_run "$version" php /skill/scripts/lib/lint.php /app | tee "$PHPM_STATE_DIR/reports/lint-$version.json"
exit "${PIPESTATUS[0]}"
