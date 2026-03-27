#!/usr/bin/env bash
set -euo pipefail

FTP_SERVER="${FTP_SERVER:?FTP_SERVER is required}"
FTP_USERNAME="${FTP_USERNAME:?FTP_USERNAME is required}"
FTP_PASSWORD="${FTP_PASSWORD:?FTP_PASSWORD is required}"
FTP_SERVER_DIR="${FTP_SERVER_DIR:?FTP_SERVER_DIR is required}"
FTP_PORT="${FTP_PORT:-22}"
GITHUB_AFTER_SHA="${GITHUB_AFTER_SHA:?GITHUB_AFTER_SHA is required}"
IGNORE_FILE="${IGNORE_FILE:-.deployignore}"
DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-.github-actions-deploy-sha}"

normalize_remote_root() {
  local dir="$1"

  dir="${dir%/}"

  if [[ -z "$dir" ]]; then
    printf '/'
  elif [[ "$dir" == "." ]]; then
    printf '.'
  else
    printf '/%s' "${dir#/}"
  fi
}

remote_root="$(normalize_remote_root "$FTP_SERVER_DIR")"

lftp_escape() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '%s' "$value"
}

should_exclude() {
  local path="$1"
  local pattern=""

  [[ -f "$IGNORE_FILE" ]] || return 1

  while IFS= read -r pattern || [[ -n "$pattern" ]]; do
    [[ -z "$pattern" ]] && continue
    [[ "${pattern:0:1}" == "#" ]] && continue

    case "$path" in
      $pattern) return 0 ;;
    esac
  done < "$IGNORE_FILE"

  return 1
}

is_zero_sha() {
  local sha="${1:-}"
  [[ -z "$sha" || "$sha" =~ ^0+$ ]]
}

declare -i upload_count=0
declare -i delete_count=0

lftp_script="$(mktemp)"
stage_root="$(mktemp -d)"
trap 'rm -f "$lftp_script"; rm -rf "$stage_root"' EXIT

append_cmd() {
  printf '%s\n' "$1" >> "$lftp_script"
}

read_remote_deployed_sha() {
  local output=""
  local sha=""

  output="$(
    lftp 2>/dev/null <<EOF || true
set cmd:fail-exit no
set net:max-retries 1
set net:reconnect-interval-base 5
set net:reconnect-interval-max 30
set net:persist-retries 1
set sftp:auto-confirm yes
open -u "$(lftp_escape "$FTP_USERNAME")","$(lftp_escape "$FTP_PASSWORD")" -p "$(lftp_escape "$FTP_PORT")" "sftp://$(lftp_escape "$FTP_SERVER")"
mkdir -p "$(lftp_escape "$remote_root")"
cd "$(lftp_escape "$remote_root")"
cat "$(lftp_escape "$DEPLOY_STATE_FILE")"
bye
EOF
  )"

  sha="$(printf '%s\n' "$output" | tr -d '\r' | tail -n 1 | tr -d '\n')"

  if [[ -n "$sha" ]] && git cat-file -e "${sha}^{commit}" 2>/dev/null; then
    printf '%s' "$sha"
  fi
}

stage_upload() {
  local path="$1"
  local target_path="$stage_root/$path"

  [[ -f "$path" ]] || return 0
  should_exclude "$path" && return 0

  mkdir -p "$(dirname "$target_path")"
  cp -p "$path" "$target_path"
  upload_count+=1
}

queue_delete() {
  local path="$1"

  should_exclude "$path" && return 0

  append_cmd "rm -f \"$(lftp_escape "$path")\""
  delete_count+=1
}

write_stage_deploy_state() {
  local state_path="$stage_root/$DEPLOY_STATE_FILE"

  mkdir -p "$(dirname "$state_path")"
  printf '%s\n' "$GITHUB_AFTER_SHA" > "$state_path"
}

append_cmd "set cmd:fail-exit yes"
append_cmd "set net:max-retries 2"
append_cmd "set net:reconnect-interval-base 5"
append_cmd "set net:reconnect-interval-max 30"
append_cmd "set net:persist-retries 2"
append_cmd "set sftp:auto-confirm yes"
append_cmd "open -u \"$(lftp_escape "$FTP_USERNAME")\",\"$(lftp_escape "$FTP_PASSWORD")\" -p \"$(lftp_escape "$FTP_PORT")\" \"sftp://$(lftp_escape "$FTP_SERVER")\""
append_cmd "mkdir -p \"$(lftp_escape "$remote_root")\""
append_cmd "cd \"$(lftp_escape "$remote_root")\""

if ! git cat-file -e "${GITHUB_AFTER_SHA}^{commit}" 2>/dev/null; then
  echo "Target commit ${GITHUB_AFTER_SHA} is not available locally."
  exit 1
fi

remote_deployed_sha="$(read_remote_deployed_sha || true)"

if [[ -n "$remote_deployed_sha" ]]; then
  echo "Deploying changed files from ${remote_deployed_sha} to ${GITHUB_AFTER_SHA}."
  while IFS= read -r -d '' status; do
    case "$status" in
      R*|C*)
        IFS= read -r -d '' old_path
        IFS= read -r -d '' new_path
        queue_delete "$old_path"
        stage_upload "$new_path"
        ;;
      D)
        IFS= read -r -d '' path
        queue_delete "$path"
        ;;
      *)
        IFS= read -r -d '' path
        stage_upload "$path"
        ;;
    esac
  done < <(git diff --name-status -z --find-renames "${remote_deployed_sha}" "${GITHUB_AFTER_SHA}" --)
else
  echo "Running initial full deploy from tracked files."
  while IFS= read -r -d '' path; do
    stage_upload "$path"
  done < <(git ls-files -z)
fi

if (( upload_count == 0 && delete_count == 0 )) && [[ "${remote_deployed_sha:-}" == "${GITHUB_AFTER_SHA}" ]]; then
  echo "No deployable file changes detected after exclusions."
  exit 0
fi

write_stage_deploy_state
append_cmd "mirror -R --verbose=1 --parallel=1 --no-perms --no-umask \"$(lftp_escape "$stage_root")\" ."
append_cmd "bye"

if (( upload_count == 0 && delete_count == 0 )); then
  echo "No deployable file changes detected after exclusions; updating remote deploy state."
else
  echo "Queued ${upload_count} uploads and ${delete_count} deletions."
fi

lftp -f "$lftp_script"
