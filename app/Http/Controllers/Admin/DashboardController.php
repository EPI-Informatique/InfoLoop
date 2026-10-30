<?php

/**
 * Admin dashboard controller: engagement stats per page and per location,
 * headline KPIs and per-screen activity.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Services\AuthService;
use InfoLoop\Services\StatsService;

final class DashboardController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $auth = $this->app->service(AuthService::class);
        $user = $auth->currentUser();
        $locationId = $this->scopedLocation($user);
        $days = $request->int('days', 30, 1, 365);
        $stats = $this->app->service(StatsService::class);
        return $this->view('admin/dashboard', [
            'headline' => $stats->headline($locationId, $days),
            'topPages' => $stats->topPages($days, $locationId, 10),
            'byLocation' => $stats->byLocation($days),
            'series' => $stats->dailySeries($days, $locationId),
            'screens' => $stats->screens($locationId, 7),
            'days' => $days,
        ]);
    }

    /**
     * JSON endpoint powering the dashboard charts.
     */
    public function statsJson(Request $request, array $params): Response
    {
        $user = $this->app->service(AuthService::class)->currentUser();
        $locationId = $this->scopedLocation($user);
        $days = $request->int('days', 30, 1, 365);
        $stats = $this->app->service(StatsService::class);
        return $this->json([
            'headline' => $stats->headline($locationId, $days),
            'series' => $stats->dailySeries($days, $locationId),
            'topPages' => $stats->topPages($days, $locationId, 10),
            'byLocation' => $stats->byLocation($days),
        ]);
    }

    /**
     * Location scoping for location admins.
     */
    private function scopedLocation(?array $user): ?int
    {
        if ($user === null) {
            return null;
        }
        $auth = $this->app->service(AuthService::class);
        $isGlobal = $auth->isSuperAdmin($user) || ($user['role_name'] ?? '') === 'admin';
        return $isGlobal ? null : (((int) ($user['location_id'] ?? 0)) ?: null);
    }
}
