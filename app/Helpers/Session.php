<?php
declare(strict_types=1);

namespace App\Helpers;

/** Gestion stricte des sessions. */
final class Session
{
    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name('ZTSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path() === '' ? '/' : base_path() . '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        if (isset($_SESSION['uid'])) {
            $idle = $now - ($_SESSION['last_activity'] ?? $now);
            $age  = $now - ($_SESSION['login_time'] ?? $now);
            if ($idle > (int)config('session_idle_timeout') || $age > (int)config('session_absolute_timeout')) {
                self::destroy();
                session_start();
                self::flash('warning', 'Votre session a expiré. Veuillez vous reconnecter.');
            } else {
                $_SESSION['last_activity'] = $now;
            }
        }
    }

    /** Ouvre une session authentifiée (régénère l'identifiant : anti fixation). */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['uid']           = (int)$user['id'];
        $_SESSION['login_time']    = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ua']            = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        unset($_SESSION['_csrf']);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function setOld(array $data): void
    {
        unset($data['_csrf'], $data['password'], $data['password_confirm'], $data['current_password']);
        $_SESSION['_old'] = $data;
    }

    public static function clearOld(): void
    {
        unset($_SESSION['_old']);
    }
}
