<?php

/**
 * Admin user & RBAC management (super admin only): create/edit/deactivate
 * local users, assign roles and location scope, trigger LDAP sync.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Database;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Validator;
use InfoLoop\Models\Location;
use InfoLoop\Models\Role;
use InfoLoop\Models\User;
use InfoLoop\Services\AuthService;
use InfoLoop\Services\LdapService;

final class UserController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        return $this->view('admin/users/index', [
            'users' => $this->app->service(User::class)->withRoles(),
        ]);
    }

    public function store(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $v = Validator::make($request->allInput())
            ->required('username')->string('username', 3, 100)
            ->required('email')->email('email')
            ->required('role_id')->int('role_id', 1)
            ->required('password')->string('password', 12, 255);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/users');
        }
        $input = $v->sanitized();
        $locationId = $request->int('location_id', 0);
        $db = new Database($this->app->db());
        $db->insert('users', [
            'username' => $input['username'],
            'email' => $input['email'],
            'display_name' => $request->string('display_name') ?: $input['username'],
            'password_hash' => $this->app->service(AuthService::class)->hashPassword((string) $input['password']),
            'role_id' => (int) $input['role_id'],
            'location_id' => $locationId > 0 ? $locationId : null,
            'source' => 'local',
            'is_active' => $request->bool('is_active', true) ? 1 : 0,
        ]);
        $this->app->logger()->info('Admin user created', ['username' => $input['username']]);
        $this->flash('success', 'User created.');
        return $this->redirect('/admin/users');
    }

    public function update(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $id = (int) ($params['id'] ?? 0);
        $v = Validator::make($request->allInput())
            ->required('email')->email('email')
            ->required('role_id')->int('role_id', 1);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/users');
        }
        $input = $v->sanitized();
        $updates = [
            'email' => $input['email'],
            'display_name' => $request->string('display_name'),
            'role_id' => (int) $input['role_id'],
            'location_id' => $request->int('location_id', 0) ?: null,
            'is_active' => $request->bool('is_active', true) ? 1 : 0,
        ];
        $password = $request->string('password');
        if ($password !== '') {
            if (mb_strlen($password) < 12) {
                $this->flash('error', 'Password must be at least 12 characters.');
                return $this->redirect('/admin/users');
            }
            $updates['password_hash'] = $this->app->service(AuthService::class)->hashPassword($password);
        }
        $db = new Database($this->app->db());
        $db->update('users', $updates, 'id = :id', ['id' => $id]);
        $this->flash('success', 'User updated.');
        return $this->redirect('/admin/users');
    }

    public function delete(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $id = (int) ($params['id'] ?? 0);
        $current = $this->app->service(AuthService::class)->currentUser();
        if ($current !== null && (int) $current['id'] === $id) {
            $this->flash('error', 'You cannot delete your own account.');
            return $this->redirect('/admin/users');
        }
        $this->app->service(User::class)->deleteById($id);
        $this->flash('success', 'User deleted.');
        return $this->redirect('/admin/users');
    }

    public function ldapSync(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        try {
            $result = $this->app->service(LdapService::class)->syncUsers();
            $this->flash('success', "LDAP sync done: {$result['created']} created, {$result['updated']} updated.");
        } catch (\Throwable $e) {
            $this->app->logger()->error('LDAP sync failed', ['error' => $e->getMessage()]);
            $this->flash('error', 'LDAP sync failed: ' . $e->getMessage());
        }
        return $this->redirect('/admin/users');
    }

    /**
     * Form data: roles + locations for the user form.
     */
    public function formData(Request $request, array $params): Response
    {
        return $this->json([
            'roles' => $this->app->service(Role::class)->allOrdered(),
            'locations' => $this->app->service(Location::class)->active(),
        ]);
    }
}
