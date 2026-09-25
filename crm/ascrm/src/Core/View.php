<?php
declare(strict_types=1);

namespace DR\Core;

final class View
{
    /** Render a template inside a layout (app | portal | auth | print | none). */
    public static function render(string $template, array $data = [], string $layout = 'app'): void
    {
        $installed = Config::installed();
        $data['user'] = $data['user'] ?? ($installed ? Auth::userOrNull() : null);
        $content = self::partial($template, $data);
        if ($layout === 'none') {
            echo $content;
            return;
        }
        $data['content'] = $content;
        $data['flashes'] = ($installed && Session::row()) ? (Session::pull('_flash', []) ?: []) : [];
        echo self::partial('layouts/' . $layout, $data);
    }

    public static function partial(string $template, array $data = []): string
    {
        $file = DR_ROOT . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function badge(string $key, ?string $text = null): string
    {
        return '<span class="badge badge-' . Labels::tone($key) . '">' . h($text ?? Labels::get($key)) . '</span>';
    }
}
