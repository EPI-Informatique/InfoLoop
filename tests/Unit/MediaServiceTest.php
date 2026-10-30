<?php

/**
 * Unit tests for MediaService logic that does not need a database:
 * shard paths, media-id extraction from HTML, MIME allow-list.
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Core\App;
use InfoLoop\Services\MediaService;

final class MediaServiceTest extends \TestCase
{
    private function app(): App
    {
        return new App([
            'app' => ['timezone' => 'UTC'],
            'log' => ['dir' => sys_get_temp_dir(), 'level' => 'error'],
            'files' => ['base_dir' => sys_get_temp_dir() . '/il-files-test', 'base_url' => '/files'],
            'upload' => ['max_mb' => 1],
            'db' => ['host' => '', 'port' => 0, 'name' => '', 'user' => '', 'pass' => '', 'charset' => 'utf8mb4'],
        ]);
    }

    public function testShardPathLayout(): void
    {
        $service = new MediaService($this->app());
        $m = new \ReflectionMethod($service, 'shardPath');
        $sha = str_repeat('ab', 32);
        $this->assertSame('ab/ab/' . $sha . '.png', $m->invoke($service, $sha, '.png'));
    }

    public function testExtractMediaIdsFindsFilesUrls(): void
    {
        $service = new MediaService($this->app());
        // Without a DB the lookup returns nothing, but the regex must match sha URLs
        $sha = hash('sha256', 'x');
        $html = '<img src="/files/' . substr($sha, 0, 2) . '/' . substr($sha, 2, 2) . '/' . $sha . '.png">';
        $this->assertSame([], $service->extractMediaIds($html), 'no DB -> no ids, but no crash');
    }

    public function testAllowedMimesContainsRequiredTypes(): void
    {
        $service = new MediaService($this->app());
        $mimes = $service->allowedMimes();
        foreach (['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/gif', 'video/mp4', 'application/pdf'] as $mime) {
            $this->assertTrue(in_array($mime, $mimes, true), "missing {$mime}");
        }
    }

    public function testSvgScriptDetection(): void
    {
        $service = new MediaService($this->app());
        $m = new \ReflectionMethod($service, 'svgContainsScript');
        $this->assertTrue($m->invoke($service, '<svg><script>alert(1)</script></svg>'));
        $this->assertTrue($m->invoke($service, '<svg onload="alert(1)"></svg>'));
        $this->assertFalse($m->invoke($service, '<svg><circle r="5"/></svg>'));
    }

    public function testStoreRejectsDisallowedMime(): void
    {
        $service = new MediaService($this->app());
        $result = $service->store(null, '<?php echo "evil"; ?>', null);
        $this->assertFalse($result['ok']);
        $this->assertStringContains('not allowed', $result['error'] ?? '');
    }

    public function testStoreRejectsEmpty(): void
    {
        $service = new MediaService($this->app());
        $result = $service->store(null, '', null);
        $this->assertFalse($result['ok']);
    }
}
