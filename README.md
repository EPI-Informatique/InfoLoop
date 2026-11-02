# InfoLoop

Open-source **digital signage** platform for offices, headquarters and campuses.
PHP 8.4+, MariaDB, zero-Composer-dependency MVC framework (PSR-4 autoloader included).

InfoLoop has two sides:

- **Kiosk / signage side** — touch-screen friendly pages shown on a network of screens
  (interactive, or auto-rotating playlists for non-interactive displays).
- **Admin side** — create pages, manage media, screens, locations, playlists, users
  (RBAC + MFA + LDAP/AD), branding and an engagement dashboard.

## Features

- Page types: article (rich HTML), photo gallery (auto/manual), PDF (with start page),
  iframe/web page, MP4 video, YouTube embed, raw HTML/CSS/JS (body + `<head>`), tables, clock with timezones
- BookStack-style **page revisions** — every save is a revision; view, restore, delete
- **Media library** with sharded storage in `public/files`, MIME allow-list, sha256
  de-duplication, search by file *or* page name, sort by upload date / last used / size / name,
  and usage tooltips linking to the pages/revisions using each file (with one-click revision delete)
- **Locations** — per-location content scoping (Location 1 shows web pages, Location 2 loops a video…)
- **Screens** with secret tokens, playlists with per-page durations, per-screen inactivity
  timeout (default 30 s, can be disabled) returning to the default page
- Menu hierarchy via a free-text `menu_path` field (e.g. `Company / HR / Policies`)
- Paste images directly into the editor (Ctrl+V) — stored like any upload
- **Branding**: logo, brand name, slogan, color palette, font (CSS variables)
- **RBAC**: super admin, admin, location admin, editor, viewer; **MFA (TOTP)** for every admin;
  **LDAP / Active Directory** login + group restriction + user sync
- **Engagement dashboard**: views/interactions per page, per location, per screen, daily series
- Mobile-friendly kiosk pages (no-JS fallback at `/page/{slug}`)
- Security: CSRF tokens, argon2id passwords, lockouts, HTML sanitizer, SVG script rejection,
  security headers (CSP etc.), prepared statements everywhere, session hardening

## Requirements

- PHP **8.4+** with `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `dom`, `openssl`, `sodium`
- MariaDB 10.6+ (or MySQL 8+)
- nginx (or Apache with the provided `.htaccess`)
- `ext-ldap` for LDAP/AD login (optional)
- `ext-gd` for image metadata (optional)

## Installation

```bash
# 1. Clone
git clone https://github.com/EPI-Informatique/InfoLoop.git /var/www/infoloop
cd /var/www/infoloop

# 2. Configure
cp .env.example .env
$EDITOR .env          # DB credentials, APP_URL, APP_KEY (optional), LDAP…

# 3. Create the database
mysql -u root -p -e "CREATE DATABASE infoloop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                     CREATE USER 'infoloop'@'localhost' IDENTIFIED BY 'secret';
                     GRANT ALL ON infoloop.* TO 'infoloop'@'localhost';"

# 4. Run migrations + seed (roles, default location, default settings)
php bin/infoloop install

# 5. Create the first super admin (password can be reset later via CLI)
php bin/infoloop user:create admin admin@example.com 'ChangeMe-12345678!' super_admin

# 6. Point nginx at /var/www/infoloop/public (see docs/nginx.conf)
#    and make sure storage/ and public/files/ are writable by PHP-FPM:
chown -R www-data:www-data storage public/files
```

Then either open `/install` in a browser (web installer) or use the CLI above,
sign in at `/admin/login`, and register your first screen at `/admin/screens`.
Open `/?screen=<token>` on the display device.

## CLI reference

```bash
php bin/infoloop doctor                           # check prerequisites (PHP ext, tools, perms, DB)
php bin/infoloop migrate                          # apply pending migrations
php bin/infoloop seed                             # seed roles/settings/location
php bin/infoloop install                          # migrate + seed
php bin/infoloop user:create <u> <email> <pw> [role] [location_id]
php bin/infoloop user:reset-password <username>   # interactive reset (forgot password)
php bin/infoloop user:list
php bin/infoloop ldap:sync                        # sync users from LDAP/AD
php bin/infoloop screen:token <screen_id>
php bin/infoloop log:tail [lines]
```

## Project layout

```
InfoLoop/
├── app/
│   ├── bootstrap.php            # autoloader + .env + config loading
│   ├── config/                  # app.php, routes.php
│   ├── Core/                    # App, Router, Request, Response, Controller, View,
│   │                            #   Database, Model, Migrator, Session, Csrf, Logger,
│   │                            #   Validator, Middleware, SecurityHeadersMiddleware
│   ├── Http/Controllers/        # public (Home, Api, File) + Admin/* controllers
│   ├── Http/Middleware/         # AuthMiddleware, RoleMiddleware
│   ├── Models/                  # User, Role, Location, Screen, Page, Playlist, MediaFile
│   ├── Services/                # Auth, Totp, Ldap, Media, Page, Screen, Stats, Settings, HtmlSanitizer
│   ├── database/
│   │   ├── migrations/          # versioned SQL (forward-only)
│   │   └── seed.sql
│   └── views/                   # kiosk + admin + errors + install templates
├── bin/infoloop                 # CLI (migrate, seed, doctor, users, ldap sync…)
├── bin/update-tinymce.sh        # upgrade the vendored TinyMCE to the latest release
├── docs/nginx.conf              # ready-to-use nginx site
├── public/                      # nginx document root (only index.php is PHP)
│   ├── index.php                # front controller
│   ├── assets/                  # kiosk.css/js, admin.css/js
│   │   └── vendor/tinymce/      # pinned TinyMCE (WYSIWYG editor), see VERSION; upgrade via bin/update-tinymce.sh
│   └── files/                   # uploaded media (sharded ab/cd/<sha256>.ext)
├── storage/                     # logs, app.key (gitignored)
└── tests/                       # unit tests (php tests/run.php)
```

## Running the tests

```bash
php tests/run.php          # or: composer test
composer lint              # php -l on every PHP file
```

## Security notes

- All DB access uses PDO prepared statements.
- All state-changing admin routes require a CSRF token; sessions use
  `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, with regeneration on login.
- Admin-authored HTML is sanitized (DOM-based allow-list); raw custom
  HTML/CSS/JS fields are admin-only by design.
- Uploads are validated by content (finfo), allow-listed, and SVG files with
  scripts are rejected; files are stored content-addressed (sha256) in
  `public/files/ab/cd/…`.
- Admin passwords use argon2id; TOTP secrets are encrypted at rest (libsodium,
  key in `storage/app.key` or `APP_KEY`).
- Failed logins are rate-limited per account and per IP (lockout after 5).
- Kiosk data is scoped by an unguessable per-screen token; engagement events
  are anonymous (no personal data — GDPR-friendly).

## License

MIT
