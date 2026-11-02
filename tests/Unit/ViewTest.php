<?php

/**
 * Unit tests for the View renderer.
 *
 * Key regression: when a template wraps itself in a layout via
 * `$this->render('layouts/x', ['content' => …])`, the layout must still
 * receive the variables the controller injected (app, settings,
 * currentUser, flash, csrfToken). Previously the partial data array
 * replaced the full one, so layouts crashed with "service() on null".
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Core\App;
use InfoLoop\Core\View;

final class ViewTest extends \TestCase
{
    private function app(): App
    {
        return new App([
            'app' => ['timezone' => 'UTC'],
            'log' => ['dir' => sys_get_temp_dir(), 'level' => 'error'],
        ]);
    }

    public function testRenderWithoutLayoutReturnsTemplate(): void
    {
        $view = new View($this->app());
        $html = $view->render('errors/404', ['title' => 'Not found']);
        $this->assertStringContains('404', $html);
    }

    public function testPartialDataRenderKeepsPreviousVariables(): void
    {
        // A View subclass records what the *layout* render receives.
        $probe = new class ($this->app()) extends View {
            /** @var array<string, mixed>|null */
            public ?array $layoutData = null;

            public function render(string $template, array $data = [], ?string $layout = null): string
            {
                if ($template === 'layouts/probe') {
                    $this->layoutData = $data;
                    return 'LAYOUT';
                }
                return parent::render($template, $data, $layout);
            }
        };

        // 1st render: full controller data (as Controller::view() does).
        $probe->render('errors/404', ['app' => 'APP_OBJ', 'brand' => 'InfoLoop', 'title' => 'x']);
        // 2nd render: template wraps itself in a layout passing only partial data.
        $probe->render('layouts/probe', ['content' => 'INNER', 'pageTitle' => 'p']);

        $this->assertNotNull($probe->layoutData);
        $this->assertSame('APP_OBJ', $probe->layoutData['app'] ?? null, 'layout must inherit controller-injected vars');
        $this->assertSame('InfoLoop', $probe->layoutData['brand'] ?? null);
        $this->assertSame('INNER', $probe->layoutData['content'] ?? null);
    }
}
