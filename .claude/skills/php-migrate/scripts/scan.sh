#!/usr/bin/env bash
# Detect remaining incompatibilities: PHPCompatibility (PHP_CodeSniffer) and PHPStan with
# deprecation rules, merged into one JSON summary. Waivers in <state>/waivers.json are applied.

if [[ "${1:-}" == "--help" || $# -lt 4 ]]; then
    cat >&2 <<'EOF'
Usage: scan.sh --floor <min-php> --target <max-php> [--label NAME] [--level N]

  --floor/--target  PHPCompatibility testVersion floor-target; PHPStan phpVersion {min, max}
                    (PHPStan cannot target below 7.1, so its min is clamped to 7.1)
  --label           report name (default: scan)
  --level           PHPStan level (default 0)
Waivers for confirmed false positives: <state>/waivers.json, format in scripts/lib/summarize-scan.php.
Full reports: <state>/reports/phpcompat-<label>.json, phpstan-<label>.json.
Exit 0 nothing remains, 1 findings remain, 2 tool error, 3 without Docker.
EOF
    exit 2
fi

source "$(dirname "$0")/env.sh"

floor="" target="" label="scan" level=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --floor) floor="$2"; shift 2 ;;
        --target) target="$2"; shift 2 ;;
        --label) label="$2"; shift 2 ;;
        --level) level="$2"; shift 2 ;;
        *) phpm_die "$PHPM_EXIT_TOOL_ERROR" "unknown option: $1" ;;
    esac
done
[[ "$floor" =~ ^[0-9]+\.[0-9]+ && "$target" =~ ^[0-9]+\.[0-9]+ ]] \
    || phpm_die "$PHPM_EXIT_TOOL_ERROR" "--floor and --target must look like 8.5"

phpm_require_docker
[[ -x "$PHPM_CACHE_DIR/tools/vendor/bin/phpcs" ]] || "$PHPM_SKILL_DIR/scripts/tools.sh" >/dev/null

floor_mm="$(cut -d. -f1-2 <<<"$floor")"
target_mm="$(cut -d. -f1-2 <<<"$target")"
test_version="$floor_mm"
[[ "$floor_mm" != "$target_mm" ]] && test_version="$floor_mm-$target_mm"

stan_id() { local major minor; IFS=. read -r major minor _ <<<"$1"; printf '%d' $(( major * 10000 + minor * 100 )); }
stan_min="$(stan_id "$floor")"
stan_max="$(stan_id "$target")"
(( stan_min < 70100 )) && stan_min=70100
(( stan_max < stan_min )) && stan_max=$stan_min

paths=()
while IFS= read -r path; do
    [[ -n "$path" ]] && paths+=("/app/$path")
done < <(phpm_run "$PHPM_TOOLS_PHP" php -r \
    'require "/skill/scripts/lib/paths.php"; echo implode("\n", phpm_paths("/app")), "\n";')
[[ ${#paths[@]} -gt 0 ]] || phpm_die "$PHPM_EXIT_TOOL_ERROR" "no PHP source paths found"

{
    echo "includes:"
    echo "    - /cache/tools/vendor/phpstan/phpstan-deprecation-rules/rules.neon"
    echo "parameters:"
    echo "    level: $level"
    echo "    phpVersion:"
    echo "        min: $stan_min"
    echo "        max: $stan_max"
    echo "    tmpDir: /state/phpstan-tmp"
    echo "    paths:"
    for path in "${paths[@]}"; do echo "        - $path"; done
    if [[ -d "$PHPM_PROJECT_DIR/vendor" ]]; then
        echo "    scanDirectories:"
        echo "        - /app/vendor"
    fi
} > "$PHPM_STATE_DIR/phpstan.neon"

reports="$PHPM_STATE_DIR/reports"
set +e

phpm_run "$PHPM_TOOLS_PHP" php -d memory_limit=-1 /cache/tools/vendor/bin/phpcs -q \
    --standard=PHPCompatibility --runtime-set testVersion "$test_version" --extensions=php \
    --ignore='*/vendor/*' --report=json --report-file="/state/reports/phpcompat-$label.json" \
    "${paths[@]}" 2>"$reports/phpcompat-$label.stderr"
phpcs_code=$?
if (( phpcs_code & 16 || phpcs_code & 64 )); then
    tail -n 20 "$reports/phpcompat-$label.stderr" >&2
    phpm_die "$PHPM_EXIT_TOOL_ERROR" "PHP_CodeSniffer failed (exit $phpcs_code)"
fi

phpm_run "$PHPM_TOOLS_PHP" php -d memory_limit=-1 /cache/tools/vendor/bin/phpstan analyse \
    -c /state/phpstan.neon --error-format=json --no-progress \
    >"$reports/phpstan-$label.json" 2>"$reports/phpstan-$label.stderr"
stan_code=$?
if (( stan_code > 1 )); then
    tail -n 20 "$reports/phpstan-$label.stderr" >&2
    phpm_die "$PHPM_EXIT_TOOL_ERROR" "PHPStan failed (exit $stan_code)"
fi

phpm_run "$PHPM_TOOLS_PHP" php /skill/scripts/lib/summarize-scan.php \
    "/state/reports/phpcompat-$label.json" "/state/reports/phpstan-$label.json" /state/waivers.json
exit $?
