#!/usr/bin/env bash
# Resume state for a migration run: key=value lines in <git dir>/php-migrate/state.env.

if [[ "${1:-}" == "--help" || $# -lt 1 ]]; then
    cat >&2 <<'EOF'
Usage: state.sh get [key] | set <key> <value> | reset | path

  get          print all key=value lines (or one value)
  set          store a value, e.g. `state.sh set phase.rewrite done`, `state.sh set target 8.5`
  reset        delete the state file and reports (start over)
  path         print the state directory
Keys used by the skill: from, target, floor, commit_policy, phpunit, phase.<name>.
EOF
    exit 2
fi

source "$(dirname "$0")/env.sh"

file="$PHPM_STATE_DIR/state.env"
touch "$file"

case "$1" in
    get)
        if [[ $# -ge 2 ]]; then
            grep -E "^$2=" "$file" | tail -n1 | cut -d= -f2-
        else
            cat "$file"
        fi
        ;;
    set)
        [[ $# -eq 3 ]] || phpm_die "$PHPM_EXIT_TOOL_ERROR" "usage: state.sh set <key> <value>"
        grep -vE "^$2=" "$file" > "$file.tmp" || true
        printf '%s=%s\n' "$2" "$3" >> "$file.tmp"
        mv "$file.tmp" "$file"
        ;;
    reset)
        rm -rf "${PHPM_STATE_DIR:?}"
        ;;
    path)
        printf '%s\n' "$PHPM_STATE_DIR"
        ;;
    *)
        phpm_die "$PHPM_EXIT_TOOL_ERROR" "unknown command: $1"
        ;;
esac
