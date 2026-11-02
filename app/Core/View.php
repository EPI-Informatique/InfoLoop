<?php

/**
 * Plain-PHP template renderer with layout support and output escaping.
 *
 * Templates live in app/views and are rendered with extract()ed data.
 * The renderer always escapes via e() unless raw() is used explicitly.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class View
{
    private string $viewPath;

    private ?string $layout = null;

    /** @var array<string, mixed> */
    private array $sections = [];

    /** @var array<string, mixed> Last non-empty data set, reused for nested layout renders. */
    private array $lastData = [];

    private string $sectionName = '';

    public function __construct(App $app)
    {
        $this->viewPath = INFOLOOP_APP . '/views';
    }

    /**
     * Renders a template, optionally inside a layout.
     *
     * Templates that wrap themselves in a layout call
     * `$this->render('layouts/x', ['content' => …])` from inside the
     * template; the data passed there must not drop the variables the
     * controller injected (app, settings, currentUser, flash, csrfToken),
     * so the last full data set is remembered and used as the base.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $this->layout = $layout;
        $this->sections = [];
        if ($data !== []) {
            $this->lastData = $data;
        }
        $data = $this->lastData;
        $content = $this->renderTemplate($template, $data);
        if ($layout !== null) {
            $data['content'] = $content;
            $data['view'] = $this;
            return $this->renderTemplate($layout, $data);
        }
        return $content;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTemplate(string $template, array $data): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template} ({$file})");
        }
        $data['view'] = $this;
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /**
     * Starts a named section (used inside templates).
     */
    public function startSection(string $name): void
    {
        $this->sectionName = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $this->sections[$this->sectionName] = (string) ob_get_clean();
        $this->sectionName = '';
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Renders a partial template and returns its output.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderTemplate($template, $data);
    }

    /**
     * Includes a URL for a named route via the router stored in $data['router'].
     *
     * @param array<string, string|int> $params
     */
    public function route(string $name, array $params = []): string
    {
        $router = $GLOBALS['infoloop_router'] ?? null;
        if ($router instanceof Router) {
            return $router->url($name, $params);
        }
        return '#';
    }
}
