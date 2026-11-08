<?php
declare(strict_types=1);

namespace App\Helpers;

/** Rendu des vues avec gabarit. */
final class View
{
    /**
     * @param string $view   chemin dans app/Views, ex. 'trucks/index'
     * @param array  $data   variables disponibles dans la vue
     * @param string $layout 'app' (connecté), 'auth' (connexion) ou '' (aucun)
     */
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $file = APP_PATH . '/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vue introuvable : ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }
        $title = $data['title'] ?? config('name');
        require APP_PATH . '/Views/layouts/' . $layout . '.php';
    }

    public static function error(int $code, string $title, string $message): never
    {
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        $user = \App\Middleware\Auth::user();
        self::render('errors/error', compact('code', 'title', 'message'), $user ? 'app' : 'auth');
        exit;
    }
}
