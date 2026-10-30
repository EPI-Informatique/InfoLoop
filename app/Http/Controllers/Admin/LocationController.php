<?php

/**
 * Location management (super admin / global admin only).
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Validator;
use InfoLoop\Models\Location;

final class LocationController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        return $this->view('admin/locations/index', [
            'locations' => $this->app->service(Location::class)->all('name', 'ASC'),
        ]);
    }

    public function store(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $v = Validator::make($request->allInput())
            ->required('name')->string('name', 1, 150)
            ->string('timezone', 1, 64);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/locations');
        }
        $input = $v->sanitized();
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', trim((string) $input['name'])));
        $this->app->service(Location::class)->create([
            'name' => $input['name'],
            'slug' => trim($slug, '-') ?: 'loc-' . bin2hex(random_bytes(3)),
            'description' => $request->string('description'),
            'timezone' => $input['timezone'] ?: 'UTC',
            'is_active' => $request->bool('is_active', true) ? 1 : 0,
        ]);
        $this->flash('success', 'Location created.');
        return $this->redirect('/admin/locations');
    }

    public function update(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $id = (int) ($params['id'] ?? 0);
        $v = Validator::make($request->allInput())
            ->required('name')->string('name', 1, 150)
            ->string('timezone', 1, 64);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/locations');
        }
        $input = $v->sanitized();
        $this->app->service(Location::class)->updateById($id, [
            'name' => $input['name'],
            'description' => $request->string('description'),
            'timezone' => $input['timezone'] ?: 'UTC',
            'is_active' => $request->bool('is_active', true) ? 1 : 0,
        ]);
        $this->flash('success', 'Location updated.');
        return $this->redirect('/admin/locations');
    }

    public function delete(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $id = (int) ($params['id'] ?? 0);
        $this->app->service(Location::class)->deleteById($id);
        $this->flash('success', 'Location deleted.');
        return $this->redirect('/admin/locations');
    }
}
