<?php

/**
 * Unit tests for SettingsService defaults and CSS variable generation.
 * Uses a fake PDO-less path: we only test the pure merge logic via
 * reflection-free defaults by constructing with an App whose db() is never
 * called for branding defaults... branding() calls all() which hits DB,
 * so here we test cssVariables indirectly is not possible without DB.
 * Instead we test the pure DEFAULTS via a stubbed service.
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Core\App;
use InfoLoop\Services\SettingsService;

final class SettingsServiceTest extends \TestCase
{
    public function testDefaultsAreComplete(): void
    {
        $ref = new \ReflectionClass(SettingsService::class);
        $defaults = $ref->getConstant('DEFAULTS');
        foreach (['brand_name', 'slogan', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color', 'font_family'] as $key) {
            $this->assertTrue(array_key_exists($key, $defaults['branding']), "branding.{$key} default missing");
        }
        $this->assertSame(30, $defaults['defaults']['inactivity_timeout_sec']);
        $this->assertTrue($defaults['defaults']['timeout_enabled']);
        $this->assertFalse($defaults['ldap']['enabled']);
    }

    public function testCssVariablesFormat(): void
    {
        $app = new App(['app' => ['timezone' => 'UTC'], 'log' => ['dir' => sys_get_temp_dir(), 'level' => 'error']]);
        $service = new SettingsService($app);
        // Force the cache to defaults only (no DB available in tests): all()
        // catches PDOException and returns DEFAULTS.
        $ref = new \ReflectionProperty(SettingsService::class, 'cache');
        $ref->setAccessible(true);
        $ref->setValue($service, null);
        $css = $service->cssVariables();
        $this->assertStringContains(':root{', $css);
        $this->assertStringContains('--il-primary:#2563eb', $css);
        $this->assertStringContains('--il-bg:#ffffff', $css);
    }
}
