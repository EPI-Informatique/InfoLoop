#!/usr/bin/env bash
# Upgrade the vendored TinyMCE to a newer release.
#
# Usage:
#   bin/update-tinymce.sh              # upgrade to latest known on npm
#   bin/update-tinymce.sh 7.9.1        # upgrade to a specific version
#
# The script downloads the official npm tarball, replaces
# public/assets/vendor/tinymce/, updates the VERSION file, and prints the
# git commands to commit the change. Nothing is committed automatically.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/assets/vendor/tinymce"
WANT="${1:-$(curl -fsSL https://registry.npmjs.org/tinymce/latest | python3 -c 'import sys,json;print(json.load(sys.stdin)["version"])')}"

if ! command -v curl >/dev/null || ! command -v python3 >/dev/null; then
    echo "error: curl and python3 are required" >&2
    exit 1
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "==> Fetching TinyMCE $WANT (official zip, npm tarball as fallback)"
if curl -fsSL "https://download.tiny.cloud/tinymce/community/tinymce_${WANT}.zip" -o "$TMP/tinymce.zip"; then
    python3 -c "import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])" "$TMP/tinymce.zip" "$TMP"
    SRC="$TMP/tinymce/js/tinymce"          # official zip layout
else
    echo "    official zip unavailable, falling back to npm tarball"
    curl -fsSL "https://registry.npmjs.org/tinymce/-/tinymce-${WANT}.tgz" -o "$TMP/tinymce.tgz"
    tar -xzf "$TMP/tinymce.tgz" -C "$TMP"
    SRC="$TMP/package"                     # npm layout
fi

for f in tinymce.min.js plugins skins themes icons license.md notices.txt; do
    [ -e "$SRC/$f" ] || { echo "error: $f missing in archive (layout: $SRC)" >&2; exit 1; }
done

echo "==> Replacing $DEST (was: $(cat "$DEST/VERSION" 2>/dev/null || echo none))"
rm -rf "$DEST"
mkdir -p "$DEST"
cp -r "$SRC"/tinymce.min.js "$SRC"/tinymce.js "$SRC"/plugins "$SRC"/skins "$SRC"/themes "$SRC"/icons "$SRC"/models "$SRC"/license.md "$SRC"/notices.txt "$DEST/" 2>/dev/null || cp -r "$SRC"/{tinymce.min.js,plugins,skins,themes,icons,license.md,notices.txt} "$DEST/"
[ -d "$SRC/langs" ] && cp -r "$SRC/langs" "$DEST/"
printf '%s\n' "$WANT" > "$DEST/VERSION"

echo "==> Updating TINYMCE_VERSION in public/assets/admin.js"
sed -i.bak -E "s/var TINYMCE_VERSION = '[^']*';/var TINYMCE_VERSION = '$WANT';/" "$ROOT/public/assets/admin.js"
rm -f "$ROOT/public/assets/admin.js.bak"

echo
echo "Done. TinyMCE $WANT installed in public/assets/vendor/tinymce/"
echo "Cache-busting: form.php reads the VERSION file for the ?v= query automatically."
echo
echo "Commit with:"
echo "  git add -A public/assets/vendor/tinymce public/assets/admin.js"
echo "  git commit -m 'chore: upgrade TinyMCE to $WANT'"
echo
echo "Smoke-test the page editor: bold/italic, Ctrl+V image paste, link, save revision."
