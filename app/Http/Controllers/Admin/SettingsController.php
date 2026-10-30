<?php

/**
 * Global settings controller: branding (logo, name, slogan, palette),
 * default timeouts, LDAP configuration.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Validator;
use InfoLoop\Services\MediaService;
use InfoLoop\Services\SettingsService;

final class SettingsController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $settings = $this->app->service(SettingsService::class);
        return $this->view('admin/settings/index', [
            'branding' => $settings->branding(),
            'defaults' => $settings->get('defaults'),
            'ldap' => $settings->get('ldap'),
        ]);
    }

    public function saveBranding(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $v = Validator::make($request->allInput())
            ->required('brand_name')->string('brand_name', 1, 100);
        $colorFields = ['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color'];
        foreach ($colorFields as $field) {
            $value = $request->string($field);
            if ($value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $this->flash('error', 'Colors must be hex values like #2563eb.');
                return $this->redirect('/admin/settings');
            }
        }
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/settings');
        }
        $settings = $this->app->service(SettingsService::class);
        $branding = $settings->branding();
        $branding['brand_name'] = $request->string('brand_name');
        $branding['slogan'] = $request->string('slogan');
        foreach ($colorFields as $field) {
            $value = $request->string($field);
            if ($value !== '') {
                $branding[$field] = $value;
            }
        }
        $branding['font_family'] = $request->string('font_family') ?: $branding['font_family'];

        $logoFile = $request->file('logo');
        if ($logoFile !== null) {
            $result = $this->app->service(MediaService::class)->store($logoFile, null, $this->currentUserId());
            if ($result['ok']) {
                $branding['logo_file_id'] = (int) $result['media']['id'];
                $media = $this->app->service(MediaService::class);
                $branding['logo_url'] = $media->publicUrl($result['media']);
                $media->recordUsage((int) $result['media']['id'], null, null, 'branding');
            } else {
                $this->flash('error', $result['error'] ?? 'Logo upload failed.');
                return $this->redirect('/admin/settings');
            }
        }
        if ($request->bool('remove_logo', false)) {
            $branding['logo_file_id'] = null;
            $branding['logo_url'] = null;
        }
        $settings->set('branding', $branding);
        $this->app->logger()->info('Branding updated', ['brand' => $branding['brand_name']]);
        $this->flash('success', 'Branding saved.');
        return $this->redirect('/admin/settings');
    }

    public function saveDefaults(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $settings = $this->app->service(SettingsService::class);
        $defaults = $settings->get('defaults');
        $defaults['inactivity_timeout_sec'] = $request->int('inactivity_timeout_sec', 30, 0, 86400);
        $defaults['timeout_enabled'] = $request->bool('timeout_enabled', true);
        $defaults['default_playlist_duration_sec'] = $request->int('default_playlist_duration_sec', 15, 1, 86400);
        $settings->set('defaults', $defaults);
        $this->flash('success', 'Defaults saved.');
        return $this->redirect('/admin/settings');
    }

    public function saveLdap(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $v = Validator::make($request->allInput())
            ->string('host', 0, 255)
            ->int('port', 1, 1, 65535)
            ->string('base_dn', 0, 512)
            ->string('user_filter', 1, 255)
            ->string('default_role', 1, 50);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/settings');
        }
        $settings = $this->app->service(SettingsService::class);
        $ldap = $settings->get('ldap');
        $ldap['enabled'] = $request->bool('enabled', false);
        $ldap['host'] = $request->string('host');
        $ldap['port'] = $request->int('port', 389, 1, 65535);
        $ldap['base_dn'] = $request->string('base_dn');
        $ldap['bind_dn'] = $request->string('bind_dn');
        $ldap['bind_password'] = $request->string('bind_password');
        $ldap['user_filter'] = $request->string('user_filter', '(uid={username})');
        $ldap['group_filter'] = $request->string('group_filter', '(member={dn})');
        $ldap['attr_username'] = $request->string('attr_username', 'uid');
        $ldap['attr_email'] = $request->string('attr_email', 'mail');
        $ldap['attr_display_name'] = $request->string('attr_display_name', 'displayName');
        $ldap['default_role'] = $request->string('default_role', 'viewer');
        $ldap['allowed_group'] = $request->string('allowed_group');
        $settings->set('ldap', $ldap);
        $this->app->logger()->info('LDAP settings saved', ['enabled' => $ldap['enabled']]);
        $this->flash('success', 'LDAP settings saved.');
        return $this->redirect('/admin/settings');
    }

    public function testLdap(Request $request, array $params): Response
    {
        $ldap = $this->app->service(\InfoLoop\Services\LdapService::class);
        if (!$ldap->isEnabled()) {
            return $this->json(['ok' => false, 'error' => 'LDAP is disabled or the ldap extension is missing.']);
        }
        $ok = $ldap->authenticate($request->string('username'), $request->string('password'));
        return $this->json(['ok' => $ok]);
    }

    private function currentUserId(): ?int
    {
        $user = $this->app->service(\InfoLoop\Services\AuthService::class)->currentUser();
        return $user !== null ? (int) $user['id'] : null;
    }
}
