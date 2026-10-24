#!/usr/bin/env bash
# Build the cached Docker image `php-migrate:<version>`: the official php:<version>-cli
# image plus Composer, git and unzip. Idempotent: an existing image is reused.

if [[ "${1:-}" == "--help" || $# -lt 1 ]]; then
    cat >&2 <<'EOF'
Usage: image.sh <php-version> [--ext ext1,ext2] [--rebuild]

Builds php-migrate:<php-version> from the official php:<php-version>-cli image.
  <php-version>  e.g. 5.6, 7.4, 8.5 or an exact patch release such as 8.5.12
  --ext          extra extensions to install with docker-php-ext-install
  --rebuild      rebuild even if the image exists
Prints JSON: {"image", "php", "built"}. Exit 3 when Docker is unavailable, 2 on build errors.
EOF
    exit 2
fi

source "$(dirname "$0")/env.sh"

version="$1"; shift
exts=""
rebuild=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --ext) exts="${2//,/ }"; shift 2 ;;
        --rebuild) rebuild=1; shift ;;
        *) phpm_die "$PHPM_EXIT_TOOL_ERROR" "unknown option: $1" ;;
    esac
done

phpm_require_docker
tag="$(phpm_image_tag "$version")"
built=false

if [[ $rebuild -eq 1 ]] || ! docker image inspect "$tag" >/dev/null 2>&1; then
    # Composer 2.3+ needs PHP 7.2.5; older PHP gets the 2.2 LTS line.
    composer_image="composer:2"
    if (( $(phpm_version_id "$version") < 702 )); then
        composer_image="composer:2.2"
    fi

    phpm_log "building $tag from php:$version-cli (Composer from $composer_image)"
    docker build --quiet -t "$tag" - >&2 <<EOF || phpm_die "$PHPM_EXIT_TOOL_ERROR" "image build failed for php:$version-cli"
FROM $composer_image AS composer
FROM php:$version-cli
COPY --from=composer /usr/bin/composer /usr/bin/composer
# EOL Debian releases (stretch, buster) moved to archive.debian.org.
RUN set -eux; \
    if ! apt-get update; then \
        sed -i -e 's|deb.debian.org|archive.debian.org|g' \
               -e 's|security.debian.org/debian-security|archive.debian.org/debian-security|g' \
               -e 's|security.debian.org|archive.debian.org/debian-security|g' \
               -e '/-updates/d' /etc/apt/sources.list; \
        apt-get -o Acquire::Check-Valid-Until=false update; \
    fi; \
    apt-get install -y --no-install-recommends --allow-unauthenticated git unzip; \
    rm -rf /var/lib/apt/lists/*
RUN if [ -n "$exts" ]; then docker-php-ext-install $exts; fi
RUN git config --system --add safe.directory '*'
EOF
    built=true
fi

php_version="$(docker run --rm "$tag" php -r 'echo PHP_VERSION;')"
printf '{"image": "%s", "php": "%s", "built": %s}\n' "$tag" "$php_version" "$built"
