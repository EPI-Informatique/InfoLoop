<?php

/**
 * InfoLoop route table.
 *
 * Each entry: [methods[], pattern, handler("Class@method"), name?, middleware[]].
 * Middleware aliases: "web" adds security headers, "auth" requires an admin
 * session, "role:super_admin" restricts to super admins.
 */

declare(strict_types=1);

use InfoLoop\Http\Controllers\Admin\AccountController;
use InfoLoop\Http\Controllers\Admin\AuthController;
use InfoLoop\Http\Controllers\Admin\DashboardController;
use InfoLoop\Http\Controllers\Admin\LocationController;
use InfoLoop\Http\Controllers\Admin\MediaController;
use InfoLoop\Http\Controllers\Admin\PageController;
use InfoLoop\Http\Controllers\Admin\PlaylistController;
use InfoLoop\Http\Controllers\Admin\ScreenController;
use InfoLoop\Http\Controllers\Admin\SettingsController;
use InfoLoop\Http\Controllers\Admin\UserController;
use InfoLoop\Http\Controllers\ApiController;
use InfoLoop\Http\Controllers\FileController;
use InfoLoop\Http\Controllers\HomeController;
use InfoLoop\Http\Controllers\InstallController;

return [
    // ---- Installer (only while not installed) ----
    [['GET'], '/install', InstallController::class . '@show', 'install', ['web']],
    [['POST'], '/install', InstallController::class . '@run', 'install.run', ['web']],

    // ---- Public kiosk side ----
    [['GET'], '/', HomeController::class . '@index', 'home', ['web']],
    [['GET'], '/page/{slug}', HomeController::class . '@page', 'page', ['web']],
    [['GET'], '/api/health', ApiController::class . '@health', 'api.health', ['web']],
    [['GET'], '/api/screen/config', ApiController::class . '@screenConfig', 'api.screen', ['web']],
    [['GET'], '/api/page/{slug}', ApiController::class . '@page', 'api.page', ['web']],
    [['POST'], '/api/stats/event', ApiController::class . '@statsEvent', 'api.stats', ['web']],
    [['GET'], '/api/media-file/{id}', ApiController::class . '@mediaFile', 'api.media.file', ['web']],
    [['GET'], '/api/media-pdf/{id}', ApiController::class . '@mediaFile', 'api.media.pdf', ['web']],
    // Fallback file serving (nginx normally serves /files/* directly)
    [['GET'], '/files-fallback/{path}', FileController::class . '@serve', 'files.fallback', ['web']],

    // ---- Admin auth ----
    [['GET'], '/admin/login', AuthController::class . '@showLogin', 'admin.login', ['web']],
    [['POST'], '/admin/login', AuthController::class . '@login', 'admin.login.post', ['web']],
    [['POST'], '/admin/logout', AuthController::class . '@logout', 'admin.logout', ['web']],
    [['GET'], '/admin/mfa', AuthController::class . '@showMfa', 'admin.mfa', ['web']],
    [['POST'], '/admin/mfa', AuthController::class . '@verifyMfa', 'admin.mfa.post', ['web']],
    [['GET'], '/admin/mfa/setup', AuthController::class . '@showMfaSetup', 'admin.mfa.setup', ['web', 'auth']],
    [['POST'], '/admin/mfa/setup', AuthController::class . '@enableMfa', 'admin.mfa.enable', ['web', 'auth']],
    [['POST'], '/admin/mfa/disable', AuthController::class . '@disableMfa', 'admin.mfa.disable', ['web', 'auth']],

    // ---- Admin area ----
    [['GET'], '/admin', DashboardController::class . '@index', 'admin.dashboard', ['web', 'auth']],
    [['GET'], '/admin/stats.json', DashboardController::class . '@statsJson', 'admin.stats', ['web', 'auth']],
    [['GET'], '/admin/account', AccountController::class . '@index', 'admin.account', ['web', 'auth']],

    // Pages & revisions
    [['GET'], '/admin/pages', PageController::class . '@index', 'admin.pages', ['web', 'auth']],
    [['GET'], '/admin/pages/create', PageController::class . '@createForm', 'admin.pages.create', ['web', 'auth']],
    [['POST'], '/admin/pages', PageController::class . '@store', 'admin.pages.store', ['web', 'auth']],
    [['GET'], '/admin/pages/{id}/edit', PageController::class . '@editForm', 'admin.pages.edit', ['web', 'auth']],
    [['POST'], '/admin/pages/{id}', PageController::class . '@update', 'admin.pages.update', ['web', 'auth']],
    [['POST'], '/admin/pages/{id}/delete', PageController::class . '@delete', 'admin.pages.delete', ['web', 'auth']],
    [['GET'], '/admin/pages/{id}/revisions', PageController::class . '@revisions', 'admin.pages.revisions', ['web', 'auth']],
    [['GET'], '/admin/pages/{id}/revisions/{revision}', PageController::class . '@showRevision', 'admin.pages.revision', ['web', 'auth']],
    [['POST'], '/admin/pages/{id}/revisions/{revision}/restore', PageController::class . '@restoreRevision', 'admin.pages.restore', ['web', 'auth']],
    [['POST'], '/admin/pages/{id}/revisions/{revision}/delete', PageController::class . '@deleteRevision', 'admin.pages.revision.delete', ['web', 'auth']],
    [['POST'], '/admin/pages/upload-inline', PageController::class . '@uploadInline', 'admin.pages.upload', ['web', 'auth']],

    // Media library
    [['GET'], '/admin/media', MediaController::class . '@index', 'admin.media', ['web', 'auth']],
    [['GET'], '/admin/media/list.json', MediaController::class . '@listJson', 'admin.media.list', ['web', 'auth']],
    [['POST'], '/admin/media/upload', MediaController::class . '@upload', 'admin.media.upload', ['web', 'auth']],
    [['GET'], '/admin/media/{id}/usages', MediaController::class . '@usages', 'admin.media.usages', ['web', 'auth']],
    [['POST'], '/admin/media/{id}/delete', MediaController::class . '@delete', 'admin.media.delete', ['web', 'auth']],

    // Locations
    [['GET'], '/admin/locations', LocationController::class . '@index', 'admin.locations', ['web', 'auth']],
    [['POST'], '/admin/locations', LocationController::class . '@store', 'admin.locations.store', ['web', 'auth']],
    [['POST'], '/admin/locations/{id}', LocationController::class . '@update', 'admin.locations.update', ['web', 'auth']],
    [['POST'], '/admin/locations/{id}/delete', LocationController::class . '@delete', 'admin.locations.delete', ['web', 'auth']],

    // Screens
    [['GET'], '/admin/screens', ScreenController::class . '@index', 'admin.screens', ['web', 'auth']],
    [['GET'], '/admin/screens/form.json', ScreenController::class . '@formData', 'admin.screens.form', ['web', 'auth']],
    [['POST'], '/admin/screens', ScreenController::class . '@store', 'admin.screens.store', ['web', 'auth']],
    [['POST'], '/admin/screens/{id}', ScreenController::class . '@update', 'admin.screens.update', ['web', 'auth']],
    [['POST'], '/admin/screens/{id}/rotate-token', ScreenController::class . '@rotateToken', 'admin.screens.rotate', ['web', 'auth']],
    [['POST'], '/admin/screens/{id}/delete', ScreenController::class . '@delete', 'admin.screens.delete', ['web', 'auth']],

    // Playlists
    [['GET'], '/admin/playlists', PlaylistController::class . '@index', 'admin.playlists', ['web', 'auth']],
    [['POST'], '/admin/playlists', PlaylistController::class . '@store', 'admin.playlists.store', ['web', 'auth']],
    [['GET'], '/admin/playlists/{id}/edit', PlaylistController::class . '@edit', 'admin.playlists.edit', ['web', 'auth']],
    [['POST'], '/admin/playlists/{id}/items', PlaylistController::class . '@addItem', 'admin.playlists.add', ['web', 'auth']],
    [['POST'], '/admin/playlists/items/{item}', PlaylistController::class . '@updateItem', 'admin.playlists.item.update', ['web', 'auth']],
    [['POST'], '/admin/playlists/items/{item}/delete', PlaylistController::class . '@removeItem', 'admin.playlists.item.delete', ['web', 'auth']],
    [['POST'], '/admin/playlists/{id}/delete', PlaylistController::class . '@delete', 'admin.playlists.delete', ['web', 'auth']],

    // Users & roles (super admin)
    [['GET'], '/admin/users', UserController::class . '@index', 'admin.users', ['web', 'auth', 'role:super_admin']],
    [['GET'], '/admin/users/form.json', UserController::class . '@formData', 'admin.users.form', ['web', 'auth', 'role:super_admin']],
    [['POST'], '/admin/users', UserController::class . '@store', 'admin.users.store', ['web', 'auth', 'role:super_admin']],
    [['POST'], '/admin/users/{id}', UserController::class . '@update', 'admin.users.update', ['web', 'auth', 'role:super_admin']],
    [['POST'], '/admin/users/{id}/delete', UserController::class . '@delete', 'admin.users.delete', ['web', 'auth', 'role:super_admin']],
    [['POST'], '/admin/users/ldap-sync', UserController::class . '@ldapSync', 'admin.users.ldap', ['web', 'auth', 'role:super_admin']],

    // Settings (branding, defaults, LDAP)
    [['GET'], '/admin/settings', SettingsController::class . '@index', 'admin.settings', ['web', 'auth', 'role:super_admin']],
    [['POST'], '/admin/settings/branding', SettingsController::class . '@saveBranding', 'admin.settings.branding', ['web', 'auth', 'role:super_admin']],
    [['POST'], '/admin/settings/defaults', SettingsController::class . '@saveDefaults', 'admin.settings.defaults', ['web', 'auth', 'role:super_admin']],
    [['POST'], '/admin/settings/ldap', SettingsController::class . '@saveLdap', 'admin.settings.ldap', ['web', 'auth', 'role:super_admin']],
    [['POST'], '/admin/settings/ldap/test', SettingsController::class . '@testLdap', 'admin.settings.ldap.test', ['web', 'auth', 'role:super_admin']],
];
