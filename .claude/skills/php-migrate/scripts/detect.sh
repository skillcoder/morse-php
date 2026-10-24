#!/usr/bin/env bash
# Preflight: report Docker status, git state and every signal of the project's current PHP version.
# Always exits 0 so it can run first; read "docker" and "git" in the JSON to decide whether to stop.

if [[ "${1:-}" == "--help" ]]; then
    cat >&2 <<'EOF'
Usage: detect.sh

Run from the project root (or set PHPM_PROJECT_DIR). Prints JSON:
  docker   "ok" | "not-installed" | "not-running"
  git      {"repo", "branch", "default_branch", "dirty"}
  detected version signals from composer.json/lock, CI files, Dockerfiles, .php-version, .tool-versions
           (null when Docker is unavailable, because parsing runs in php:8.5-cli)
Always exits 0.
EOF
    exit 0
fi

source "$(dirname "$0")/env.sh"
set +e

json_str() { local s="${1//\\/\\\\}"; s="${s//\"/\\\"}"; printf '"%s"' "$s"; }

if ! command -v docker >/dev/null 2>&1; then
    docker_status="not-installed"
elif ! docker info >/dev/null 2>&1; then
    docker_status="not-running"
else
    docker_status="ok"
fi

repo=false branch="" default_branch="" dirty=false
if git -C "$PHPM_PROJECT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    repo=true
    branch="$(git -C "$PHPM_PROJECT_DIR" branch --show-current)"
    default_branch="$(git -C "$PHPM_PROJECT_DIR" symbolic-ref --quiet --short refs/remotes/origin/HEAD 2>/dev/null)"
    default_branch="${default_branch#origin/}"
    if [[ -z "$default_branch" ]]; then
        for candidate in main master; do
            if git -C "$PHPM_PROJECT_DIR" show-ref --verify --quiet "refs/heads/$candidate"; then
                default_branch="$candidate"
                break
            fi
        done
    fi
    # Ignore the skill's own files so a freshly added skill does not count as a dirty tree.
    if [[ -n "$(git -C "$PHPM_PROJECT_DIR" status --porcelain -- . ':!.claude/skills/php-migrate')" ]]; then
        dirty=true
    fi
fi

detected="null"
if [[ "$docker_status" == "ok" ]]; then
    detected="$(docker run --rm -v "$PHPM_PROJECT_DIR":/app:ro -v "$PHPM_SKILL_DIR":/skill:ro \
        "php:$PHPM_TOOLS_PHP-cli" php /skill/scripts/lib/detect.php /app 2>/dev/null)" || detected="null"
    [[ -n "$detected" ]] || detected="null"
fi

printf '{\n  "docker": "%s",\n  "git": {"repo": %s, "branch": %s, "default_branch": %s, "dirty": %s},\n  "state_dir": %s,\n  "detected": %s\n}\n' \
    "$docker_status" "$repo" "$(json_str "$branch")" "$(json_str "$default_branch")" "$dirty" \
    "$(json_str "$PHPM_STATE_DIR")" "$detected"
exit 0
