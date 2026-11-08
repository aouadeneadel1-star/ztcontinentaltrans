<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\DB;
use App\Helpers\Session;
use App\Helpers\View;

/** Authentification et contrôle des rôles (toujours côté serveur). */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (empty($_SESSION['uid'])) {
            return null;
        }
        $u = DB::one('SELECT id, role, first_name, last_name, email, phone, is_active, must_change_password FROM users WHERE id = ?', [$_SESSION['uid']]);
        // Compte supprimé/désactivé ou navigateur différent : on coupe la session
        $uaOk = ($_SESSION['ua'] ?? '') === hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!$u || !(int)$u['is_active'] || !$uaOk) {
            Session::destroy();
            session_start();
            return null;
        }
        return self::$user = $u;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function is(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function isStaff(): bool
    {
        return self::is('admin', 'manager');
    }

    /** Exige une connexion, et éventuellement un rôle parmi $roles. */
    public static function require(array $roles = []): array
    {
        $user = self::user();
        if (!$user) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? null;
            }
            redirect('auth/login');
        }
        if ((int)$user['must_change_password'] === 1 && ($_GET['r'] ?? '') !== 'auth/password' && ($_GET['r'] ?? '') !== 'auth/logout') {
            redirect('auth/password');
        }
        if ($roles && !in_array($user['role'], $roles, true)) {
            View::error(403, 'Accès refusé', 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        return $user;
    }

    /** Page d'accueil selon le rôle. */
    public static function home(): string
    {
        return self::is('driver') ? 'driver/home' : 'dashboard/index';
    }
}
