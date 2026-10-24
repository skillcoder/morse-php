#!/usr/bin/env bash
# Install the pinned third-party tools (Rector, PHP_CodeSniffer + PHPCompatibility,
# PHPStan + deprecation rules) into the cache, outside the project. Idempotent.

if [[ "${1:-}" == "--help" ]]; then
    cat >&2 <<'EOF'
Usage: tools.sh [--reinstall]

Installs pinned tools into ~/.cache/php-migrate/tools using the PHP 8.5 image.
The project's composer.json is never touched.
Prints JSON with the installed versions. Exit 2 on install errors, 3 without Docker.
EOF
    exit 2
fi

source "$(dirname "$0")/env.sh"

phpm_require_docker
phpm_ensure_image "$PHPM_TOOLS_PHP"

tools_dir="$PHPM_CACHE_DIR/tools"
manifest="$(cat <<EOF
{
    "name": "php-migrate/tools",
    "description": "Pinned migration tools used by the php-migrate skill",
    "require": {
        "rector/rector": "$PHPM_RECTOR_VERSION",
        "squizlabs/php_codesniffer": "$PHPM_PHPCS_VERSION",
        "phpcompatibility/php-compatibility": "$PHPM_PHPCOMPAT_VERSION",
        "phpstan/phpstan": "$PHPM_PHPSTAN_VERSION",
        "phpstan/phpstan-deprecation-rules": "$PHPM_PHPSTAN_DEPRECATION_VERSION"
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    }
}
EOF
)"

if [[ "${1:-}" == "--reinstall" ]] || [[ ! -f "$tools_dir/composer.json" ]] \
    || [[ "$(cat "$tools_dir/composer.json")" != "$manifest" ]] || [[ ! -d "$tools_dir/vendor" ]]; then
    printf '%s\n' "$manifest" > "$tools_dir/composer.json"
    rm -f "$tools_dir/composer.lock"
    phpm_log "installing tools into $tools_dir"
    docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/cache/composer \
        -v "$PHPM_CACHE_DIR":/cache -w /cache/tools "$(phpm_image_tag "$PHPM_TOOLS_PHP")" \
        composer update --no-interaction --no-progress --prefer-dist >&2 \
        || phpm_die "$PHPM_EXIT_TOOL_ERROR" "tool installation failed"
fi

docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -e TOOLS_DIR="$tools_dir" \
    -v "$PHPM_CACHE_DIR":/cache -w /cache/tools \
    "$(phpm_image_tag "$PHPM_TOOLS_PHP")" php -r '
        $lock = json_decode(file_get_contents("composer.lock"), true);
        $versions = [];
        foreach ($lock["packages"] as $package) {
            $versions[$package["name"]] = $package["version"];
        }
        $wanted = ["rector/rector", "squizlabs/php_codesniffer", "phpcompatibility/php-compatibility",
                   "phpcsstandards/phpcsutils", "phpstan/phpstan", "phpstan/phpstan-deprecation-rules"];
        $out = ["tools_dir" => getenv("TOOLS_DIR"), "php" => PHP_VERSION, "versions" => []];
        foreach ($wanted as $name) {
            $out["versions"][$name] = $versions[$name] ?? null;
        }
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    ' 2>/dev/null || phpm_die "$PHPM_EXIT_TOOL_ERROR" "could not read installed tool versions"
