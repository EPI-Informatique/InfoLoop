<?php

/**
 * Web installer.
 *
 * Runs only while the `installed` table is empty: creates the schema
 * (migrations), seeds roles/settings, and creates the initial super admin
 * with a password chosen during installation (resettable later via CLI:
 * `php bin/infoloop user:reset-password admin`).
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Migrator;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Validator;
use InfoLoop\Services\AuthService;

final class InstallController extends Controller
{
    public function isInstalled(): bool
    {
        try {
            $row = $this->app->db()->query('SELECT id FROM installed LIMIT 1')->fetch();
            return $row !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function show(Request $request, array $params): Response
    {
        if ($this->isInstalled()) {
            return $this->redirect('/admin/login');
        }
        return $this->view('install/index', ['step' => 'db'], 200);
    }

    public function run(Request $request, array $params): Response
    {
        if ($this->isInstalled()) {
            return $this->redirect('/admin/login');
        }
        $csrf = $this->app->service(Csrf::class);
        if (!$csrf->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $v = Validator::make($request->allInput())
            ->required('admin_username')->string('admin_username', 3, 100)
            ->required('admin_email')->email('admin_email')
            ->required('admin_password')->string('admin_password', 12, 255)
            ->required('admin_password_confirm')->string('admin_password_confirm', 12, 255);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/install');
        }
        $input = $v->sanitized();
        if ($input['admin_password'] !== $input['admin_password_confirm']) {
            $this->flash('error', 'Passwords do not match.');
            return $this->redirect('/install');
        }

        try {
            $migrator = new Migrator($this->app);
            $migrator->migrate();
            $this->seed();
            $auth = $this->app->service(AuthService::class);
            $superRole = $this->app->db()->query("SELECT id FROM roles WHERE name = 'super_admin'")->fetch();
            $db = new \InfoLoop\Core\Database($this->app->db());
            $db->insert('users', [
                'username' => $input['admin_username'],
                'email' => $input['admin_email'],
                'display_name' => $input['admin_username'],
                'password_hash' => $auth->hashPassword((string) $input['admin_password']),
                'role_id' => (int) $superRole['id'],
                'source' => 'local',
                'is_active' => 1,
            ]);
            $db->insert('installed', ['id' => 1, 'version' => '1.0.0']);
            $this->app->logger()->info('InfoLoop installed', ['admin' => $input['admin_username']]);
            $this->flash('success', 'InfoLoop is installed. Sign in with your admin account.');
            return $this->redirect('/admin/login');
        } catch (\Throwable $e) {
            $this->app->logger()->error('Installation failed', ['error' => $e->getMessage()]);
            $this->flash('error', 'Installation failed: ' . $e->getMessage());
            return $this->redirect('/install');
        }
    }

    /**
     * Applies seed.sql statements (roles, default location, settings).
     */
    private function seed(): void
    {
        $sql = (string) file_get_contents(INFOLOOP_APP . '/database/seed.sql');
        foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement !== '' && !str_starts_with($statement, '--')) {
                $this->app->db()->exec($statement);
            }
        }
    }
}
