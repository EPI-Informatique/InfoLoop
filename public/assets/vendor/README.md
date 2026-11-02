# Vendored third-party assets

This directory holds pinned copies of third-party JS/CSS libraries so the
admin UI works fully offline (air-gapped signage servers) and the version is
controlled by git.

## TinyMCE (WYSIWYG page editor)

- **Where:** `public/assets/vendor/tinymce/tinymce.min.js`
  (+ the `plugins/`, `skins/`, `langs/` subfolders as needed).
- **License:** LGPL-2.1-or-later (TinyMCE is dual-licensed; the community
  edition used here is LGPL). Keep the license header in the file.
- **Upgrade procedure** (when a new TinyMCE release comes out):
  1. Download the `tinymce_<version>_js.zip` (or the npm tarball) from
     https://www.tiny.cloud/get-tiny/ or https://github.com/tinymce/tinymce/releases
  2. Replace the contents of `public/assets/vendor/tinymce/` with the new
     `js/tinymce/` tree.
  3. Update the version constant in `public/assets/admin.js`
     (`TINYMCE_VERSION`) and the `<script src>` URL in
     `app/views/admin/pages/form.php` if the path changed.
  4. Clear browser caches (nginx serves `/assets/` with `expires 7d` —
     either bump the URL with a `?v=` query or reload clients).
  5. Smoke-test the page editor: bold/italic, image paste (Ctrl+V),
     link insertion, save → revision created.

The file `tinymce.min.js` is intentionally **not** committed here if it
exceeds a few MB; fetch it with:

```bash
curl -L -o /tmp/tinymce.zip https://github.com/tinymce/tinymce/releases/download/v7.5.0/tinymce_7.5.0.zip
unzip -o /tmp/tinymce.zip 'js/tinymce/*' -d public/assets/vendor/
mv public/assets/vendor/js/tinymce public/assets/vendor/tinymce
```

(pin the exact version you deploy in git so upgrades are explicit commits).
