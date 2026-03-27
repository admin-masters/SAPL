#!/usr/bin/env bash
set -euo pipefail

FTP_SERVER="${FTP_SERVER:?FTP_SERVER is required}"
FTP_USERNAME="${FTP_USERNAME:?FTP_USERNAME is required}"
FTP_PASSWORD="${FTP_PASSWORD:?FTP_PASSWORD is required}"
FTP_SERVER_DIR="${FTP_SERVER_DIR:?FTP_SERVER_DIR is required}"
FTP_PORT="${FTP_PORT:-22}"
GITHUB_AFTER_SHA="${GITHUB_AFTER_SHA:?GITHUB_AFTER_SHA is required}"
IGNORE_FILE="${IGNORE_FILE:-.deployignore}"

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
trap 'rm -f "$lftp_script"' EXIT

append_cmd() {
  printf '%s\n' "$1" >> "$lftp_script"
}

queue_upload() {
  local path="$1"
  local remote_dir

  [[ -f "$path" ]] || return 0
  should_exclude "$path" && return 0

  remote_dir="$(dirname "$path")"
  append_cmd "mkdir -p \"$(lftp_escape "$remote_dir")\""
  append_cmd "put -O \"$(lftp_escape "$remote_dir")\" \"$(lftp_escape "$path")\""
  upload_count+=1
}

queue_delete() {
  local path="$1"

  should_exclude "$path" && return 0

  append_cmd "rm -f \"$(lftp_escape "$path")\""
  delete_count+=1
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

if is_zero_sha "${GITHUB_BEFORE_SHA:-}" || ! git cat-file -e "${GITHUB_BEFORE_SHA}^{commit}" 2>/dev/null; then
  echo "Running initial full deploy from tracked files."
  while IFS= read -r -d '' path; do
    queue_upload "$path"
  done < <(git ls-files -z)
else
  echo "Deploying changed files from ${GITHUB_BEFORE_SHA} to ${GITHUB_AFTER_SHA}."
  while IFS= read -r -d '' status; do
    case "$status" in
      R*|C*)
        IFS= read -r -d '' old_path
        IFS= read -r -d '' new_path
        queue_delete "$old_path"
        queue_upload "$new_path"
        ;;
      D)
        IFS= read -r -d '' path
        queue_delete "$path"
        ;;
      *)
        IFS= read -r -d '' path
        queue_upload "$path"
        ;;
    esac
  done < <(git diff --name-status -z --find-renames "${GITHUB_BEFORE_SHA}" "${GITHUB_AFTER_SHA}" --)
fi

if (( upload_count == 0 && delete_count == 0 )); then
  echo "No deployable file changes detected after exclusions."
  exit 0
fi

append_cmd "bye"

echo "Queued ${upload_count} uploads and ${delete_count} deletions."
lftp -f "$lftp_script"
