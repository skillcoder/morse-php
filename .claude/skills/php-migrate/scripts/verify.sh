#!/usr/bin/env bash
# The gate: lint, composer validate, tests and scan on the target PHP (and the minimum, if different).

if [[ "${1:-}" == "--help" || $# -lt 4 ]]; then
    cat >&2 <<'EOF'
Usage: verify.sh --floor <min-php> --target <max-php> [--skip-scan] [--level N]

Runs, in order: lint.sh <target> [and <floor>], composer validate --strict, test.sh <target>
[and <floor>], scan.sh --floor --target. Prints one JSON object with each step's result and
"green". Exit 0 only when every step passed; 1 otherwise; 3 without Docker.
EOF
    exit 2
fi

source "$(dirname "$0")/env.sh"

floor="" target="" scan=1 level=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --floor) floor="$2"; shift 2 ;;
        --target) target="$2"; shift 2 ;;
        --skip-scan) scan=0; shift ;;
        --level) level="$2"; shift 2 ;;
        *) phpm_die "$PHPM_EXIT_TOOL_ERROR" "unknown option: $1" ;;
    esac
done
phpm_require_docker

scripts="$PHPM_SKILL_DIR/scripts"
versions=("$target")
[[ "$floor" != "$target" ]] && versions+=("$floor")
green=true
out="{"

step() { # <json key> <command...>: append "key": {"exit": n, "result": <json or null>}
    local key="$1"; shift
    local result code
    set +e
    result="$("$@")"
    code=$?
    set -e
    [[ $code -eq 0 ]] || green=false
    [[ -n "$result" && "$result" == "{"* ]] || result="null"
    out+="\"$key\": {\"exit\": $code, \"result\": $result},"
}

for version in "${versions[@]}"; do
    step "lint_$version" "$scripts/lint.sh" "$version"
done

set +e
validate="$("$scripts/php.sh" "$target" -- composer validate --strict --no-check-publish 2>&1)"
validate_code=$?
set -e
[[ $validate_code -eq 0 ]] || green=false
validate_text="$(printf '%s' "$validate" | tail -n 15 | sed 's/\\/\\\\/g; s/"/\\"/g' | awk '{printf "%s\\n", $0}')"
out+="\"composer_validate\": {\"exit\": $validate_code, \"output\": \"$validate_text\"},"

for version in "${versions[@]}"; do
    step "tests_$version" "$scripts/test.sh" "$version" --label "verify-$version"
done

if [[ $scan -eq 1 ]]; then
    step "scan" "$scripts/scan.sh" --floor "$floor" --target "$target" --label verify --level "$level"
fi

out+="\"green\": $green}"
printf '%s\n' "$out"
[[ "$green" == true ]] && exit "$PHPM_EXIT_OK"
exit "$PHPM_EXIT_FINDINGS"
