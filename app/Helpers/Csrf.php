<?php
declare(strict_types=1);

namespace App\Helpers;

/** Protection CSRF : jeton de session vérifié sur toute requête POST. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(): void
    {
        $sent = (string)($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $good = (string)($_SESSION['_csrf'] ?? '');
        if ($good === '' || !hash_equals($good, $sent)) {
            http_response_code(419);
            \App\Helpers\View::error(419, 'Session expirée', 'Le formulaire a expiré ou est invalide. Rechargez la page et recommencez.');
        }
    }
}
