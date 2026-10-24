#!/usr/bin/env bash
# Run any command (php, composer, vendor/bin/...) in the php-migrate image for a PHP version.
# The project is mounted at /app and is the working directory.

if [[ "${1:-}" == "--help" || $# -lt 3 || "${2:-}" != "--" ]]; then
    cat >&2 <<'EOF'
Usage: php.sh <php-version> -- <command> [args...]

Examples:
  php.sh 8.5 -- php -v
  php.sh 8.5 -- composer prohibits php 8.5
  php.sh 5.6 -- composer install
Paths inside the container: project /app, cache /cache, state /state, skill /skill.
The command's own exit code is returned. Exit 3 when Docker is unavailable.
EOF
    exit 2
fi

source "$(dirname "$0")/env.sh"

version="$1"
shift 2
phpm_require_docker
phpm_ensure_image "$version"
phpm_run "$version" "$@"
