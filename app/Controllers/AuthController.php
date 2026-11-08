<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\DB;
use App\Helpers\Log;
use App\Helpers\Session;
use App\Middleware\Auth;
use App\Models\User;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::user()) {
            redirect(Auth::home());
        }
        $this->view('auth/login', ['title' => 'Connexion'], 'auth');
    }

    public function login(): void
    {
        $email = mb_strtolower($this->post('email', 190));
        $password = (string)($_POST['password'] ?? '');
        $ip = client_ip();

        // Limitation des tentatives (par e-mail et par IP)
        $window = date('Y-m-d H:i:s', time() - (int)config('login_window_min') * 60);
        $max = (int)config('login_max_attempts');
        $byEmail = (int)DB::value('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > ?', [$email, $window]);
        $byIp = (int)DB::value('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?', [$ip, $window]);
        if ($byEmail >= $max || $byIp >= $max * 3) {
            Log::add('auth.locked', 'user', null, $email);
            flash('error', 'Trop de tentatives. Réessayez dans ' . (int)config('login_window_min') . ' minutes.');
            redirect('auth/login');
        }

        $user = $email !== '' ? User::findByEmail($email) : null;
        // password_verify exécuté même si l'utilisateur n'existe pas (temps de réponse constant)
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsaltuseSomeSillyStringForSaltXXXXXXXXXXXXX';
        $ok = password_verify($password, $hash) && $user && (int)$user['is_active'] === 1;

        if (!$ok) {
            DB::insert('login_attempts', ['email' => $email, 'ip_address' => $ip]);
            Log::add('auth.failed', 'user', $user ? (int)$user['id'] : null, $email);
            Session::setOld(['email' => $email]);
            flash('error', 'Adresse e-mail ou mot de passe incorrect.');
            redirect('auth/login');
        }

        DB::run('DELETE FROM login_attempts WHERE email = ?', [$email]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::update((int)$user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }
        Session::login($user);
        User::update((int)$user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        Log::add('auth.login', 'user', (int)$user['id'], null, (int)$user['id']);

        if ((int)$user['must_change_password'] === 1) {
            redirect('auth/password');
        }
        $intended = $_SESSION['intended'] ?? null;
        unset($_SESSION['intended']);
        // Redirection interne uniquement (anti open-redirect)
        if (is_string($intended) && str_starts_with($intended, base_path() . '/') && !str_contains($intended, '//', 1)) {
            header('Location: ' . $intended);
            exit;
        }
        redirect($user['role'] === 'driver' ? 'driver/home' : 'dashboard/index');
    }

    public function logout(): void
    {
        Log::add('auth.logout', 'user', Auth::id());
        Session::destroy();
        session_start();
        flash('success', 'Vous êtes déconnecté.');
        redirect('auth/login');
    }

    public function passwordForm(): void
    {
        Auth::require();
        $this->view('auth/password', ['title' => 'Changer le mot de passe', 'forced' => (int)Auth::user()['must_change_password'] === 1]);
    }

    public function password(): void
    {
        $user = Auth::require();
        $row = User::find((int)$user['id']);
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        $errors = [];
        if (!password_verify($current, $row['password_hash'])) {
            $errors[] = 'Le mot de passe actuel est incorrect.';
        }
        $errors = array_merge($errors, self::passwordErrors($new, $confirm));
        if ($new !== '' && $new === $current) {
            $errors[] = 'Le nouveau mot de passe doit être différent de l\'ancien.';
        }
        if ($errors) {
            $this->fail($errors, 'auth/password');
        }
        User::update((int)$user['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0]);
        session_regenerate_id(true);
        Log::add('auth.password_changed', 'user', (int)$user['id']);
        flash('success', 'Votre mot de passe a été modifié.');
        redirect(Auth::home());
    }

    /** Règles de mot de passe communes (création / modification). */
    public static function passwordErrors(string $password, string $confirm): array
    {
        $min = (int)config('password_min_length', 10);
        $e = [];
        if (mb_strlen($password) < $min) {
            $e[] = 'Le mot de passe doit contenir au moins ' . $min . ' caractères.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $e[] = 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
        }
        if ($password !== $confirm) {
            $e[] = 'Les deux mots de passe ne correspondent pas.';
        }
        return $e;
    }
}
