#!/usr/bin/env bash
# Runs ON the server. Replaces the app in APP_DIR with the freshly extracted
# release in NEW_DIR (no rsync needed), preserving server-owned files:
#   .env  storage/  public/storage  .well-known/  public/.well-known/  cgi-bin/  error_log
# Usage: bash NEW_DIR/deploy/cpanel/swap-release.sh NEW_DIR APP_DIR
set -euo pipefail
NEW_DIR="${1:-}"; APP_DIR="${2:-}"

# Safety: this script deletes files, so refuse anything but two distinct,
# absolute, non-trivial directories (never /, $HOME or public_html itself).
for dir in "$NEW_DIR" "$APP_DIR"; do
  case "$dir" in
    /*/*) ;;
    *) echo "Refusing unsafe path: '$dir'"; exit 1 ;;
  esac
  case "${dir%/}" in
    "${HOME%/}"|"${HOME%/}/public_html") echo "Refusing to operate on $dir"; exit 1 ;;
  esac
done
[ "${NEW_DIR%/}" != "${APP_DIR%/}" ] || { echo "NEW_DIR and APP_DIR must differ"; exit 1; }
[ -f "$NEW_DIR/artisan" ] || { echo "Release in $NEW_DIR looks incomplete"; exit 1; }
NEW_DIR="${NEW_DIR%/}"; APP_DIR="${APP_DIR%/}"

source "$NEW_DIR/deploy/cpanel/php.sh"
mkdir -p "$APP_DIR/public"

# Maintenance mode via the currently installed code (no-op on first deploy).
if [ -f "$APP_DIR/artisan" ] && [ -f "$APP_DIR/vendor/autoload.php" ]; then
  (cd "$APP_DIR" && "$PHP" artisan down --retry=15 >/dev/null 2>&1) || true
fi

shopt -s dotglob nullglob

# 1. Remove the old release (except protected paths).
for item in "$APP_DIR"/*; do
  case "$(basename "$item")" in
    .env|storage|public|.well-known|cgi-bin|error_log) continue ;;
  esac
  rm -rf "$item"
done
for item in "$APP_DIR/public"/*; do
  case "$(basename "$item")" in
    storage|.well-known|error_log) continue ;;
  esac
  rm -rf "$item"
done

# 2. Move the new release in.
for item in "$NEW_DIR"/*; do
  case "$(basename "$item")" in
    public|.env|storage) continue ;;
  esac
  mv "$item" "$APP_DIR/"
done
for item in "$NEW_DIR/public"/*; do
  case "$(basename "$item")" in
    storage|.well-known) continue ;;
  esac
  mv "$item" "$APP_DIR/public/"
done

rm -rf "$NEW_DIR"
echo "Release swapped into $APP_DIR"
