#!/usr/bin/env bash
# Shared settings for the php-migrate scripts. Source it; do not run it.
# Pinned versions are explained in references/tooling.md.
# shellcheck disable=SC2034  # variables are used by the scripts that source this file

set -euo pipefail

PHPM_SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHPM_PROJECT_DIR="$(cd "${PHPM_PROJECT_DIR:-$PWD}" && pwd)"
PHPM_CACHE_DIR="${XDG_CACHE_HOME:-$HOME/.cache}/php-migrate"

# PHP version of the image that runs Rector, PHPCS/PHPCompatibility and PHPStan.
PHPM_TOOLS_PHP="${PHPM_TOOLS_PHP:-8.5}"

# Pinned tool versions.
PHPM_RECTOR_VERSION="2.7.0"
PHPM_PHPCS_VERSION="4.0.4"
PHPM_PHPCOMPAT_VERSION="10.0.0-alpha2"
PHPM_PHPSTAN_VERSION="2.3.2"
PHPM_PHPSTAN_DEPRECATION_VERSION="^2.0"

# Exit codes shared by all scripts.
PHPM_EXIT_OK=0
PHPM_EXIT_FINDINGS=1
PHPM_EXIT_TOOL_ERROR=2
PHPM_EXIT_NO_DOCKER=3

# State and reports live inside the git dir, so they are never committed.
if git_dir="$(git -C "$PHPM_PROJECT_DIR" rev-parse --absolute-git-dir 2>/dev/null)"; then
    PHPM_STATE_DIR="$git_dir/php-migrate"
else
    PHPM_STATE_DIR="$PHPM_CACHE_DIR/state/$(printf '%s' "$PHPM_PROJECT_DIR" | cksum | cut -d' ' -f1)"
fi
unset git_dir

mkdir -p "$PHPM_CACHE_DIR/composer" "$PHPM_CACHE_DIR/tools" "$PHPM_STATE_DIR/reports"

phpm_log() { printf 'php-migrate: %s\n' "$*" >&2; }

phpm_die() { # <exit code> <message>
    local code="$1"; shift
    phpm_log "$*"
    exit "$code"
}

phpm_require_docker() {
    command -v docker >/dev/null 2>&1 || phpm_die "$PHPM_EXIT_NO_DOCKER" "docker is not installed"
    docker info >/dev/null 2>&1 || phpm_die "$PHPM_EXIT_NO_DOCKER" "docker is not running"
}

# Normalise "8", "8.5", "8.5.12", "5.6.0" to an image tag ("8.5", "8.5.12").
phpm_image_tag() { printf 'php-migrate:%s' "$1"; }

# Major.minor as a comparable integer: 5.6 -> 506, 8.5.12 -> 805.
phpm_version_id() {
    local major minor
    IFS=. read -r major minor _ <<<"$1"
    printf '%d' $(( major * 100 + ${minor:-0} ))
}

# Run a command in the php-migrate image for a PHP version.
# Mounts: project -> /app (cwd), cache -> /cache, state -> /state, skill -> /skill (read-only).
phpm_run() { # <php version> <cmd...>
    local version="$1"; shift
    docker run --rm \
        --user "$(id -u):$(id -g)" \
        -e HOME=/tmp \
        -e COMPOSER_HOME=/cache/composer \
        -e COMPOSER_NO_INTERACTION=1 \
        -v "$PHPM_PROJECT_DIR":/app \
        -v "$PHPM_CACHE_DIR":/cache \
        -v "$PHPM_STATE_DIR":/state \
        -v "$PHPM_SKILL_DIR":/skill:ro \
        -w /app \
        "$(phpm_image_tag "$version")" "$@"
}

# Build the image for a version if it does not exist yet.
phpm_ensure_image() { # <php version>
    docker image inspect "$(phpm_image_tag "$1")" >/dev/null 2>&1 \
        || "$PHPM_SKILL_DIR/scripts/image.sh" "$1" >/dev/null
}
